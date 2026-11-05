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

    /**
     * POST /lead/customers/bulk — add many leads at once (name + mobile + optional email).
     * Requires the bulk_add_leads permission (super admins always have it).
     * Rows are processed independently: valid ones are saved, the rest are reported back.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        /** @var \App\Models\LeadUser $user */
        $user = $request->user();
        if (!$user->hasPermission('bulk_add_leads')) {
            return response()->json(['message' => 'You do not have permission to add multiple leads.'], 403);
        }

        $request->validate([
            'customers'   => 'required|array|min:1|max:100',
            'lead_source' => 'nullable|string|max:100',
        ]);

        $created = 0;
        $duplicates = [];
        $failed = [];

        foreach ($request->input('customers') as $i => $row) {
            $row = is_array($row) ? $row : [];
            $validator = \Illuminate\Support\Facades\Validator::make($row, [
                'name'   => 'nullable|string|max:255',
                'mobile' => 'required|string|regex:/^[0-9]{10}$/',
                'email'  => 'nullable|email|max:255',
                'lead_source' => 'nullable|string|max:100',
            ], ['mobile.regex' => 'Mobile number must be a valid 10-digit number.']);

            if ($validator->fails()) {
                $failed[] = ['row' => $i + 1, 'mobile' => $row['mobile'] ?? null, 'error' => $validator->errors()->first()];
                continue;
            }

            $data = $validator->validated();
            // Name is optional in a bulk row; fall back to the number so the lead is still identifiable
            $data['name'] = trim((string) ($data['name'] ?? '')) !== '' ? $data['name'] : $data['mobile'];
            // each row carries its own source; the optional batch-level value is only a fallback
            if (empty($data['lead_source']) && $request->filled('lead_source')) {
                $data['lead_source'] = $request->input('lead_source');
            }

            $result = $this->customerService->create($user, $data);
            if ($result['duplicate']) {
                $duplicates[] = ['row' => $i + 1, 'mobile' => $data['mobile']];
            } else {
                $created++;
            }
        }

        return response()->json([
            'created'    => $created,
            'duplicates' => $duplicates,
            'failed'     => $failed,
        ]);
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

        if (!$user->hasPermission('delete_leads')) {
            return response()->json(['message' => 'You do not have permission to delete customer records.'], 403);
        }

        // Executives granted delete_leads may only delete their own leads
        if ($user->role === 'sales_executive') {
            $customer = $this->customerService->find($id);
            if ($customer && $customer->executive_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access to customer record.'], 403);
            }
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
            'followup_date' => 'required|date_format:Y-m-d|after_or_equal:yesterday',
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

    protected function exportCSV(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters   = $request->only(['search', 'status', 'sort', 'executive_id', 'branch']);
        $customers = LeadCustomer::query()->with('executive');

        if (!empty($filters['executive_id'])) {
            $customers->where('executive_id', $filters['executive_id']);
        }
        if (!empty($filters['branch'])) {
            $branch = $filters['branch'];
            $customers->whereHas('executive', fn ($q) => $q->where('branch', $branch));
        }

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
                'City', 'Company', 'Lead Source', 'Interested Product', 'Device Brand',
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
                    $cust->lead_source        ?? 'N/A',
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
