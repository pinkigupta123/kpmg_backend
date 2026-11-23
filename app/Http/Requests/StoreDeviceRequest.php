<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The API's middleware stack does not turn empty form values into null, so a blank Fixed Amount arrived as ''
     * and the decimal column rejected it. Treat every blank value as "not provided".
     */
    protected function prepareForValidation(): void
    {
        $blank = [];
        foreach ($this->all() as $key => $value) {
            if ($value === '') {
                $blank[$key] = null;
            }
        }
        $this->merge($blank);
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:laptop,desktop,printer,networking,other',
            // brand, model and the reported problem are required for every device type except "other"
            'brand' => 'required_unless:type,other|nullable|string|max:255',
            'model' => 'required_unless:type,other|nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'processor' => 'nullable|string|max:255',
            'ram' => 'nullable|string|max:255',
            'storage' => 'nullable|string|max:255',
            'accessories' => 'nullable|array',
            'reported_issue' => 'required_unless:type,other|nullable|string',
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
            'device_user_id' => 'nullable|string|max:255',
            'fixed_amount' => 'nullable|numeric|min:0',
            'estimated_amount' => 'nullable|numeric|min:0',
            'estimate_remarks' => 'nullable|string',
            'receiving_address' => 'nullable|string',
            'assigned_technician_id' => 'nullable|exists:employees,id',
            'device_images' => 'nullable|array',
            'condition_images' => 'nullable|array',
        ];
    }
}
