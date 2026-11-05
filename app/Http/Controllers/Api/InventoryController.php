<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockItemRequest;
use App\Http\Requests\StoreStockUsageRequest;
use App\Models\StockItem;
use App\Models\StockUsage;
use App\Models\Device;
use App\Models\Branch;
use App\Models\StockInvoice;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\StockCodeService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = StockItem::query();
        
        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $query->whereIn('branch_id', $user->allBranchIds());
            if ($request->filled('branch_id') && $user->canAccessBranch($request->branch_id)) {
                $query->where('branch_id', $request->branch_id);
            }
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('part_number', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('compatible_devices', 'like', "%{$search}%");
            });
        }

        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        
        $total = clone $query;
        $totalCount = $total->count();
        
        $items = $query->orderBy('name', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $totalCount,
            'page' => (int) $page,
            'limit' => (int) $limit,
            'hasMore' => ($page * $limit) < $totalCount,
            'message' => 'Inventory retrieved',
        ]);
    }

    public function store(StoreStockItemRequest $request)
    {
        $data = $request->validated();
        
        $data['item_code'] = StockCodeService::generate();
        $user = $request->user();
        if (!$user->canAccessBranch($data['branch_id'] ?? 0)) {
            $data['branch_id'] = $user->branch_id; // stock is segregated by branch; fall back to the user's own
        }
        $branch = Branch::findOrFail($data['branch_id']);
        
        $data['branch_name'] = $branch->name;
        $data['added_by'] = $user->id;

        if (isset($data['slip_photo_base64'])) {
            $data['slip_photo_path'] = $this->saveBase64Image($data['slip_photo_base64']);
            unset($data['slip_photo_base64']);
        }

        $item = StockItem::create($data);
        StockTransaction::record($item, 'addition', (int) $item->quantity, $item->slip_number, 'Stock added', $user);

        AuditService::log('create', 'inventory', $item->id, StockItem::class, $item->name);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Stock item added successfully',
        ], 201);
    }

    public function update(StoreStockItemRequest $request, $id)
    {
        $item = StockItem::findOrFail($id);
        $data = $request->validated();
        $oldQty = (int) $item->quantity;
        
        if ($data['branch_id'] != $item->branch_id) {
            $branch = Branch::findOrFail($data['branch_id']);
            $data['branch_name'] = $branch->name;
        }
        
        if (isset($data['slip_photo_base64'])) {
            if ($item->slip_photo_path) {
                Storage::disk('public')->delete($item->slip_photo_path);
            }
            $data['slip_photo_path'] = $this->saveBase64Image($data['slip_photo_base64']);
            unset($data['slip_photo_base64']);
        }
        
        $item->update($data);
        $diff = (int) $item->quantity - $oldQty;
        StockTransaction::record($item, 'update', $diff, $item->slip_number, $diff ? "Quantity changed {$oldQty} -> {$item->quantity}" : 'Details updated', $request->user());

        AuditService::log('update', 'inventory', $item->id, StockItem::class);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Stock item updated successfully',
        ]);
    }

    public function usage(StoreStockUsageRequest $request)
    {
        $data = $request->validated();
        
        $item = StockItem::findOrFail($data['stock_item_id']);
        if ($item->quantity < $data['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock quantity',
            ], 400);
        }

        $device = Device::findOrFail($data['device_id']);
        $user = $request->user();

        $item->quantity -= $data['quantity'];
        $item->save();
        StockTransaction::record($item, 'usage', -(int) $data['quantity'], $device->job_number, 'Used on job', $user);

        $usage = StockUsage::create([
            'device_id' => $device->id,
            'job_number' => $device->job_number,
            'stock_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $data['quantity'],
            'selling_price' => $data['selling_price'],
            'unit_cost' => $item->unit_cost, // snapshot of our purchase cost, used for profit
            'added_by' => $user->id,
            'branch_id' => $user->branch_id,
            'used_at' => now(),
        ]);
        
        AuditService::log('usage', 'inventory', $usage->id, StockUsage::class, "Added to {$device->job_number}");

        return response()->json([
            'success' => true,
            'data' => [
                'usage' => $usage,
                'updatedItem' => $item
            ],
            'message' => 'Part added to job',
        ]);
    }

    /**
     * Add several items against one purchase invoice. Each row becomes its own stock line item,
     * all linked to the same invoice, in the branch selected on the invoice.
     */
    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'supplier' => 'nullable|string|max:255',
            'slip_photo_base64' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.category' => 'required|string|max:255',
            'items.*.part_number' => 'nullable|string|max:255',
            'items.*.brand' => 'nullable|string|max:255',
            'items.*.compatible_devices' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reorder_level' => 'nullable|integer|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.condition' => 'nullable|string|max:50',
            'items.*.warranty' => 'nullable|string|max:255',
            'items.*.location' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (!$user->canAccessBranch($data['branch_id'])) {
            $data['branch_id'] = $user->branch_id;
        }
        $branch = Branch::findOrFail($data['branch_id']);
        $slip = !empty($data['slip_photo_base64']) ? $this->saveBase64Image($data['slip_photo_base64']) : null;

        $invoice = DB::transaction(function () use ($data, $user, $branch, $slip) {
            $invoice = StockInvoice::create([
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'slip_photo_path' => $slip,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $row) {
                $item = StockItem::create($row + [
                    'item_code' => StockCodeService::generate(),
                    'supplier' => $data['supplier'] ?? null,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'slip_number' => $data['invoice_number'] ?? null,
                    'invoice_date' => $data['invoice_date'] ?? null,
                    'invoice_id' => $invoice->id,
                    'slip_photo_path' => $slip,
                    'added_by' => $user->id,
                ]);
                StockTransaction::record($item, 'addition', (int) $item->quantity, $invoice->invoice_number, 'Added via purchase invoice', $user);
            }
            return $invoice;
        });

        AuditService::log('create', 'inventory', $invoice->id, StockInvoice::class, 'Invoice ' . ($invoice->invoice_number ?? '#' . $invoice->id) . ' (' . count($data['items']) . ' items)');

        return response()->json([
            'success' => true,
            'data' => $invoice->load('items'),
            'message' => count($data['items']) . ' item(s) added against the invoice',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $item = StockItem::findOrFail($id);
        $user = $request->user();
        abort_unless($user->canAccessBranch($item->branch_id), 403);

        $item->setAttribute('transactions', StockTransaction::where('stock_item_id', $item->id)->orderByDesc('id')->limit(200)->get());
        $item->setAttribute('invoice', $item->invoice_id ? StockInvoice::with('items:id,invoice_id,item_code,name,quantity')->find($item->invoice_id) : null);

        return response()->json(['success' => true, 'data' => $item]);
    }

    /** Complete stock ledger with month-wise summary. Filters: month=YYYY-MM, branch_id, type, stock_item_id. */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $q = StockTransaction::query();
        if ($user->role !== 'super_admin') {
            $q->whereIn('branch_id', $user->allBranchIds());
            if ($request->filled('branch_id') && $user->canAccessBranch($request->branch_id)) {
                $q->where('branch_id', $request->branch_id);
            }
        } elseif ($request->filled('branch_id')) {
            $q->where('branch_id', $request->branch_id);
        }
        if ($request->filled('type')) $q->where('type', $request->type);
        if ($request->filled('stock_item_id')) $q->where('stock_item_id', $request->stock_item_id);

        $summary = (clone $q)
            ->select(
                DB::raw("DATE_FORMAT(created_at,'%Y-%m') as month"),
                DB::raw("SUM(CASE WHEN type='addition' THEN quantity_change ELSE 0 END) as added"),
                DB::raw("SUM(CASE WHEN type='usage' THEN -quantity_change ELSE 0 END) as used"),
                DB::raw("SUM(CASE WHEN type='update' THEN quantity_change ELSE 0 END) as adjusted"),
                DB::raw('COUNT(*) as entries')
            )
            ->groupBy(DB::raw("DATE_FORMAT(created_at,'%Y-%m')"))
            ->orderByDesc('month')->get();

        if ($request->filled('month')) {
            $q->where(DB::raw("DATE_FORMAT(created_at,'%Y-%m')"), $request->month);
        }

        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('id')->limit(1000)->get(),
            'summary' => $summary,
        ]);
    }

    public function usageByDevice($deviceId)
    {
        // Join with employees to get adder name
        $usages = StockUsage::where('device_id', $deviceId)
            ->join('employees', 'stock_usages.added_by', '=', 'employees.id')
            ->select('stock_usages.*', 'employees.name as addedBy')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $usages,
            'message' => 'Usage retrieved',
        ]);
    }

    public function history(Request $request)
    {
        $query = StockUsage::query()
            ->join('employees', 'stock_usages.added_by', '=', 'employees.id')
            ->select('stock_usages.*', 'employees.name as addedBy');

        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $query->whereIn('stock_usages.branch_id', $user->allBranchIds());
        }

        $history = $query->orderBy('used_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $history,
            'message' => 'Usage history retrieved',
        ]);
    }

    private function saveBase64Image($base64String)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \Exception('invalid image type');
            }
            
            $data = base64_decode($data);
            
            if ($data === false) {
                throw new \Exception('base64_decode failed');
            }
        } else {
            throw new \Exception('did not match data URI with image data');
        }

        $fileName = Str::uuid() . '.' . $type;
        $filePath = 'inventory_slips/' . $fileName;
        
        Storage::disk('public')->put($filePath, $data);
        
        return $filePath;
    }
}
