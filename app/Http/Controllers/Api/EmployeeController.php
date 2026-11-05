<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Employee;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditService;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['branch', 'branches']);
        
        $user = $request->user();
        // an employee belongs to a branch if it is their primary branch OR one of their extra assignments
        $inBranches = fn ($ids) => fn ($q) => $q->whereIn('branch_id', $ids)->orWhereHas('branches', fn ($b) => $b->whereIn('branches.id', $ids));
        if ($user->role !== 'super_admin') {
            $query->where($inBranches($user->allBranchIds()));
        }

        if ($request->filled('branch_id')) {
            $query->where($inBranches([(int) $request->branch_id]));
        }

        $employees = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
            'message' => 'Employees retrieved',
        ]);
    }

    public function show($id)
    {
        $employee = Employee::with(['branch', 'branches'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $employee,
            'message' => 'Employee retrieved',
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();
        
        $branch = Branch::findOrFail($data['branch_id']);
        $data['branch_name'] = $branch->name;
        $data['company_id'] = $branch->company_id;
        
        $password = $request->password ?? 'User@123';
        $data['password'] = Hash::make($password);
        $data['joined_at'] = now();

        unset($data['branch_ids']);
        $employee = Employee::create($data);
        $employee->branches()->sync($this->branchSet($request, $data['branch_id']));
        
        $branch->increment('total_employees');
        
        AuditService::log('create', 'employee', $employee->id, Employee::class);

        return response()->json([
            'success' => true,
            'data' => $employee->load(['branch', 'branches']),
            'message' => 'Employee created successfully',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        
        // Custom validation logic because of unique constraint ignoring self
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15|unique:employees,mobile,' . $id,
            'email' => 'required|email|max:255|unique:employees,email,' . $id,
            'designation' => 'required|string|max:255',
            'role' => 'required|in:super_admin,branch_manager,employee,stock_manager',
        ]);

        $data = $request->all();

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['must_change_password'] = true;
        } else {
            unset($data['password']);
        }

        if (isset($data['branch_id']) && $data['branch_id'] != $employee->branch_id) {
            $branch = Branch::findOrFail($data['branch_id']);
            $data['branch_name'] = $branch->name;
            
            // Update branch employee counts
            Branch::find($employee->branch_id)->decrement('total_employees');
            $branch->increment('total_employees');
        }

        unset($data['branch_ids'], $data['branches']);
        $employee->update($data);
        if ($request->has('branch_ids') || isset($data['branch_id'])) {
            $employee->branches()->sync($this->branchSet($request, $employee->branch_id));
        }
        AuditService::log('update', 'employee', $employee->id, Employee::class);

        return response()->json([
            'success' => true,
            'data' => $employee->load(['branch', 'branches']),
            'message' => 'Employee updated successfully',
        ]);
    }

    /** Primary branch + any extra branches; a branch manager may only hand out branches they themselves have. */
    private function branchSet(Request $request, $primary): array
    {
        $extra = collect($request->input('branch_ids', []))->map(fn ($i) => (int) $i);
        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $extra = $extra->filter(fn ($i) => in_array($i, $user->allBranchIds(), true));
        }

        return $extra->push((int) $primary)->unique()->values()->all();
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);
        
        $employee = Employee::findOrFail($id);
        $employee->is_active = $request->is_active;
        $employee->save();

        // If deactivated, revoke all tokens
        if (!$employee->is_active) {
            $employee->tokens()->delete();
        }

        AuditService::log('update_status', 'employee', $employee->id, Employee::class, 'Status changed to ' . ($employee->is_active ? 'Active' : 'Inactive'));

        return response()->json([
            'success' => true,
            'data' => $employee,
            'message' => 'Employee status updated',
        ]);
    }
}
