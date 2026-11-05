<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Device;
use App\Models\Invoice;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    private function scoped(Request $request)
    {
        $user = $request->user();
        $q = Invoice::with(['items', 'company', 'branch', 'creator']);
        if ($user->role === 'branch_manager') {
            $q->whereIn('branch_id', $user->allBranchIds());
        } elseif ($user->role !== 'super_admin') {
            $q->where('created_by', $user->id);
        }
        return $q;
    }

    public function index(Request $request)
    {
        $q = $this->scoped($request);
        if ($request->filled('type')) $q->where('type', $request->type);
        if ($request->filled('branch_id') && $request->user()->role === 'super_admin') $q->where('branch_id', $request->branch_id);
        if ($request->filled('from')) $q->whereDate('invoice_date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('invoice_date', '<=', $request->to);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('invoice_number', 'like', "%{$s}%")
                ->orWhere('buyer_name', 'like', "%{$s}%")->orWhere('job_number', 'like', "%{$s}%"));
        }

        return response()->json(['success' => true, 'data' => $q->orderByDesc('id')->limit(500)->get()]);
    }

    public function show(Request $request, $id)
    {
        return response()->json(['success' => true, 'data' => $this->scoped($request)->findOrFail($id)]);
    }

    /** Pre-filled invoice for a job: parts used + the service charge, buyer details, tax treatment. */
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
        $service = (float) ($device->fixed_amount ?: $device->estimated_amount ?: 0);
        if ($service > 0) {
            array_unshift($items, [
                'description' => "Service / Repair Charges - {$device->brand} {$device->model}",
                'hsn_sac' => '998713', 'quantity' => 1, 'unit' => 'Pc', 'rate' => $service, 'gst_rate' => 18,
            ]);
        }

        $buyerState = $customer?->state;
        $intra = !$company?->state || !$buyerState || strcasecmp(trim($company->state), trim($buyerState)) === 0;

        return response()->json(['success' => true, 'data' => [
            'device_id' => $device->id,
            'job_number' => $device->job_number,
            'type' => 'gst',
            'supply_type' => $intra ? 'intra' : 'inter',
            'invoice_date' => now()->toDateString(),
            'buyer_name' => $device->customer_name,
            'buyer_mobile' => $device->customer_mobile,
            'buyer_address' => collect([$customer?->address, $customer?->city, $customer?->state, $customer?->pincode])->filter()->implode(', '),
            'buyer_state' => $buyerState,
            'buyer_gstin' => '',
            'place_of_supply' => $buyerState ?: $company?->state,
            'items' => $items,
        ]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:gst,non_gst',
            'supply_type' => 'required|in:intra,inter',
            'invoice_date' => 'required|date',
            'device_id' => 'nullable|exists:devices,id',
            'buyer_name' => 'required|string|max:255',
            'buyer_address' => 'nullable|string',
            'buyer_mobile' => 'nullable|string|max:20',
            'buyer_gstin' => 'nullable|string|max:20',
            'buyer_state' => 'nullable|string|max:255',
            'place_of_supply' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.hsn_sac' => 'nullable|string|max:20',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:10',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $user = $request->user();
        $device = !empty($data['device_id']) ? Device::with('currentBranch')->findOrFail($data['device_id']) : null;
        if ($device) $this->authorizeDevice($request, $device);

        $branchId = $device?->current_branch_id ?? $user->branch_id;
        $company = Company::find($device?->company_id ?? $user->company_id ?? $device?->currentBranch?->company_id);
        abort_unless($company, 422, 'No company is mapped to this job / user.');

        [$lines, $totals] = $this->compute($data['items'], $data['type'], $data['supply_type']);

        $invoice = DB::transaction(function () use ($data, $company, $branchId, $device, $user, $lines, $totals) {
            $locked = Company::whereKey($company->id)->lockForUpdate()->first();
            $col = $data['type'] === 'gst' ? 'invoice_sequence' : 'nongst_invoice_sequence';
            $locked->$col = $locked->$col + 1;
            $locked->save();

            $fy = (int) date('n') >= 4 ? date('y') . '-' . ((int) date('y') + 1) : ((int) date('y') - 1) . '-' . date('y');
            $number = $data['type'] === 'gst'
                ? sprintf('%s/%05d/%s', $locked->code, $locked->$col, $fy)
                : sprintf('%s/NG/%05d/%s', $locked->code, $locked->$col, $fy);

            $invoice = Invoice::create([
                'invoice_number' => $number,
                'type' => $data['type'],
                'supply_type' => $data['supply_type'],
                'invoice_date' => $data['invoice_date'],
                'company_id' => $locked->id,
                'branch_id' => $branchId,
                'device_id' => $device?->id,
                'job_number' => $device?->job_number,
                'buyer_name' => $data['buyer_name'],
                'buyer_address' => $data['buyer_address'] ?? null,
                'buyer_mobile' => $data['buyer_mobile'] ?? null,
                'buyer_gstin' => $data['buyer_gstin'] ?? null,
                'buyer_state' => $data['buyer_state'] ?? null,
                'place_of_supply' => $data['place_of_supply'] ?? null,
                'created_by' => $user->id,
            ] + $totals);

            foreach ($lines as $i => $line) {
                $invoice->items()->create($line + ['line_no' => $i + 1]);
            }
            return $invoice;
        });

        AuditService::log('create', 'invoice', $invoice->id, Invoice::class, $invoice->invoice_number);

        return response()->json([
            'success' => true,
            'data' => $invoice->load(['items', 'company', 'branch', 'creator']),
            'message' => 'Invoice generated',
        ], 201);
    }

    private function authorizeDevice(Request $request, Device $device): void
    {
        $user = $request->user();
        abort_if(
            $user->role === 'branch_manager' && !$user->canAccessBranch($device->current_branch_id),
            403,
            'This job belongs to another branch.'
        );
    }

    /**
     * Rates on GST invoices are tax-inclusive (as on the client's sample): taxable = amount / (1 + gst%).
     * Intra-state splits tax into CGST + SGST; inter-state charges it all as IGST.
     */
    private function compute(array $items, string $type, string $supply): array
    {
        $lines = [];
        $taxable = $cgst = $sgst = $igst = 0.0;

        foreach ($items as $it) {
            $gross = round((float) $it['quantity'] * (float) $it['rate'], 2);
            $gst = $type === 'gst' ? (float) ($it['gst_rate'] ?? 18) : 0.0;
            $tv = $gst > 0 ? round($gross / (1 + $gst / 100), 2) : $gross;
            $tax = round($gross - $tv, 2);
            $c = $s = $i = 0.0;
            if ($gst > 0) {
                if ($supply === 'inter') {
                    $i = $tax;
                } else {
                    $c = round($tax / 2, 2);
                    $s = round($tax - $c, 2);
                }
            }
            $taxable += $tv; $cgst += $c; $sgst += $s; $igst += $i;

            $lines[] = [
                'description' => $it['description'],
                'hsn_sac' => $it['hsn_sac'] ?? null,
                'quantity' => $it['quantity'],
                'unit' => $it['unit'] ?? 'Pc',
                'rate' => $it['rate'],
                'gst_rate' => $gst,
                'taxable_value' => $tv,
                'cgst' => $c, 'sgst' => $s, 'igst' => $i,
            ];
        }

        if ($supply === 'intra') {
            // Split the invoice-level tax evenly (as on the sample) rather than summing per-line halves
            $totalTax = round($cgst + $sgst, 2);
            $cgst = round($totalTax / 2, 2);
            $sgst = round($totalTax - $cgst, 2);
        }

        $exact = $taxable + $cgst + $sgst + $igst;
        $grand = round($exact);

        return [$lines, [
            'taxable_total' => round($taxable, 2),
            'cgst_total' => round($cgst, 2),
            'sgst_total' => round($sgst, 2),
            'igst_total' => round($igst, 2),
            'round_off' => round($grand - $exact, 2),
            'grand_total' => $grand,
        ]];
    }
}
