<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\JobInvoice;
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
        $this->authorizeDevice($request, $device);

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

    /** Saved invoices of a job (at most one GST and one Non-GST), newest first. */
    public function index(Request $request, $deviceId)
    {
        $device = Device::with(['company', 'currentBranch.company'])->findOrFail($deviceId);
        $this->authorizeDevice($request, $device);

        $company = $device->company ?? $device->currentBranch?->company;
        $rows = JobInvoice::where('device_id', $device->id)->latest('updated_at')->get()
            ->map(fn ($i) => array_merge($i->toArray(), ['company' => $company]));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** Save (or overwrite the saved copy of) the invoice of this type for the job. */
    public function store(Request $request, $deviceId)
    {
        $device = Device::with(['company', 'currentBranch.company'])->findOrFail($deviceId);
        $this->authorizeDevice($request, $device);

        $data = $request->validate([
            'type' => 'required|in:gst,non_gst',
            'supply_type' => 'nullable|in:intra,inter',
            'invoice_number' => 'nullable|string|max:60',
            'invoice_date' => 'required|date',
            'buyer_name' => 'required|string|max:255',
            'buyer_mobile' => 'nullable|string|max:20',
            'buyer_address' => 'nullable|string',
            'buyer_gstin' => 'nullable|string|max:20',
            'buyer_state' => 'nullable|string|max:100',
            'place_of_supply' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric',
            'items.*.rate' => 'required|numeric',
            'taxable_total' => 'required|numeric',
            'cgst_total' => 'required|numeric',
            'sgst_total' => 'required|numeric',
            'igst_total' => 'required|numeric',
            'round_off' => 'required|numeric',
            'grand_total' => 'required|numeric',
        ]);

        $invoice = JobInvoice::updateOrCreate(
            ['device_id' => $device->id, 'type' => $data['type']],
            array_merge($data, ['job_number' => $device->job_number, 'supply_type' => $data['supply_type'] ?? 'intra', 'created_by' => $request->user()->id])
        );

        $company = $device->company ?? $device->currentBranch?->company;

        return response()->json(['success' => true, 'data' => array_merge($invoice->fresh()->toArray(), ['company' => $company])]);
    }

    private function authorizeDevice(Request $request, Device $device): void
    {
        $user = $request->user();
        abort_if(
            $user->role === 'branch_manager' && !$user->canAccessBranch($device->current_branch_id),
            403,
            'This job belongs to another branch.'
        );
        // technicians: only their own jobs (assigned to them or created by them), same rule as the job list
        abort_if(
            $user->role === 'employee'
                && (int) $device->assigned_technician_id !== (int) $user->id
                && (int) $device->created_by !== (int) $user->id,
            403,
            'You can only generate invoices for your own jobs.'
        );
    }
}
