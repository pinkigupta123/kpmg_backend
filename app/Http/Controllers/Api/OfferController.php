<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Offers: Super Admin creates / edits them; admins and branch managers read them to send to customers.
 * Exactly one offer can be the default (pre-selected when sending). `?active=1` returns only offers that can be sent today.
 */
class OfferController extends Controller
{
    public function index(Request $request)
    {
        $q = Offer::query();

        $canSeeAll = in_array($request->user()->role, ['super_admin', 'branch_manager'], true);
        if ($request->boolean('active') || !$canSeeAll) {
            $today = now()->toDateString();
            $q->where('is_active', true)
                ->where(fn ($w) => $w->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($w) => $w->whereNull('valid_to')->orWhere('valid_to', '>=', $today));
        }

        return response()->json(['success' => true, 'data' => $q->orderByDesc('is_default')->orderByDesc('id')->get()]);
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'discount_text' => 'nullable|string|max:200',
            'discount_type' => 'nullable|in:amount,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'code' => 'nullable|string|max:40',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    private function checked(Request $request): array
    {
        $data = $request->validate($this->rules());
        if (empty($data['discount_value'])) {
            $data['discount_type'] = null;
            $data['discount_value'] = null;
        }
        abort_if(($data['discount_type'] ?? null) === 'percent' && $data['discount_value'] > 100, 422, 'A percentage discount cannot be more than 100%.');
        abort_if(($data['discount_value'] ?? null) && empty($data['discount_type']), 422, 'Choose whether the discount is an amount or a percentage.');

        return $data;
    }

    /** Only one default at a time; a default offer is always active. */
    private function applyDefault(Offer $offer): void
    {
        if ($offer->is_default) {
            if (!$offer->is_active) {
                $offer->update(['is_active' => true]);
            }
            Offer::where('id', '!=', $offer->id)->update(['is_default' => false]);
        }
    }

    public function store(Request $request)
    {
        $data = $this->checked($request);
        $offer = Offer::create($data + ['created_by' => $request->user()->id]);
        $this->applyDefault($offer);
        AuditService::log('create', 'offer', $offer->id, Offer::class, $offer->title);

        return response()->json(['success' => true, 'data' => $offer->fresh()], 201);
    }

    public function update(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);
        $data = $this->checked($request);
        $offer->update($data);
        $this->applyDefault($offer);
        AuditService::log('update', 'offer', $offer->id, Offer::class, $offer->title);

        return response()->json(['success' => true, 'data' => $offer->fresh()]);
    }

    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);
        $offer->delete();
        AuditService::log('delete', 'offer', (int) $id, Offer::class, $offer->title);

        return response()->json(['success' => true, 'message' => 'Offer deleted']);
    }
}
