<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query();
        
        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $query->whereIn('branch_id', $user->allBranchIds());
        }

        if ($request->has('module')) {
            $query->where('module', $request->module);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $page = $request->page ?? 1;
        $limit = $request->limit ?? 50;
        
        $total = clone $query;
        $totalCount = $total->count();
        
        $logs = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
            'total' => $totalCount,
            'page' => (int) $page,
            'limit' => (int) $limit,
            'hasMore' => ($page * $limit) < $totalCount,
            'message' => 'Audit logs retrieved',
        ]);
    }
}
