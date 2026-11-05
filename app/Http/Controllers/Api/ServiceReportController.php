<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceReportRequest;
use App\Models\ServiceReport;
use App\Models\Device;
use Illuminate\Http\Request;
use App\Services\AuditService;

class ServiceReportController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceReport::query();
        
        $user = $request->user();
        if ($user->role !== 'super_admin') {
            $query->whereIn('branch_id', $user->allBranchIds());
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $reports,
            'message' => 'Service reports retrieved',
        ]);
    }

    public function show($id)
    {
        $report = ServiceReport::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Service report retrieved',
        ]);
    }

    public function store(StoreServiceReportRequest $request)
    {
        $data = $request->validated();
        
        $device = Device::findOrFail($data['device_id']);
        $user = $request->user();
        
        $data['job_number'] = $device->job_number;
        $data['customer_id'] = $device->customer_id;
        $data['customer_name'] = $device->customer_name;
        $data['customer_mobile'] = $device->customer_mobile;
        $data['device_type'] = $device->type;
        $data['brand'] = $device->brand;
        $data['model'] = $device->model;
        $data['serial_number'] = $device->serial_number;
        
        $data['created_by'] = $user->id;
        $data['branch_id'] = $user->branch_id;
        $data['branch_name'] = $user->branch_name;

        // Ensure we don't duplicate service report for a device
        $report = ServiceReport::updateOrCreate(
            ['device_id' => $device->id],
            $data
        );
        
        AuditService::log('create', 'repair', $report->id, ServiceReport::class, "Job Number: {$report->job_number}");

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Service report saved successfully',
        ], 201);
    }
}
