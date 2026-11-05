<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:employees,code',
            'mobile' => 'required|string|max:15|unique:employees,mobile',
            'email' => 'required|email|max:255|unique:employees,email',
            'password' => 'nullable|string|min:6',
            'designation' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'role' => 'required|in:super_admin,branch_manager,employee,stock_manager',
            'avatar_url' => 'nullable|string',
        ];
    }
}
