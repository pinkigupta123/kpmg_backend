<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Invoices are NOT stored. The browser builds the PDF at download time; this endpoint only supplies the
 * pre-filled lines (service charge + parts used) and buyer details for a job.
 */
class InvoiceController extends Controller
{
    public function draft(Request $request, $deviceId)
    {
        $device = Device::with(['customer', 'company', 'currentBranch.company', 'stockUsages'])->findOrFail($deviceId);
        $user = $request->user();
        abort_if(
            $user->role === 'branch_manager' && !$user->canAccessBranch($device->current_branch_id),
            403,
            'This job belongs to another branch.'
        );

        $company = $device->company ?? $device->currentBranch?->company;
        $customer = $device->customer;

        $items = [];
        foreach ($device->stockUsages as $u) {
            $items[] = [
                'description' => $u->item_name, 'hsn_sac' => '', 'quantity' => (float) $u->quantity,
                'unit' => 'Pc', 'rate' => (float) $u->selling_price, 'gst_rate' => 18,
            ];
        }
        $invoiceDate = now()->toDateString();
        if ($device->status === 'closed' && $device->final_amount !== null) {
            // Closed jobs are locked, so parts and the charged amount never change: the invoice is built from what was
            // entered at closing and always comes out the same. Total = exactly the amount charged.
            $invoiceDate = optional($device->closed_at)->toDateString() ?? $invoiceDate;
            $partsTotal = round(array_sum(array_map(fn ($i) => $i['quantity'] * $i['rate'], $items)), 2);
            $diff = round((float) $device->final_amount - $partsTotal, 2);
            if ($diff != 0) {
                array_unshift($items, [
                    'description' => $diff > 0 ? "Service / Repair Charges - {$device->brand} {$device->model}" : 'Discount / adjustment',
                    'hsn_sac' => $diff > 0 ? '998713' : '', 'quantity' => 1, 'unit' => 'Pc', 'rate' => $diff, 'gst_rate' => 18,
                ]);
            }
        } else {
            $service = (float) ($device->fixed_amount ?: $device->estimated_amount ?: 0);
            if ($service > 0) {
                array_unshift($items, [
                    'description' => "Service / Repair Charges - {$device->brand} {$device->model}",
                    'hsn_sac' => '998713', 'quantity' => 1, 'unit' => 'Pc', 'rate' => $service, 'gst_rate' => 18,
                ]);
            }
        }

        $buyerState = $customer?->state;
        $intra = !$company?->state || !$buyerState || strcasecmp(trim($company->state), trim($buyerState)) === 0;

        return response()->json(['success' => true, 'data' => [
            'device_id' => $device->id,
            'job_number' => $device->job_number,
            'type' => 'gst',
            'supply_type' => $intra ? 'intra' : 'inter',
            'invoice_date' => $invoiceDate,
            'buyer_name' => $device->customer_name,
            'buyer_mobile' => $device->customer_mobile,
            'buyer_address' => collect([$customer?->address, $customer?->city, $customer?->state, $customer?->pincode])->filter()->implode(', '),
            'buyer_state' => $buyerState,
            'buyer_gstin' => '',
            'place_of_supply' => $buyerState ?: $company?->state,
            'company' => $company,
            'items' => $items,
        ]]);
    }
}
