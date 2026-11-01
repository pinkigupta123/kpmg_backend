<?php

namespace App\Console\Commands;

use App\Models\LeadFollowup;
use App\Models\LeadUser;
use App\Models\LeadUserDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Daily push reminder (Expo) for follow-ups that are due today or overdue.
 *
 *  - each sales executive gets a count for their own leads
 *  - each admin gets a digest of everything overdue / due today
 *
 * Scheduled in routes/console.php (needs `php artisan schedule:run` in cron).
 * Run manually with --dry-run to see who would be notified.
 */
class SendLeadFollowupReminders extends Command
{
    protected $signature   = 'lead:send-followup-reminders {--dry-run : only print, send nothing}';
    protected $description = 'Send push reminders for lead follow-ups that are due today or overdue';

    public function handle(): int
    {
        $today = today()->toDateString();

        $pending = LeadFollowup::query()
            ->with('customer:id,executive_id')
            ->where('status', 'pending')
            ->whereDate('scheduled_for', '<=', $today)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No follow-ups due.');
            return self::SUCCESS;
        }

        $messages = [];

        // per executive
        foreach ($pending->groupBy(fn ($f) => $f->customer?->executive_id) as $execId => $rows) {
            if (!$execId) {
                continue;
            }
            $user = LeadUser::find($execId);
            if (!$user || $user->status !== 'active') {
                continue;
            }
            [$dueToday, $overdue] = $this->split($rows, $today);
            $messages = array_merge($messages, $this->build($user, 'Follow-ups to do', $this->bodyFor($dueToday, $overdue)));
        }

        // admin digest
        [$allToday, $allOverdue] = $this->split($pending, $today);
        $admins = LeadUser::where('role', 'super_admin')->where('status', 'active')->get();
        foreach ($admins as $admin) {
            $messages = array_merge($messages, $this->build($admin, 'Follow-up digest', $this->bodyFor($allToday, $allOverdue, true)));
        }

        $this->info(count($messages) . ' notification(s) to send.');

        if ($this->option('dry-run')) {
            foreach ($messages as $m) {
                $this->line("  → {$m['to']}: {$m['title']} — {$m['body']}");
            }
            return self::SUCCESS;
        }

        foreach (array_chunk($messages, 100) as $chunk) {
            $this->send($chunk);
        }

        return self::SUCCESS;
    }

    private function split($rows, string $today): array
    {
        $dueToday = $rows->filter(fn ($f) => $f->scheduled_for->toDateString() === $today)->count();
        return [$dueToday, $rows->count() - $dueToday];
    }

    private function bodyFor(int $dueToday, int $overdue, bool $team = false): string
    {
        $parts = [];
        if ($dueToday) {
            $parts[] = "{$dueToday} due today";
        }
        if ($overdue) {
            $parts[] = "{$overdue} overdue";
        }
        return ($team ? 'Team: ' : 'You have ') . implode(', ', $parts) . '.';
    }

    private function build(LeadUser $user, string $title, string $body): array
    {
        return LeadUserDevice::where('lead_user_id', $user->id)->pluck('expo_token')->map(fn ($t) => [
            'to'    => $t,
            'title' => $title,
            'body'  => $body,
            'sound' => 'default',
            'data'  => ['screen' => 'Dashboard'],
        ])->all();
    }

    private function send(array $chunk): void
    {
        try {
            $response = Http::acceptJson()->timeout(20)->post('https://exp.host/--/api/v2/push/send', $chunk);
            $tickets  = $response->json('data') ?? [];

            // drop tokens Expo says are no longer valid
            foreach ($tickets as $i => $ticket) {
                if (($ticket['status'] ?? '') === 'error' && ($ticket['details']['error'] ?? '') === 'DeviceNotRegistered') {
                    LeadUserDevice::where('expo_token', $chunk[$i]['to'])->delete();
                }
            }
        } catch (\Throwable $e) {
            $this->error('Push send failed: ' . $e->getMessage());
        }
    }
}
