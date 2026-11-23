<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A generated invoice saved against a job so it can be re-downloaded / re-shared with the same values. */
class JobInvoice extends Model
{
    protected $fillable = [
        'device_id', 'job_number', 'type', 'supply_type', 'invoice_number', 'invoice_date',
        'buyer_name', 'buyer_mobile', 'buyer_address', 'buyer_gstin', 'buyer_state', 'place_of_supply',
        'items', 'taxable_total', 'cgst_total', 'sgst_total', 'igst_total', 'round_off', 'grand_total', 'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'invoice_date' => 'date:Y-m-d',
        'taxable_total' => 'float', 'cgst_total' => 'float', 'sgst_total' => 'float',
        'igst_total' => 'float', 'round_off' => 'float', 'grand_total' => 'float',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
