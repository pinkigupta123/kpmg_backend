<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'email'             => 'nullable|email|max:255',
            'city'              => 'nullable|string|max:255',
            'company'           => 'nullable|string|max:255',
            'lead_source'       => 'nullable|string|max:100',
            'interested_product'=> 'nullable|string|max:255',
            'device_brand'      => 'nullable|string|max:255',
            'device_model'      => 'nullable|string|max:255',
            'customer_query'    => 'nullable|string',
            'status'            => 'required|string|in:new,contacted,follow-up,interested,purchased,closed',
            'followup_date'     => 'nullable|date_format:Y-m-d',
            'notes'             => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'followup_date.date_format' => 'Follow-up date must be in YYYY-MM-DD format.',
        ];
    }
}
