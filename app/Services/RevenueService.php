<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Revenue = what was actually charged (entered when the job was closed).
 * Cost    = purchase cost of the inventory parts used on that job.
 * Profit  = revenue - cost.
 * Only closed jobs that have a charged amount count.
 */
class RevenueService
{
    public static function base($user): Builder
    {
        $cost = DB::table('stock_usages as su')
            ->join('stock_items as si', 'si.id', '=', 'su.stock_item_id')
            ->select('su.device_id', DB::raw('SUM(su.quantity * COALESCE(su.unit_cost, si.unit_cost)) as parts_cost'))
            ->groupBy('su.device_id');

        $q = DB::table('devices as d')
            ->leftJoinSub($cost, 'uc', 'uc.device_id', '=', 'd.id')
            ->leftJoin('branches as b', 'b.id', '=', 'd.current_branch_id')
            ->leftJoin('companies as c', 'c.id', '=', 'd.company_id')
            ->where('d.status', 'closed')
            ->whereNotNull('d.final_amount');

        if ($user->role === 'branch_manager') {
            $q->whereIn('d.current_branch_id', $user->allBranchIds());
        } elseif ($user->role !== 'super_admin') {
            $q->where(fn ($w) => $w->where('d.assigned_technician_id', $user->id)->orWhere('d.created_by', $user->id));
        }

        return $q;
    }

    public static function aggregates(): array
    {
        return [
            DB::raw('COUNT(d.id) as jobs'),
            DB::raw('COALESCE(SUM(d.final_amount),0) as revenue'),
            DB::raw('COALESCE(SUM(uc.parts_cost),0) as cost'),
            DB::raw('COALESCE(SUM(d.final_amount - COALESCE(uc.parts_cost,0)),0) as profit'),
        ];
    }
}
