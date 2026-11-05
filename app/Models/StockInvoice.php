<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'invoice_date', 'supplier', 'branch_id', 'branch_name',
        'slip_photo_path', 'created_by',
    ];

    protected $casts = ['invoice_date' => 'date:Y-m-d'];

    public function items(): HasMany
    {
        return $this->hasMany(StockItem::class, 'invoice_id');
    }
}
