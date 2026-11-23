<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Companies and branches for the Lead app. They are the SAME tables the KPNG Technology app uses, so anything
 * created here shows up there and vice versa. Super Admin only; no AuditService (its user_id points at employees).
 */
class LeadOrgController extends Controller
{
    private function admin(Request $r): void
    {
        abort_unless($r->user()->role === 'super_admin', 403, 'Only the Super Admin can manage companies and branches.');
    }

    private function companyRules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|alpha_num|unique:companies,code' . ($id ? ",{$id}" : ''),
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:10',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:12',
        ];
    }

    private function branchRules(?int $id = null): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:branches,code' . ($id ? ",{$id}" : ''),
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function companies(Request $r): JsonResponse
    {
        $this->admin($r);
        return response()->json(Company::withCount('branches')->orderBy('name')->get());
    }

    public function storeCompany(Request $r): JsonResponse
    {
        $this->admin($r);
        $data = $r->validate($this->companyRules());
        $data['code'] = strtoupper($data['code']);
        return response()->json(Company::create($data), 201);
    }

    public function updateCompany(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $c = Company::findOrFail($id);
        $data = $r->validate($this->companyRules($id));
        $data['code'] = strtoupper($data['code']);
        $c->update($data);
        return response()->json($c);
    }

    public function companyStatus(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $c = Company::findOrFail($id);
        $c->update(['is_active' => $r->validate(['is_active' => 'required|boolean'])['is_active']]);
        return response()->json($c);
    }

    public function branches(Request $r): JsonResponse
    {
        $this->admin($r);
        return response()->json(Branch::with('company:id,name,code')->orderBy('name')->get());
    }

    /** Blank address/contact fields fall back to the company's, like in KPNG Technology. */
    private function withDefaults(array $d): array
    {
        $company = Company::find($d['company_id']);
        foreach (['address', 'city', 'state', 'pincode', 'phone', 'email'] as $f) {
            if (blank($d[$f] ?? null) && $company) {
                $d[$f] = $company->{$f};
            }
        }
        return $d;
    }

    public function storeBranch(Request $r): JsonResponse
    {
        $this->admin($r);
        $d = $this->withDefaults($r->validate($this->branchRules()));
        if (blank($d['code'] ?? null)) {
            $company = Company::find($d['company_id']);
            $n = Branch::where('company_id', $company->id)->count() + 1;
            do {
                $code = sprintf('%s-%02d', strtoupper($company->code ?? 'BR'), $n++);
            } while (Branch::where('code', $code)->exists());
            $d['code'] = $code;
        }
        return response()->json(Branch::create($d)->load('company:id,name,code'), 201);
    }

    public function updateBranch(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $b = Branch::findOrFail($id);
        $d = $this->withDefaults($r->validate($this->branchRules($id)));
        if (blank($d['code'] ?? null)) {
            unset($d['code']);
        }
        $b->update($d);
        return response()->json($b->load('company:id,name,code'));
    }

    public function branchStatus(Request $r, int $id): JsonResponse
    {
        $this->admin($r);
        $b = Branch::findOrFail($id);
        $b->update(['is_active' => $r->validate(['is_active' => 'required|boolean'])['is_active']]);
        return response()->json($b);
    }
}
