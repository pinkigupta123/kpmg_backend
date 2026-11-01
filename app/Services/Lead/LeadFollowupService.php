<?php

namespace App\Services\Lead;

use App\Models\LeadCustomer;
use App\Models\LeadCustomerTimeline;
use App\Models\LeadFollowup;
use App\Models\LeadUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Follow-up tracking: every scheduled follow-up is a row, so we can always answer
 * "was it done on the scheduled date, late, or not at all?".
 */
class LeadFollowupService
{
    /**
     * Schedule a follow-up for a lead. Any follow-up still pending for the lead is
     * marked "rescheduled" (history is kept) and lead_customers.followup_date points
     * at the new date.
     */
    public function schedule(LeadCustomer $customer, string $date, ?string $remarks, LeadUser $by): LeadFollowup
    {
        $date = substr($date, 0, 10);

        return DB::transaction(function () use ($customer, $date, $remarks, $by) {
            $pending = $customer->followups()->where('status', 'pending')->get();

            // Same date already pending → nothing to do.
            $same = $pending->first(fn ($f) => $f->scheduled_for->format('Y-m-d') === $date);
            if ($same && $pending->count() === 1) {
                $customer->update(['followup_date' => $date]);
                return $same;
            }

            $previousDate = $pending->first()?->scheduled_for?->format('Y-m-d');
            foreach ($pending as $p) {
                $p->update(['status' => 'rescheduled', 'resolved_at' => now(), 'resolved_by' => $by->id]);
            }

            $followup = LeadFollowup::create([
                'customer_id'   => $customer->id,
                'scheduled_for' => $date,
                'status'        => 'pending',
                'remarks'       => $remarks,
                'created_by'    => $by->id,
            ]);

            $customer->update(['followup_date' => $date]);

            $text = $previousDate
                ? "Follow-up rescheduled from {$previousDate} to {$date}"
                : "Follow-up scheduled for {$date}";
            if ($remarks) {
                $text .= " — {$remarks}";
            }

            LeadCustomerTimeline::create([
                'customer_id' => $customer->id,
                'action'      => $previousDate ? 'Follow-up Rescheduled' : 'Follow-up Added',
                'remarks'     => $text,
                'created_by'  => $by->id,
            ]);

            return $followup;
        });
    }

    /** Cancel any pending follow-ups (lead closed/purchased, or the date was cleared). */
    public function cancelPending(LeadCustomer $customer, LeadUser $by, bool $clearDate = false): void
    {
        $customer->followups()->where('status', 'pending')->update([
            'status'      => 'cancelled',
            'resolved_at' => now(),
            'resolved_by' => $by->id,
        ]);

        if ($clearDate) {
            $customer->update(['followup_date' => null]);
        }
    }

    /**
     * Mark a follow-up as done. Optionally update the lead status and schedule the next one.
     *
     * @param array{outcome?:?string,status?:?string,next_followup_date?:?string} $data
     */
    public function complete(LeadUser $by, int $followupId, array $data): LeadFollowup
    {
        $followup = LeadFollowup::with('customer')->findOrFail($followupId);
        $customer = $followup->customer;

        if ($by->role === 'sales_executive' && $customer->executive_id != $by->id) {
            abort(403, 'Unauthorized.');
        }

        if ($followup->status !== 'pending') {
            throw ValidationException::withMessages(['followup' => 'This follow-up is already ' . $followup->status . '.']);
        }

        return DB::transaction(function () use ($by, $followup, $customer, $data) {
            $followup->update([
                'status'      => 'done',
                'resolved_at' => now(),
                'resolved_by' => $by->id,
                'outcome'     => $data['outcome'] ?? null,
            ]);

            $scheduled = $followup->scheduled_for->copy()->startOfDay();
            $late      = (int) $scheduled->diffInDays(today(), false);
            $when      = $late > 0 ? "completed {$late} day" . ($late > 1 ? 's' : '') . ' late' : 'completed on time';
            $text      = 'Scheduled for ' . $scheduled->format('Y-m-d') . ' — ' . $when;
            if (!empty($data['outcome'])) {
                $text .= '. Outcome: ' . $data['outcome'];
            }

            LeadCustomerTimeline::create([
                'customer_id' => $customer->id,
                'action'      => 'Follow-up Completed',
                'remarks'     => $text,
                'created_by'  => $by->id,
            ]);

            // optional status change
            $newStatus = $data['status'] ?? null;
            if ($newStatus && $newStatus !== $customer->status) {
                LeadCustomerTimeline::create([
                    'customer_id' => $customer->id,
                    'action'      => 'Status Updated',
                    'remarks'     => 'Status updated from ' . strtoupper($customer->status) . ' to ' . strtoupper($newStatus),
                    'created_by'  => $by->id,
                ]);
                $customer->update(['status' => $newStatus]);
            }

            $closed = in_array($customer->status, ['purchased', 'closed'], true);
            if ($closed) {
                $this->cancelPending($customer, $by, true);
            } elseif (!empty($data['next_followup_date'])) {
                $this->schedule($customer, $data['next_followup_date'], null, $by);
            } else {
                // keep followup_date in sync with the next still-pending follow-up (if any)
                $next = $customer->followups()->where('status', 'pending')->orderBy('scheduled_for')->first();
                $customer->update(['followup_date' => $next?->scheduled_for?->format('Y-m-d')]);
            }

            return $followup->fresh(['customer.executive', 'resolver']);
        });
    }

    /** Follow-ups visible to this user (executives only see their own leads). */
    public function queryFor(LeadUser $user): Builder
    {
        $q = LeadFollowup::query()->with(['customer.executive']);
        if ($user->role === 'sales_executive') {
            $q->whereHas('customer', fn ($c) => $c->where('executive_id', $user->id));
        }
        return $q;
    }

    /** Counts for the banner / badge. */
    public function summary(LeadUser $user): array
    {
        $today = today()->toDateString();
        $pending = fn () => $this->queryFor($user)->where('status', 'pending');

        return [
            'overdue'   => $pending()->whereDate('scheduled_for', '<', $today)->count(),
            'due_today' => $pending()->whereDate('scheduled_for', $today)->count(),
            'upcoming'  => $pending()->whereDate('scheduled_for', '>', $today)
                ->whereDate('scheduled_for', '<=', today()->addDays(7)->toDateString())->count(),
        ];
    }
}
