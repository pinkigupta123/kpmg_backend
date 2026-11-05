<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:255',
            'category' => 'required|string|max:255',
            'compatible_devices' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'location' => 'nullable|string|max:255',
            'warranty' => 'nullable|string|max:255',
            'condition' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            'slip_photo_base64' => 'nullable|string', // Assuming base64 for now
            'slip_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
        ];
    }
}
