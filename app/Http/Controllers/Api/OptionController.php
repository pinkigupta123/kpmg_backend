<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DropdownOption;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Centralised dropdown values. There is deliberately no delete: options are deactivated, so
 * historical records (which store the plain value) keep their original text.
 */
class OptionController extends Controller
{
    public const GROUPS = [
        'stock_category' => 'Inventory Categories',
        'stock_condition' => 'Stock Condition',
        'stock_unit' => 'Stock Units',
        'call_type' => 'Call Type',
        'service_type' => 'Service Type',
        'call_reason' => 'Call Reason',
        'response_time' => 'Response SLA',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $query = DropdownOption::query();

        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }
        // Only admins may see deactivated options (for the management screen)
        if (!($user->role === 'super_admin' && $request->boolean('include_inactive'))) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('group')->orderBy('sort_order')->orderBy('label')->get(),
            'groups' => self::GROUPS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group' => ['required', Rule::in(array_keys(self::GROUPS))],
            'label' => 'required|string|max:255',
            'value' => 'nullable|string|max:255',
        ]);
        $data['value'] = trim($data['value'] ?? '') !== '' ? trim($data['value']) : trim($data['label']);

        abort_if(
            DropdownOption::where('group', $data['group'])->where('value', $data['value'])->exists(),
            422,
            'This option already exists in the group.'
        );

        $data['sort_order'] = (int) DropdownOption::where('group', $data['group'])->max('sort_order') + 1;
        $option = DropdownOption::create($data);
        AuditService::log('create', 'option', $option->id, DropdownOption::class, "{$option->group}: {$option->label}");

        return response()->json(['success' => true, 'data' => $option], 201);
    }

    /** Edit the display label / order only. `value` is what records store, so it is immutable. */
    public function update(Request $request, $id)
    {
        $option = DropdownOption::findOrFail($id);
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $option->update($data);
        AuditService::log('update', 'option', $option->id, DropdownOption::class, "{$option->group}: {$option->label}");

        return response()->json(['success' => true, 'data' => $option]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $option = DropdownOption::findOrFail($id);
        $option->update(['is_active' => $request->boolean('is_active')]);
        AuditService::log($option->is_active ? 'activate' : 'deactivate', 'option', $option->id, DropdownOption::class, "{$option->group}: {$option->label}");

        return response()->json(['success' => true, 'data' => $option]);
    }
}
