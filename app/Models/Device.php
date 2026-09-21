<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $fillable = [
        'job_number', 'receipt_number', 'customer_id', 'customer_name', 'customer_mobile',
        'type', 'brand', 'model', 'serial_number', 'processor', 'ram', 'storage',
        'accessories', 'reported_issue', 'physical_condition', 'condition_checklist',
        'current_branch_id', 'current_branch_name', 'assigned_technician_id', 'assigned_technician_name',
        'status', 'call_type', 'service_type', 'call_reason', 'response_time', 'error_codes',
        'doi', 'device_password', 'customer_signature', 'employee_signature',
        'received_at', 'assigned_at', 'completed_at', 'delivered_at', 'closed_at', 'created_by',
    ];

    protected $casts = [
        'accessories' => 'array',
        'condition_checklist' => 'array',
        'received_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
        'doi' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(DeviceImage::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DeviceNote::class)->orderBy('created_at', 'desc');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeviceStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function serviceReport(): HasOne
    {
        return $this->hasOne(ServiceReport::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function stockUsages(): HasMany
    {
        return $this->hasMany(StockUsage::class);
    }
}
