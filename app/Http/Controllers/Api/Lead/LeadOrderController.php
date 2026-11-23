<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Controller;
use App\Models\LeadCustomer;
use App\Models\LeadOrder;
use App\Services\Lead\LeadCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Order Confirmation form. Saving it is what turns a lead into a confirmed sale: the lead's status becomes
 * "purchased" (pending follow-ups are cancelled and the timeline records it). One order per lead.
 */
class LeadOrderController extends Controller
{
    public const METHODS = ['cash', 'card', 'upi', 'imps', 'finance', 'cheque'];

    /** A sales executive only works with their own leads; a super admin with any. */
    private function lead(Request $request, int $id): LeadCustomer|JsonResponse
    {
        $customer = LeadCustomer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer lead not found.'], 404);
        }
        $user = $request->user();
        if ($user->role === 'sales_executive' && $customer->executive_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to customer record.'], 403);
        }

        return $customer;
    }

    /** GET /lead/customers/{id}/order, the saved order or `order: null`. */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->lead($request, $id);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        return response()->json(['order' => LeadOrder::where('customer_id', $customer->id)->first()]);
    }

    /** POST /lead/customers/{id}/order, create or update the order and mark the lead as confirmed (purchased). */
    public function store(Request $request, int $id): JsonResponse
    {
        $customer = $this->lead($request, $id);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $data = $request->validate([
            'order_date'             => ['required', 'date'],
            'name'                   => ['required', 'string', 'max:255'],
            'address'                => ['nullable', 'string', 'max:1000'],
            'mobile'                 => ['required', 'string', 'max:20'],
            'email'                  => ['nullable', 'email', 'max:255'],
            'gst_no'                 => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{15}$/'],
            'items'                  => ['required', 'array', 'min:1', 'max:50'],
            'items.*.particulars'    => ['required', 'string', 'max:500'],
            'items.*.unit'           => ['nullable', 'string', 'max:50'],
            'items.*.amount'         => ['required', 'numeric', 'min:0'],
            'payments'               => ['required', 'array', 'min:1', 'max:20'],
            'payments.*.method'      => ['required', Rule::in(self::METHODS)],
            'payments.*.provider'    => ['nullable', 'string', 'max:100'],
            'payments.*.amount'      => ['required', 'numeric', 'min:0'],
            'payments.*.cashback'    => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference'   => ['nullable', 'string', 'max:100'],
            'offers'                 => ['nullable', 'array', 'max:20'],
            'offers.*'               => ['string', 'max:150'],
            'sales_person'           => ['nullable', 'string', 'max:255'],
            'store'                  => ['nullable', 'string', 'max:255'],
            'cash_counter'           => ['nullable', 'string', 'max:255'],
            'verified_by'            => ['nullable', 'string', 'max:255'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ], ['gst_no.regex' => 'GST number must be 15 letters / digits.']);

        $data['gst_no'] = isset($data['gst_no']) ? strtoupper($data['gst_no']) : null;
        $data['total']  = round(collect($data['items'])->sum(fn ($i) => (float) $i['amount']), 2);

        $existing = LeadOrder::where('customer_id', $customer->id)->first();
        $order = $existing ?: new LeadOrder(['customer_id' => $customer->id, 'created_by' => $request->user()->id]);
        $order->fill($data)->save();
        if (!$order->order_no) {
            $order->update(['order_no' => 'OC-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)]);
        }

        // confirming the sale: status -> purchased (cancels pending follow-ups, writes the timeline)
        $note = ($existing ? 'Order updated' : 'Sale confirmed') . ' - ' . $order->order_no . ' - Rs ' . number_format($order->total, 2);
        app(LeadCustomerService::class)->addActivity(
            $request->user(), $customer->id, $note, $customer->status !== 'purchased' ? 'purchased' : null
        );

        return response()->json(['order' => $order->fresh()], $existing ? 200 : 201);
    }
}
