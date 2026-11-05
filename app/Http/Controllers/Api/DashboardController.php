<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Transfer;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $query = Device::query();
        
        if ($user->role === 'branch_manager' || $user->role === 'stock_manager') {
            $query->whereIn('current_branch_id', $user->allBranchIds());
        } elseif ($user->role === 'employee') {
            $query->where('assigned_technician_id', $user->id);
        }

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
}
