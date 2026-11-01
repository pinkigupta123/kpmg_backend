<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\StoreLeadCustomerRequest;
use App\Http\Requests\Lead\UpdateLeadCustomerRequest;
use App\Http\Resources\Lead\LeadCustomerResource;
use App\Services\Lead\LeadCustomerService;
use App\Models\LeadCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LeadCustomerController extends Controller
{
    protected LeadCustomerService $customerService;

    public function __construct(LeadCustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function index(Request $request): mixed
    {
        /** @var \App\Models\LeadUser $user */
        $user = $request->user();

        // CSV export
        if ($request->input('export') === 'csv' || $request->routeIs('lead.customers.export')) {
            if ($user->role !== 'super_admin') {
                abort(403, 'Only system administrators can export customer records.');
            }
            return $this->exportCSV($request);
        }

        $filters = $request->only(['search', 'status', 'sort', 'executive_id', 'branch']);
        $perPage  = (int) $request->input('per_page', 15);

        // Admin can filter by executive_id; sales executive always restricted to own leads
        if ($user->role === 'sales_executive') {
            unset($filters['executive_id']); // Service will enforce it
        }

        $customers = $this->customerService->paginate($user, $filters, $perPage);

        return LeadCustomerResource::collection($customers);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->customerService->find($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer lead not found.'], 404);
        }

        /** @var \App\Models\LeadUser $user */
        $user = $request->user();
        if ($user->role === 'sales_executive' && $customer->executive_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to customer record.'], 403);
        }

        return response()->json(new LeadCustomerResource($customer));
    }

    public function store(StoreLeadCustomerRequest $request): JsonResponse
    {
        /** @var \App\Models\LeadUser $user */
        $user   = $request->user();
        $result = $this->customerService->create($user, $request->validated());

        return response()->json([
            'duplicate' => $result['duplicate'],
            'customer'  => new LeadCustomerResource($result['customer']->load('executive')),
        ], $result['duplicate'] ? 200 : 201);
    }

    public function update(UpdateLeadCustomerRequest $request, int $id): JsonResponse
    {
        /** @var \App\Models\LeadUser $user */
        $user     = $request->user();
        $customer = $this->customerService->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer lead not found.'], 404);
        }

        if ($user->role === 'sales_executive' && $customer->executive_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to customer record.'], 403);
        }

        $updated = $this->customerService->update($user, $id, $request->validated());

        return response()->json(new LeadCustomerResource($updated->load('executive')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\LeadUser $user */
        $user = $request->user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Only system administrators can delete customer records.'], 403);
        }

        $deleted = $this->customerService->delete($id);
        if (!$deleted) {
            return response()->json(['message' => 'Customer lead not found.'], 404);
        }

        return response()->json(['message' => 'Customer lead deleted successfully.']);
    }

    public function search(Request $request): JsonResponse
    {
        /** @var \App\Models\LeadUser $user */
        $user      = $request->user();
        $filters   = $request->only(['search', 'status', 'sort']);
        $customers = $this->customerService->paginate($user, $filters, 20);

        return response()->json(LeadCustomerResource::collection($customers));
    }

    public function addFollowup(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'followup_date' => 'required|date_format:Y-m-d',
            'remarks'       => 'nullable|string',
            'status'        => 'nullable|string|in:new,contacted,follow-up,interested,purchased,closed',
        ]);

        /** @var \App\Models\LeadUser $user */
        $user = $request->user();

        try {
            $customer = $this->customerService->addFollowup($user, $id, $request->all());
            return response()->json(new LeadCustomerResource($customer->load(['executive', 'timelines.creator'])));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /** POST /lead/customers/{id}/activity — log what happened (note + optional status). */
    public function addActivity(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note'   => 'required|string|max:2000',
            'status' => 'nullable|string|in:new,contacted,follow-up,interested,purchased,closed',
        ]);

        /** @var \App\Models\LeadUser $user */
        $user     = $request->user();
        $customer = $this->customerService->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer lead not found.'], 404);
        }
        if ($user->role === 'sales_executive' && $customer->executive_id != $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $updated = $this->customerService->addActivity($user, $id, $data['note'], $data['status'] ?? null);

        return response()->json(new LeadCustomerResource($updated->load(['executive', 'timelines.creator', 'followups.resolver'])));
    }

    public function addTimelineEvent(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action'  => 'required|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        /** @var \App\Models\LeadUser $user */
        $user     = $request->user();
        $customer = $this->customerService->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        if ($user->role === 'sales_executive' && $customer->executive_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->customerService->addTimelineEvent($user, $id, $request->action, $request->remarks);

        return response()->json(['message' => 'Timeline event recorded successfully.']);
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        /** @var \App\Models\LeadUser $user */
        $user  = $request->user();
        $stats = $this->customerService->getDashboardStats($user);

        return response()->json($stats);
    }

    protected function exportCSV(Request $request): Response
    {
        $filters   = $request->only(['search', 'status', 'sort']);
        $customers = LeadCustomer::query()->with('executive');

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $customers->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $customers->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $sort = (!empty($filters['sort']) && strtolower($filters['sort']) === 'asc') ? 'asc' : 'desc';
        $customers->orderBy('created_at', $sort);
        $list = $customers->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lead_customers_' . date('Y-m-d') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($list) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID', 'Customer Name', 'Mobile Number', 'Email Address',
                'City', 'Company', 'Interested Product', 'Device Brand',
                'Device Model', 'Customer Query', 'Status', 'Follow-up Date',
                'Notes', 'Created By (Executive)', 'Created At',
            ]);

            foreach ($list as $cust) {
                fputcsv($file, [
                    $cust->id,
                    $cust->name,
                    $cust->mobile,
                    $cust->email              ?? 'N/A',
                    $cust->city               ?? 'N/A',
                    $cust->company            ?? 'N/A',
                    $cust->interested_product ?? 'N/A',
                    $cust->device_brand       ?? 'N/A',
                    $cust->device_model       ?? 'N/A',
                    $cust->customer_query     ?? 'N/A',
                    strtoupper($cust->status),
                    $cust->followup_date      ?? 'N/A',
                    $cust->notes              ?? 'N/A',
                    $cust->executive          ? $cust->executive->name : 'N/A',
                    $cust->created_at->toIso8601String(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
