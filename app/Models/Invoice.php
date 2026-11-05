<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'type', 'supply_type', 'invoice_date', 'company_id', 'branch_id', 'device_id',
        'job_number', 'buyer_name', 'buyer_address', 'buyer_mobile', 'buyer_gstin', 'buyer_state',
        'place_of_supply', 'taxable_total', 'cgst_total', 'sgst_total', 'igst_total', 'round_off',
        'grand_total', 'created_by',
    ];

    protected $casts = ['invoice_date' => 'date:Y-m-d'];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_no');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
