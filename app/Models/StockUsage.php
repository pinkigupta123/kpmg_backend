<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockUsage extends Model
{
    protected $fillable = [
        'device_id', 'job_number', 'stock_item_id', 'item_name',
        'quantity', 'selling_price', 'unit_cost', 'added_by', 'branch_id', 'used_at',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'added_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
