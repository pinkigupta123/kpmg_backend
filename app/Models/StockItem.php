<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    protected $fillable = [
        'item_code', 'name', 'part_number', 'category', 'compatible_devices',
        'brand', 'quantity', 'reorder_level', 'unit_cost', 'selling_price',
        'supplier', 'branch_id', 'branch_name', 'location', 'warranty',
        'condition', 'unit', 'slip_photo_path', 'slip_number', 'invoice_date', 'invoice_id', 'added_by',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'invoice_date' => 'date:Y-m-d',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'added_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(StockUsage::class);
    }
}
