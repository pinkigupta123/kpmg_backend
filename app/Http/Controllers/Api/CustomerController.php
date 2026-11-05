<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Http\Request;
use App\Services\AuditService;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();
        
        $user = $request->user();
        if ($user && $user->role !== 'super_admin') {
            $query->whereIn('branch_id', $user->allBranchIds());
            if ($request->filled('branch_id') && $user->canAccessBranch($request->branch_id)) {
                $query->where('branch_id', $request->branch_id);
            }
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $page = (int) ($request->page ?? 1);
        $limit = min((int) ($request->limit ?? 50), 500);
        
        $total = clone $query;
        $totalCount = $total->count();
        
        $customers = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
            'total' => $totalCount,
            'page' => (int) $page,
            'limit' => (int) $limit,
            'hasMore' => ($page * $limit) < $totalCount,
            'message' => 'Customers retrieved',
        ]);
    }

    public function show($id)
    {
        $customer = Customer::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer retrieved',
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $customer = Customer::create($data);
        AuditService::log('create', 'customer', $customer->id, Customer::class);

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer created successfully',
        ], 201);
    }

    public function update(StoreCustomerRequest $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->update($request->validated());
        
        AuditService::log('update', 'customer', $customer->id, Customer::class);

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer updated successfully',
        ]);
    }

    public function history($id)
    {
        $devices = Device::where('customer_id', $id)->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $devices,
            'message' => 'Customer device history retrieved',
        ]);
    }
}
