<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AuditService;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    private function rules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|alpha_num|unique:companies,code' . ($id ? ",{$id}" : ''),
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'state_code' => 'nullable|string|size:2',
            'pincode' => 'required|string|max:10',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:12',
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Company::withCount('branches');
        if ($user->role !== 'super_admin') {
            $query->where('id', $user->company_id);
        }

        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get(), 'message' => 'Companies retrieved']);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        abort_if($user->role !== 'super_admin' && (int) $user->company_id !== (int) $id, 403);

        return response()->json(['success' => true, 'data' => Company::with('branches')->findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['code'] = strtoupper($data['code']);
        $company = Company::create($data);
        AuditService::log('create', 'company', $company->id, Company::class);

        return response()->json(['success' => true, 'data' => $company, 'message' => 'Company created successfully'], 201);
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        $data = $request->validate($this->rules((int) $id));
        $data['code'] = strtoupper($data['code']);
        $company->update($data);
        AuditService::log('update', 'company', $company->id, Company::class);

        return response()->json(['success' => true, 'data' => $company, 'message' => 'Company updated successfully']);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $company = Company::findOrFail($id);
        $company->update(['is_active' => $request->boolean('is_active')]);
        AuditService::log('update_status', 'company', $company->id, Company::class);

        return response()->json(['success' => true, 'data' => $company]);
    }
}
