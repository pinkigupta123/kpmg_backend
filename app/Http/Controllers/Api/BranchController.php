<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\AuditService;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::with(['manager', 'company']);
        
        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $query->whereIn('id', $user->allBranchIds());
        }

        $branches = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
            'message' => 'Branches retrieved',
        ]);
    }

    public function show($id)
    {
        $branch = Branch::with('manager')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Branch retrieved',
        ]);
    }

    /** Address, contact and email are optional on a branch - anything left blank defaults to the company's. */
    private function withCompanyDefaults(array $data): array
    {
        $company = Company::find($data['company_id'] ?? null);
        if ($company) {
            foreach (['address', 'city', 'state', 'pincode', 'phone', 'email'] as $field) {
                if (blank($data[$field] ?? null)) {
                    $data[$field] = $company->{$field};
                }
            }
        }

        return $data;
    }

    /** Next free branch code for a company, e.g. HTC-03 (editable by the user afterwards). */
    private function generateBranchCode(?Company $company): string
    {
        $prefix = strtoupper($company->code ?? 'BR');
        $n = Branch::where('company_id', $company?->id)->count() + 1;
        do {
            $code = sprintf('%s-%02d', $prefix, $n++);
        } while (Branch::where('code', $code)->exists());

        return $code;
    }

    public function store(StoreBranchRequest $request)
    {
        $data = $this->withCompanyDefaults($request->validated());
        if (blank($data['code'] ?? null)) {
            $data['code'] = $this->generateBranchCode(Company::find($data['company_id']));
        }
        
        if (!empty($data['manager_id'])) {
            $manager = Employee::find($data['manager_id']);
            if ($manager) {
                $data['manager_name'] = $manager->name;
            }
        }

        $branch = Branch::create($data);
        AuditService::log('create', 'branch', $branch->id, Branch::class);

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Branch created successfully',
        ], 201);
    }

    public function update(StoreBranchRequest $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $data = $this->withCompanyDefaults($request->validated());
        if (blank($data['code'] ?? null)) {
            unset($data['code']); // keep the existing code
        }

        if (!empty($data['manager_id']) && $data['manager_id'] != $branch->manager_id) {
            $manager = Employee::find($data['manager_id']);
            if ($manager) {
                $data['manager_name'] = $manager->name;
            }
        }

        $branch->update($data);
        AuditService::log('update', 'branch', $branch->id, Branch::class);

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Branch updated successfully',
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);
        
        $branch = Branch::findOrFail($id);
        $branch->is_active = $request->is_active;
        $branch->save();

        AuditService::log('update_status', 'branch', $branch->id, Branch::class, 'Status changed to ' . ($branch->is_active ? 'Active' : 'Inactive'));

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Branch status updated',
        ]);
    }
}
