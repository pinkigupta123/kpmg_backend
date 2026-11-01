<?php

namespace App\Services\Lead;

use App\Repositories\Lead\Interfaces\LeadCustomerRepositoryInterface;
use App\Repositories\Lead\Interfaces\LeadCustomerTimelineRepositoryInterface;
use App\Models\LeadCustomer;
use App\Models\LeadUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadCustomerService
{
    protected LeadCustomerRepositoryInterface $customerRepository;
    protected LeadCustomerTimelineRepositoryInterface $timelineRepository;

    public function __construct(
        LeadCustomerRepositoryInterface $customerRepository,
        LeadCustomerTimelineRepositoryInterface $timelineRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->timelineRepository = $timelineRepository;
    }

    public function paginate(LeadUser $currentUser, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if ($currentUser->role === 'sales_executive') {
            $filters['executive_id'] = $currentUser->id;
        }

        return $this->customerRepository->paginate($filters, $perPage);
    }

    public function find(int $id): ?LeadCustomer
    {
        return $this->customerRepository->find($id);
    }

    public function create(LeadUser $creator, array $data): array
    {
        // Check for duplicate mobile in the same branch
        $existingCustomer = $this->customerRepository->findByMobileAndBranch($data['mobile'], $creator->branch);
        if ($existingCustomer) {
            return [
                'duplicate' => true,
                'customer'  => $existingCustomer,
            ];
        }

        $data['executive_id'] = $creator->id;
        $customer = $this->customerRepository->create($data);

        $this->timelineRepository->create([
            'customer_id' => $customer->id,
            'action'      => 'Customer Created',
            'remarks'     => 'Enquiry registered for ' . ($customer->interested_product ?? 'laptop services'),
            'created_by'  => $creator->id,
        ]);

        return [
            'duplicate' => false,
            'customer'  => $customer,
        ];
    }

    public function update(LeadUser $editor, int $id, array $data): ?LeadCustomer
    {
        $oldCustomer = $this->customerRepository->find($id);
        if (!$oldCustomer) {
            return null;
        }

        $customer = $this->customerRepository->update($id, $data);
        if (!$customer) {
            return null;
        }

        if (isset($data['status']) && $oldCustomer->status !== $data['status']) {
            $this->timelineRepository->create([
                'customer_id' => $customer->id,
                'action'      => 'Status Updated',
                'remarks'     => 'Status updated from ' . strtoupper($oldCustomer->status) . ' to ' . strtoupper($customer->status),
                'created_by'  => $editor->id,
            ]);
        }

        // Follow-up history: scheduling / rescheduling / clearing the date is tracked per follow-up.
        $followups = app(LeadFollowupService::class);
        if (array_key_exists('followup_date', $data)) {
            $newDate = $data['followup_date'] ? substr((string) $data['followup_date'], 0, 10) : null;
            $oldDate = $oldCustomer->followup_date ? substr((string) $oldCustomer->followup_date, 0, 10) : null;
            if ($newDate !== $oldDate) {
                $fresh = LeadCustomer::find($customer->id);
                if ($newDate) {
                    $followups->schedule($fresh, $newDate, null, $editor);
                } else {
                    $followups->cancelPending($fresh, $editor, true);
                }
            }
        }

        // A closed / purchased lead has nothing left to follow up.
        if (in_array($customer->status, ['purchased', 'closed'], true)) {
            $followups->cancelPending(LeadCustomer::find($customer->id), $editor);
        }

        return $customer;
    }

    public function delete(int $id): bool
    {
        return $this->customerRepository->delete($id);
    }

    public function addFollowup(LeadUser $editor, int $id, array $data): LeadCustomer
    {
        $customer = $this->customerRepository->find($id);
        if (!$customer) {
            throw new \InvalidArgumentException('Customer not found.');
        }

        // creates the follow-up row (older pending ones become "rescheduled"), sets followup_date, logs the timeline
        $followups = app(LeadFollowupService::class);
        $followups->schedule($customer, $data['followup_date'], $data['remarks'] ?? null, $editor);

        $updateData = ['status' => $data['status'] ?? $customer->status];
        if (!empty($data['remarks'])) {
            $updateData['notes'] = $data['remarks'];
        }

        $updatedCustomer = $this->customerRepository->update($id, $updateData);

        if (isset($data['status']) && $customer->status !== $data['status']) {
            $this->timelineRepository->create([
                'customer_id' => $id,
                'action'      => 'Status Updated',
                'remarks'     => 'Status updated from ' . strtoupper($customer->status) . ' to ' . strtoupper($data['status']),
                'created_by'  => $editor->id,
            ]);
        }

        if (in_array($updatedCustomer->status, ['purchased', 'closed'], true)) {
            $followups->cancelPending(LeadCustomer::find($id), $editor, true);
        }

        return $this->customerRepository->find($id);
    }

    public function addTimelineEvent(LeadUser $creator, int $customerId, string $action, ?string $remarks = null): void
    {
        $this->timelineRepository->create([
            'customer_id' => $customerId,
            'action'      => $action,
            'remarks'     => $remarks,
            'created_by'  => $creator->id,
        ]);
    }

    public function getDashboardStats(LeadUser $user): array
    {
        $isAdmin = $user->role === 'super_admin';
        $conditions = [];
        if (!$isAdmin) {
            $conditions['executive_id'] = $user->id;
        }

        $totalCustomers   = $this->customerRepository->getCounts($conditions);
        $addedToday       = $this->customerRepository->getCounts(array_merge($conditions, ['created_today' => true]));
        $addedThisMonth   = $this->customerRepository->getCounts(array_merge($conditions, ['created_this_month' => true]));
        $recentFilters    = $isAdmin ? [] : ['executive_id' => $user->id];
        $recentCustomers  = $this->customerRepository->paginate($recentFilters, 5)->items();

        $stats = [
            'total_customers'  => $totalCustomers,
            'added_today'      => $addedToday,
            'added_this_month' => $addedThisMonth,
            'recent_customers' => $recentCustomers,
        ];

        if ($isAdmin) {
            $stats['total_executives'] = LeadUser::where('role', 'sales_executive')->count();
        } else {
            $stats['pending_followups'] = $this->customerRepository->getCounts(
                array_merge($conditions, ['pending_followup' => true])
            );
            $stats['upcoming_followups'] = LeadCustomer::where('executive_id', $user->id)
                ->whereNotNull('followup_date')
                ->whereDate('followup_date', '>=', today())
                ->orderBy('followup_date', 'asc')
                ->take(3)
                ->get();
        }

        return $stats;
    }
}
