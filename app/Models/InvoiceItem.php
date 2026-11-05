<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'line_no', 'description', 'hsn_sac', 'quantity', 'unit', 'rate', 'gst_rate',
        'taxable_value', 'cgst', 'sgst', 'igst',
    ];
}
