<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Controller;
use App\Models\LeadOption;
use App\Support\LeadPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-managed dropdown values (lead source, designation, ...).
 * Everyone can read them; only a super admin can create / edit / delete.
 */
class LeadOptionController extends Controller
{
    /** GET /lead/options — grouped by type. Admins also get inactive entries (?all=1). */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->role === 'super_admin';
        $query   = LeadOption::query()->orderBy('sort_order')->orderBy('label');

        if (!($isAdmin && $request->boolean('all'))) {
            $query->where('is_active', true);
        }

        $grouped = array_fill_keys(LeadOption::TYPES, []);
        foreach ($query->get() as $opt) {
            $grouped[$opt->type][] = $opt;
        }

        return response()->json([
            'options'     => $grouped,
            'permissions' => LeadPermissions::catalog(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'type'  => ['required', Rule::in(LeadOption::TYPES)],
            'label' => ['required', 'string', 'max:100'],
        ]);
        $data['label'] = trim($data['label']);

        if (LeadOption::where('type', $data['type'])->where('label', $data['label'])->exists()) {
            return response()->json(['message' => 'This option already exists.', 'errors' => ['label' => ['This option already exists.']]], 422);
        }

        $data['sort_order'] = (int) LeadOption::where('type', $data['type'])->max('sort_order') + 1;

        return response()->json(LeadOption::create($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->requireAdmin($request);
        $option = LeadOption::find($id);
        if (!$option) {
            return response()->json(['message' => 'Option not found.'], 404);
        }

        $data = $request->validate([
            'label'     => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['label'])) {
            $data['label'] = trim($data['label']);
            $clash = LeadOption::where('type', $option->type)->where('label', $data['label'])->where('id', '!=', $id)->exists();
            if ($clash) {
                return response()->json(['message' => 'This option already exists.', 'errors' => ['label' => ['This option already exists.']]], 422);
            }
        }

        $option->update($data);

        return response()->json($option);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->requireAdmin($request);
        $option = LeadOption::find($id);
        if (!$option) {
            return response()->json(['message' => 'Option not found.'], 404);
        }
        // Existing leads / staff keep the text they were saved with; only the dropdown entry goes away.
        $option->delete();

        return response()->json(['message' => 'Option deleted.']);
    }

    protected function requireAdmin(Request $request): void
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403, 'Only system administrators can manage dropdown options.');
        }
    }
}
