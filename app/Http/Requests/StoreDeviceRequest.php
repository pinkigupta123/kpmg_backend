<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:laptop,desktop,printer,networking,other',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'processor' => 'nullable|string|max:255',
            'ram' => 'nullable|string|max:255',
            'storage' => 'nullable|string|max:255',
            'accessories' => 'nullable|array',
            'reported_issue' => 'required|string',
            'physical_condition' => 'nullable|string',
            'condition_checklist' => 'nullable|array',
            'branch_id' => 'required|exists:branches,id',
            'call_type' => 'nullable|string|max:255',
            'service_type' => 'nullable|string|max:255',
            'call_reason' => 'nullable|string|max:255',
            'response_time' => 'nullable|string|max:255',
            'error_codes' => 'nullable|string|max:255',
            'device_password' => 'nullable|string|max:255',
            'doi' => 'nullable|date',
            'customer_signature' => 'nullable|string',
            'employee_signature' => 'nullable|string',
            'device_images' => 'nullable|array',
            'condition_images' => 'nullable|array',
        ];
    }
}
