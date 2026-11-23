<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $query = Device::query()->visibleTo($user);

        $totalDevices = (clone $query)->count();
        $pendingJobs = (clone $query)->whereNotIn('status', ['delivered', 'closed'])->count();
        $stockQuery = \App\Models\StockItem::query();
        if ($user->role !== 'super_admin') {
            $stockQuery->whereIn('branch_id', $user->allBranchIds());
        }
        $totalStockItems = $stockQuery->count();
        $activeRepairs = (clone $query)->whereIn('status', ['diagnosis_in_progress', 'repair_in_progress'])->count();
        $completedRepairs = (clone $query)->where('status', 'repair_completed')->count();
        $readyForDelivery = (clone $query)->where('status', 'ready_for_delivery')->count();

        // Admin sees total system stats
        $totalBranches = $user->role === 'super_admin' ? Branch::count() : count($user->allBranchIds());
        $totalEmployees = $user->role === 'super_admin' ? Employee::count() : Employee::where(fn ($q) => $q->whereIn('branch_id', $user->allBranchIds())->orWhereHas('branches', fn ($b) => $b->whereIn('branches.id', $user->allBranchIds())))->count();
        $totalCustomers = $user->role === 'super_admin' ? Customer::count() : Customer::whereIn('branch_id', $user->allBranchIds())->count();
        
        $transferQuery = Transfer::query();
        if ($user->role !== 'super_admin') {
            $transferQuery->where(function($q) use ($user) {
                $q->whereIn('source_branch_id', $user->allBranchIds())
                  ->orWhereIn('destination_branch_id', $user->allBranchIds());
            });
        }
        $devicesInTransfer = $transferQuery->where('status', 'in_transit')->count();

        $rev = \App\Services\RevenueService::base($user);
        $all = (clone $rev)->select(\App\Services\RevenueService::aggregates())->first();
        $month = (clone $rev)->whereMonth('d.closed_at', now()->month)->whereYear('d.closed_at', now()->year)
            ->select(\App\Services\RevenueService::aggregates())->first();

        return response()->json([
            'success' => true,
            'data' => [
                'revenueTotal' => (float) $all->revenue, 'costTotal' => (float) $all->cost, 'profitTotal' => (float) $all->profit,
                'revenueMonth' => (float) $month->revenue, 'costMonth' => (float) $month->cost, 'profitMonth' => (float) $month->profit,
                'closedJobs' => (int) $all->jobs,
                'totalBranches' => $totalBranches,
                'totalEmployees' => $totalEmployees,
                'totalCustomers' => $totalCustomers,
                'totalDevices' => $totalDevices,
                'pendingJobs' => $pendingJobs,
                'totalStockItems' => $totalStockItems,
                'activeRepairs' => $activeRepairs,
                'completedRepairs' => $completedRepairs,
                'devicesInTransfer' => $devicesInTransfer,
                'readyForDelivery' => $readyForDelivery,
            ],
            'message' => 'Dashboard stats retrieved',
        ]);
    }

    /** Jobs visible to this user, same scoping as stats(). */
    private function scopedDevices($user)
    {
        return \App\Models\Device::query()->visibleTo($user);
    }

    /**
     * Everything the dashboard's charts and lists need, in one call (role-scoped):
     * pipeline by status, 6-month intake and revenue trend, open-job ageing + oldest open jobs,
     * technician and branch workload, low-stock alerts, today's activity and turnaround time.
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $open = fn () => $this->scopedDevices($user)->whereNotIn('status', ['delivered', 'closed']);
        $recvCol = 'COALESCE(received_at, created_at)';

        // pipeline
        $byStatus = $this->scopedDevices($user)->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');

        // intake, last 6 months, by device type
        $from = now()->startOfMonth()->subMonths(5);
        $intakeRows = $this->scopedDevices($user)->where(DB::raw($recvCol), '>=', $from)
            ->select(DB::raw("DATE_FORMAT($recvCol,'%Y-%m') as ym"), 'type', DB::raw('COUNT(*) as n'))->groupBy('ym', 'type')->get();
        $intake = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $row = ['key' => $m->format('Y-m'), 'label' => $m->format('M'), 'laptops' => 0, 'desktops' => 0, 'printers' => 0, 'other' => 0];
            foreach ($intakeRows->where('ym', $row['key']) as $r) {
                $k = in_array($r->type, ['laptop', 'desktop', 'printer'], true) ? $r->type . 's' : 'other';
                $row[$k] += (int) $r->n;
            }
            $intake[] = $row;
        }

        // open jobs by age (days since received)
        $age = $open()->select(DB::raw(
            "SUM(CASE WHEN DATEDIFF(NOW(), $recvCol) <= 2 THEN 1 ELSE 0 END) as d0, "
            . "SUM(CASE WHEN DATEDIFF(NOW(), $recvCol) BETWEEN 3 AND 7 THEN 1 ELSE 0 END) as d3, "
            . "SUM(CASE WHEN DATEDIFF(NOW(), $recvCol) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as d8, "
            . "SUM(CASE WHEN DATEDIFF(NOW(), $recvCol) > 14 THEN 1 ELSE 0 END) as d15"
        ))->first();
        $ageBuckets = [
            ['label' => '0-2 days', 'value' => (int) ($age->d0 ?? 0)],
            ['label' => '3-7 days', 'value' => (int) ($age->d3 ?? 0)],
            ['label' => '8-14 days', 'value' => (int) ($age->d8 ?? 0)],
            ['label' => '15+ days', 'value' => (int) ($age->d15 ?? 0)],
        ];

        // oldest open jobs
        $oldest = $open()->select('id', 'job_number', 'brand', 'model', 'customer_name', 'status', 'assigned_technician_name', 'current_branch_name',
                DB::raw("$recvCol as received"), DB::raw("DATEDIFF(NOW(), $recvCol) as age_days"))
            ->orderBy(DB::raw($recvCol))->limit(6)->get();

        // latest jobs
        $recent = $this->scopedDevices($user)->select('id', 'job_number', 'brand', 'model', 'customer_name', 'status', 'assigned_technician_name', DB::raw("$recvCol as received"))
            ->orderByDesc(DB::raw($recvCol))->limit(6)->get();

        // technician + branch workload (admin / managers)
        $technicians = [];
        $branches = [];
        if (in_array($user->role, ['super_admin', 'branch_manager', 'stock_manager'], true)) {
            $technicians = $this->scopedDevices($user)->whereNotNull('assigned_technician_id')
                ->select('assigned_technician_id as id', 'assigned_technician_name as name',
                    DB::raw("SUM(CASE WHEN status NOT IN ('delivered','closed') THEN 1 ELSE 0 END) as open_jobs"),
                    DB::raw("SUM(CASE WHEN status IN ('repair_completed','ready_for_delivery','delivered','closed') AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as done_30d"))
                ->groupBy('assigned_technician_id', 'assigned_technician_name')->orderByDesc('open_jobs')->limit(8)->get();
            $branches = $this->scopedDevices($user)
                ->select('current_branch_name as name', DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status NOT IN ('delivered','closed') THEN 1 ELSE 0 END) as open_jobs"))
                ->groupBy('current_branch_name')->orderByDesc('total')->limit(8)->get();
        }

        // revenue / profit trend (not for technicians)
        $revenueTrend = [];
        if ($user->role !== 'employee') {
            $rows = \App\Services\RevenueService::base($user)->where('d.closed_at', '>=', $from)
                ->select(DB::raw("DATE_FORMAT(d.closed_at,'%Y-%m') as ym"))
                ->addSelect(\App\Services\RevenueService::aggregates())->groupBy('ym')->get()->keyBy('ym');
            for ($i = 5; $i >= 0; $i--) {
                $m = now()->startOfMonth()->subMonths($i);
                $r = $rows->get($m->format('Y-m'));
                $revenueTrend[] = ['key' => $m->format('Y-m'), 'label' => $m->format('M'),
                    'revenue' => (float) ($r->revenue ?? 0), 'cost' => (float) ($r->cost ?? 0), 'profit' => (float) ($r->profit ?? 0), 'jobs' => (int) ($r->jobs ?? 0)];
            }
        }

        // low-stock alerts
        $lowLimit = 5;
        $stock = \App\Models\StockItem::query();
        if ($user->role !== 'super_admin') {
            $stock->whereIn('branch_id', $user->allBranchIds());
        }
        $lowStock = (clone $stock)->where('quantity', '<=', $lowLimit)->orderBy('quantity')->limit(6)
            ->get(['id', 'item_code', 'name', 'quantity', 'unit', 'branch_name']);
        $lowStockCount = (clone $stock)->where('quantity', '<=', $lowLimit)->count();

        // today + turnaround + transfers
        $today = [
            'received' => $this->scopedDevices($user)->whereDate(DB::raw($recvCol), now()->toDateString())->count(),
            'delivered' => $this->scopedDevices($user)->whereDate('delivered_at', now()->toDateString())->count(),
            'completed' => $this->scopedDevices($user)->whereDate('completed_at', now()->toDateString())->count(),
        ];
        $turn = $this->scopedDevices($user)->whereIn('status', ['delivered', 'closed'])->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', now()->subDays(90))
            ->select(DB::raw("AVG(DATEDIFF(delivered_at, $recvCol)) as avg_days"), DB::raw('COUNT(*) as n'))->first();

        $transfers = \App\Models\Transfer::query();
        if ($user->role !== 'super_admin') {
            $transfers->where(fn ($q) => $q->whereIn('source_branch_id', $user->allBranchIds())->orWhereIn('destination_branch_id', $user->allBranchIds()));
        }

        return response()->json(['success' => true, 'data' => [
            'byStatus' => $byStatus,
            'intake' => $intake,
            'ageBuckets' => $ageBuckets,
            'oldestOpen' => $oldest,
            'recent' => $recent,
            'technicians' => $technicians,
            'branches' => $branches,
            'revenueTrend' => $revenueTrend,
            'lowStock' => $lowStock,
            'lowStockCount' => $lowStockCount,
            'lowStockLimit' => $lowLimit,
            'today' => $today,
            'avgTurnaroundDays' => $turn && $turn->n ? round((float) $turn->avg_days, 1) : null,
            'transfersPending' => (clone $transfers)->where('status', 'pending')->count(),
            'transfersInTransit' => (clone $transfers)->where('status', 'in_transit')->count(),
        ]]);
    }
}
