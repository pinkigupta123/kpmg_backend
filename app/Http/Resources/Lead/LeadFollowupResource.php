<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadFollowupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduled = $this->scheduled_for->copy()->startOfDay();
        $today     = today();
        $pending   = $this->status === 'pending';

        // How the follow-up stands, for display:
        //   pending → "upcoming" | "due_today" | "overdue"; otherwise the stored status.
        $state = $this->status;
        $daysOverdue = 0;
        if ($pending) {
            if ($scheduled->lt($today)) {
                $state = 'overdue';
                $daysOverdue = (int) abs($scheduled->diffInDays($today));
            } elseif ($scheduled->eq($today)) {
                $state = 'due_today';
            } else {
                $state = 'upcoming';
            }
        }

        // For resolved rows: how many days after the scheduled date it was handled (0 = on time).
        $daysLate = 0;
        if (!$pending && $this->resolved_at) {
            $daysLate = max(0, (int) $scheduled->diffInDays($this->resolved_at->copy()->startOfDay(), false));
        }

        $customer = $this->whenLoaded('customer');

        return [
            'id'            => $this->id,
            'customer_id'   => $this->customer_id,
            'scheduled_for' => $this->scheduled_for->format('Y-m-d'),
            'status'        => $this->status,
            'state'         => $state,
            'days_overdue'  => $daysOverdue,
            'days_late'     => $daysLate,
            'remarks'       => $this->remarks,
            'outcome'       => $this->outcome,
            'resolved_at'   => $this->resolved_at?->toIso8601String(),
            'resolver'      => new LeadUserResource($this->whenLoaded('resolver')),
            'created_by'    => $this->created_by,
            'created_at'    => $this->created_at?->toIso8601String(),
            'customer'      => $customer instanceof \Illuminate\Http\Resources\MissingValue ? $customer : [
                'id'                 => $customer->id,
                'name'               => $customer->name,
                'mobile'             => $customer->mobile,
                'interested_product' => $customer->interested_product,
                'status'             => $customer->status,
                'executive_id'       => $customer->executive_id,
                'executive'          => $customer->relationLoaded('executive') && $customer->executive ? [
                    'id'     => $customer->executive->id,
                    'name'   => $customer->executive->name,
                    'branch' => $customer->executive->branch,
                ] : null,
            ],
        ];
    }
}
