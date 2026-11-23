<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The Order Confirmation form of a lead that became a sale. */
class LeadOrder extends Model
{
    protected $table = 'lead_orders';

    protected $fillable = [
        'customer_id', 'order_no', 'order_date', 'name', 'address', 'mobile', 'email', 'gst_no',
        'items', 'total', 'payments', 'offers', 'sales_person', 'store', 'cash_counter', 'verified_by', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'items' => 'array',
            'payments' => 'array',
            'offers' => 'array',
            'total' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LeadCustomer::class, 'customer_id');
    }
}
