<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch'); // Depending on your route parameter, usually 'branch' or 'id'
        
        // If route parameter is 'id' (as in /api/branches/{id})
        $id = $this->route('id') ?? $this->route('branch');

        return [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:branches,code,' . $id,
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:10',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'manager_id' => 'nullable|exists:employees,id',
        ];
    }
}
