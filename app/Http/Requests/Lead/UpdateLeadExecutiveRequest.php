<?php

namespace App\Http\Requests\Lead;

use App\Support\LeadPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadExecutiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\LeadUser $user */
        $user = auth('lead')->user();
        return $user && $user->role === 'super_admin';
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|unique:lead_users,email,' . $id,
            'branch'      => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in(LeadPermissions::keys())],
            'status'      => 'sometimes|string|in:active,inactive',
            'password'    => 'nullable|string|min:6',
        ];
    }
}
