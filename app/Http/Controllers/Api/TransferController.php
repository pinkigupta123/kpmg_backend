<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Transfer;
use App\Models\Device;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Services\TransferNumberService;
use App\Services\AuditService;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $query = Transfer::query();
        
        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $query->where(function($q) use ($user) {
                $q->whereIn('source_branch_id', $user->allBranchIds())
                  ->orWhereIn('destination_branch_id', $user->allBranchIds());
            });
        }

        $transfers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $transfers,
            'message' => 'Transfers retrieved',
        ]);
    }

    public function show($id)
    {
        $transfer = Transfer::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $transfer,
            'message' => 'Transfer retrieved',
        ]);
    }

    public function store(StoreTransferRequest $request)
    {
        $data = $request->validated();
        
        $device = Device::findOrFail($data['device_id']);
        abort_if($device->status === 'closed', 422, 'This job is closed and locked - it cannot be reopened or changed.');
        $user = $request->user();
        $destBranch = Branch::findOrFail($data['destination_branch_id']);
        
        $data['transfer_number'] = TransferNumberService::generate();
        $data['source_branch_id'] = $user->branch_id;
        $data['source_branch_name'] = $user->branch_name;
        $data['destination_branch_name'] = $destBranch->name;
        $data['job_number'] = $device->job_number;
        $data['device_info'] = "{$device->brand} {$device->model}";
        $data['customer_id'] = $device->customer_id;
        $data['customer_name'] = $device->customer_name;
        $data['requested_by_id'] = $user->id;
        $data['requested_by_name'] = $user->name;
        $data['status'] = 'pending';
        $data['requested_at'] = now();

        $transfer = Transfer::create($data);
        
        $device->status = 'transfer_required';
        $device->save();
        
        $device->statusHistory()->create([
            'status' => 'transfer_required',
            'description' => "Transfer requested to {$destBranch->name}",
            'performed_by' => $user->id,
            'performed_by_name' => $user->name,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch_name,
        ]);
        
        AuditService::log('create', 'transfer', $transfer->id, Transfer::class, $transfer->transfer_number);

        return response()->json([
            'success' => true,
            'data' => $transfer,
            'message' => 'Transfer requested successfully',
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:approved,in_transit,received,cancelled']);
        
        $transfer = Transfer::findOrFail($id);
        $user = $request->user();
        $newStatus = $request->status;
        
        $transfer->status = $newStatus;
        
        if ($newStatus === 'approved') {
            $transfer->approved_at = now();
            $transfer->approved_by_id = $user->id;
            $transfer->approved_by_name = $user->name;
        } elseif ($newStatus === 'in_transit') {
            $transfer->dispatched_at = now();
        } elseif ($newStatus === 'received') {
            $transfer->received_at = now();
            $transfer->received_by_id = $user->id;
            $transfer->received_by_name = $user->name;
            
            // Update device branch
            $device = Device::find($transfer->device_id);
            if ($device) {
                // Remove from old branch stats, add to new
                Branch::find($device->current_branch_id)->decrement('active_repairs');
                Branch::find($transfer->destination_branch_id)->increment('active_repairs');
                
                $device->current_branch_id = $transfer->destination_branch_id;
                $device->current_branch_name = $transfer->destination_branch_name;
                $device->status = 'transferred';
                $device->save();
                
                $device->statusHistory()->create([
                    'status' => 'transferred',
                    'description' => "Transfer received at {$transfer->destination_branch_name}",
                    'performed_by' => $user->id,
                    'performed_by_name' => $user->name,
                    'branch_id' => $user->branch_id,
                    'branch_name' => $user->branch_name,
                ]);
            }
        } elseif ($newStatus === 'cancelled') {
            $transfer->cancelled_at = now();
            $device = Device::find($transfer->device_id);
            if ($device) {
                $device->status = 'repair_in_progress';
                $device->save();
            }
        }
        
        $transfer->save();

        AuditService::log('update_status', 'transfer', $transfer->id, Transfer::class, $newStatus);

        return response()->json([
            'success' => true,
            'data' => $transfer,
            'message' => 'Transfer status updated',
        ]);
    }
}
