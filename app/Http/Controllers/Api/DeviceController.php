<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Models\Device;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\JobNumberService;
use App\Services\AuditService;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::with(['customer', 'currentBranch', 'assignedTechnician', 'creator']);
        
        $user = $request->user();
        if ($user->role === 'branch_manager') {
            $query->where('current_branch_id', $user->branch_id);
        } elseif ($user->role === 'employee') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_technician_id', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        }

        if ($request->has('branch_id') && $user->role === 'super_admin') {
            $query->where('current_branch_id', $request->branch_id);
        }

        if ($request->has('technician_id')) {
            $query->where('assigned_technician_id', $request->technician_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_mobile', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%");
            });
        }

        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        
        $total = clone $query;
        $totalCount = $total->count();
        
        $devices = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
            'total' => $totalCount,
            'page' => (int) $page,
            'limit' => (int) $limit,
            'hasMore' => ($page * $limit) < $totalCount,
            'message' => 'Devices retrieved',
        ]);
    }

    public function show($id)
    {
        $device = Device::with(['customer', 'images', 'notes', 'statusHistory', 'stockUsages'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $device,
            'message' => 'Device retrieved',
        ]);
    }

    public function store(StoreDeviceRequest $request)
    {
        try {
            $data = $request->validated();
            
            $customer = Customer::findOrFail($data['customer_id']);
            $branch = Branch::findOrFail($data['branch_id']);
            
            $data['job_number'] = JobNumberService::generate();
            $data['receipt_number'] = JobNumberService::generateReceipt();
            $data['customer_name'] = $customer->name;
            $data['customer_mobile'] = $customer->mobile;
            $data['current_branch_id'] = $branch->id;
            $data['current_branch_name'] = $branch->name;
            $data['status'] = 'received';
            $data['received_at'] = now();
            $data['created_by'] = $request->user()->id;

            $device = Device::create($data);
            
            $customer->increment('total_devices');
            $branch->increment('active_repairs');

            // Handle images
            if (isset($data['device_images']) && is_array($data['device_images'])) {
                foreach ($data['device_images'] as $imgBase64) {
                    // In a real app, decode base64 and save to storage. Storing base64 string for now.
                    $device->images()->create([
                        'type' => 'device',
                        'image_path' => $imgBase64
                    ]);
                }
            }
            
            if (isset($data['condition_images']) && is_array($data['condition_images'])) {
                foreach ($data['condition_images'] as $imgBase64) {
                    $device->images()->create([
                        'type' => 'condition',
                        'image_path' => $imgBase64
                    ]);
                }
            }
            
            // Initial status history
            $device->statusHistory()->create([
                'status' => 'received',
                'description' => 'Device received at branch',
                'performed_by' => $request->user()->id,
                'performed_by_name' => $request->user()->name,
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
            ]);
            
            AuditService::log('create', 'device', $device->id, Device::class, "Job Number: {$device->job_number}");

            return response()->json([
                'success' => true,
                'data' => $device->load(['images', 'currentBranch', 'customer', 'creator']),
                'message' => 'Job created successfully',
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Device store error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create job: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(StoreDeviceRequest $request, $id)
    {
        $device = Device::findOrFail($id);
        $device->update($request->validated());
        
        AuditService::log('update', 'device', $device->id, Device::class);

        return response()->json([
            'success' => true,
            'data' => $device,
            'message' => 'Device updated successfully',
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string']);
        
        $device = Device::findOrFail($id);
        $oldStatus = $device->status;
        $newStatus = $request->status;
        $user = $request->user();
        
        if ($oldStatus === $newStatus) {
            return response()->json(['success' => true, 'data' => $device]);
        }

        $device->status = $newStatus;
        
        if ($newStatus === 'repair_completed') {
            $device->completed_at = now();
            if ($device->assigned_technician_id) {
                Employee::find($device->assigned_technician_id)->increment('completed_jobs');
            }
        } elseif ($newStatus === 'delivered') {
            $device->delivered_at = now();
        } elseif ($newStatus === 'closed') {
            $device->closed_at = now();
            Branch::find($device->current_branch_id)->decrement('active_repairs');
        }
        
        $device->save();

        $device->statusHistory()->create([
            'status' => $newStatus,
            'description' => "Status changed from {$oldStatus} to {$newStatus}",
            'performed_by' => $user->id,
            'performed_by_name' => $user->name,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch_name,
        ]);

        AuditService::log('update_status', 'device', $device->id, Device::class, $newStatus);

        return response()->json([
            'success' => true,
            'data' => $device,
            'message' => 'Device status updated',
        ]);
    }

    public function assignTechnician(Request $request, $id)
    {
        $request->validate(['technician_id' => 'required|exists:employees,id']);
        
        $device = Device::findOrFail($id);
        $technician = Employee::findOrFail($request->technician_id);
        
        $device->assigned_technician_id = $technician->id;
        $device->assigned_technician_name = $technician->name;
        $device->status = 'assigned';
        $device->assigned_at = now();
        $device->save();
        
        $technician->increment('assigned_devices');
        
        $user = $request->user();
        $device->statusHistory()->create([
            'status' => 'assigned',
            'description' => "Assigned to {$technician->name}",
            'performed_by' => $user->id,
            'performed_by_name' => $user->name,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch_name,
        ]);

        AuditService::log('assign_technician', 'device', $device->id, Device::class, "Assigned to {$technician->name}");

        return response()->json([
            'success' => true,
            'data' => $device,
            'message' => 'Technician assigned successfully',
        ]);
    }

    public function addNote(Request $request, $id)
    {
        $request->validate(['content' => 'required|string']);
        
        $device = Device::findOrFail($id);
        $user = $request->user();
        
        $note = $device->notes()->create([
            'author_id' => $user->id,
            'author_name' => $user->name,
            'role' => $user->role,
            'content' => $request->content,
        ]);

        AuditService::log('add_note', 'device', $device->id, Device::class);

        return response()->json([
            'success' => true,
            'data' => $device->load('notes'),
            'message' => 'Note added',
        ]);
    }
}
