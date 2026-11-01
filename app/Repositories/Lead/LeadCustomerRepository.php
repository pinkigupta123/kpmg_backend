<?php

namespace App\Repositories\Lead;

use App\Models\LeadCustomer;
use App\Repositories\Lead\Interfaces\LeadCustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadCustomerRepository implements LeadCustomerRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = LeadCustomer::query()->with('executive');

        if (!empty($filters['executive_id'])) {
            $query->where('executive_id', $filters['executive_id']);
        }

        // Branch filter: leads whose executive belongs to the given branch
        if (!empty($filters['branch'])) {
            $branch = $filters['branch'];
            $query->whereHas('executive', function ($q) use ($branch) {
                $q->where('branch', $branch);
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $sort = (!empty($filters['sort']) && strtolower($filters['sort']) === 'asc') ? 'asc' : 'desc';
        $query->orderBy('created_at', $sort);

        return $query->paginate($perPage);
    }

    public function find(int $id): ?LeadCustomer
    {
        return LeadCustomer::with(['executive', 'timelines.creator', 'followups.resolver'])->find($id);
    }

    public function findByMobile(string $mobile): ?LeadCustomer
    {
        return LeadCustomer::where('mobile', $mobile)->first();
    }

    public function findByMobileAndBranch(string $mobile, string $branch): ?LeadCustomer
    {
        return LeadCustomer::where('mobile', $mobile)
            ->whereHas('executive', function ($q) use ($branch) {
                $q->where('branch', $branch);
            })->first();
    }

    public function create(array $data): LeadCustomer
    {
        return LeadCustomer::create($data);
    }

    public function update(int $id, array $data): ?LeadCustomer
    {
        $customer = LeadCustomer::find($id);
        if ($customer) {
            $customer->update($data);
            return $this->find($id);
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $customer = LeadCustomer::find($id);
        if ($customer) {
            return $customer->delete();
        }
        return false;
    }

    public function getCounts(array $conditions = []): int
    {
        $query = LeadCustomer::query();

        if (isset($conditions['executive_id'])) {
            $query->where('executive_id', $conditions['executive_id']);
        }

        if (isset($conditions['created_today']) && $conditions['created_today'] === true) {
            $query->whereDate('created_at', today());
        }

        if (isset($conditions['created_this_month']) && $conditions['created_this_month'] === true) {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }

        if (isset($conditions['pending_followup']) && $conditions['pending_followup'] === true) {
            $query->whereIn('status', ['new', 'contacted', 'follow-up', 'interested'])
                  ->whereNotNull('followup_date')
                  ->whereDate('followup_date', '<=', today());
        }

        return $query->count();
    }
}
