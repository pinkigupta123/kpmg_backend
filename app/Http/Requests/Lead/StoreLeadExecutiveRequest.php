<?php

namespace App\Http\Requests\Lead;

use App\Support\LeadPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadExecutiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\LeadUser $user */
        $user = auth('lead')->user();
        return $user && $user->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'mobile'      => 'required|string|regex:/^[0-9]{10}$/|unique:lead_users,mobile',
            'email'       => 'nullable|email|unique:lead_users,email',
            'branch'      => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in(LeadPermissions::keys())],
            'password'    => 'nullable|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.unique' => 'An executive account with this mobile number already exists.',
            'mobile.regex'  => 'Mobile number must be a valid 10-digit number.',
        ];
    }
}
