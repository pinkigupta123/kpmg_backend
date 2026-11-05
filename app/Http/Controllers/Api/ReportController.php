<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevenueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Revenue / cost / profit from closed jobs (revenue = amount actually charged at close, cost = parts used).
     * Visibility: super admin = all, branch manager = own branch, others = jobs they created or are assigned to.
     * Filters: from/to (close date), company_id, branch_id, user_id (engineer), creator_id (job creator), manager_id.
     * group_by: company | branch | user (engineer) | creator | manager | month | none
     */
    private function filtered(Request $request)
    {
        $q = RevenueService::base($request->user());

        if ($request->filled('from')) $q->whereDate('d.closed_at', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('d.closed_at', '<=', $request->to);
        if ($request->filled('company_id')) $q->where('d.company_id', $request->company_id);
        if ($request->filled('branch_id')) $q->where('d.current_branch_id', $request->branch_id);
        if ($request->filled('user_id')) $q->where('d.assigned_technician_id', $request->user_id);   // engineer who did the job
        if ($request->filled('creator_id')) $q->where('d.created_by', $request->creator_id);         // who created the job sheet
        if ($request->filled('manager_id')) $q->where('b.manager_id', $request->manager_id);

        return $q;
    }

    /** The closed jobs behind the revenue numbers, with who created them and who worked on them. */
    public function revenueJobs(Request $request)
    {
        $rows = $this->filtered($request)
            ->leftJoin('employees as ec', 'ec.id', '=', 'd.created_by')
            ->leftJoin('employees as et', 'et.id', '=', 'd.assigned_technician_id')
            ->select(
                'd.id', 'd.job_number', 'd.customer_name', 'd.brand', 'd.model', 'd.closed_at',
                'd.created_by', 'd.assigned_technician_id', 'd.final_amount_note',
                DB::raw('ec.name as created_by_name'), DB::raw('et.name as technician_name'), DB::raw('b.name as branch_name'),
                DB::raw('d.final_amount as revenue'),
                DB::raw('COALESCE(uc.parts_cost,0) as cost'),
                DB::raw('(d.final_amount - COALESCE(uc.parts_cost,0)) as profit')
            )
            ->orderByDesc('d.closed_at')
            ->limit(1000)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function revenue(Request $request)
    {
        $request->validate([
            'group_by' => 'nullable|in:none,company,branch,user,creator,manager,month',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);
        $groupBy = $request->input('group_by', 'branch');
        $q = $this->filtered($request);

        $agg = RevenueService::aggregates();
        $totals = (clone $q)->select($agg)->first();
        $rows = [];

        if ($groupBy !== 'none') {
            $g = clone $q;
            switch ($groupBy) {
                case 'company':
                    $g->select(DB::raw('c.id as `key`'), DB::raw("COALESCE(c.name,'Unassigned') as label"))->groupBy('c.id', 'c.name');
                    break;
                case 'branch':
                    $g->select(DB::raw('b.id as `key`'), DB::raw("COALESCE(b.name,'Unassigned') as label"))->groupBy('b.id', 'b.name');
                    break;
                case 'user':
                    $g->leftJoin('employees as e', 'e.id', '=', 'd.assigned_technician_id')
                        ->select(DB::raw('e.id as `key`'), DB::raw("COALESCE(e.name,'Unassigned') as label"))->groupBy('e.id', 'e.name');
                    break;
                case 'creator':
                    $g->leftJoin('employees as e', 'e.id', '=', 'd.created_by')
                        ->select(DB::raw('e.id as `key`'), DB::raw("COALESCE(e.name,'Unknown') as label"))->groupBy('e.id', 'e.name');
                    break;
                case 'manager':
                    $g->leftJoin('employees as e', 'e.id', '=', 'b.manager_id')
                        ->select(DB::raw('e.id as `key`'), DB::raw("COALESCE(e.name,'No manager') as label"))->groupBy('e.id', 'e.name');
                    break;
                case 'month':
                    $g->select(DB::raw("DATE_FORMAT(d.closed_at,'%Y-%m') as `key`"), DB::raw("DATE_FORMAT(d.closed_at,'%b %Y') as label"))
                        ->groupBy(DB::raw("DATE_FORMAT(d.closed_at,'%Y-%m')"), DB::raw("DATE_FORMAT(d.closed_at,'%b %Y')"));
                    break;
            }
            $rows = $g->addSelect($agg)->orderByDesc('revenue')->get();
        }

        return response()->json([
            'success' => true,
            'data' => ['groupBy' => $groupBy, 'rows' => $rows, 'totals' => $totals],
        ]);
    }
}
