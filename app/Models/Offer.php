<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A promotional offer an admin creates; staff send it to customers on WhatsApp. */
class Offer extends Model
{
    protected $fillable = [
        'title', 'discount_text', 'discount_type', 'discount_value', 'description', 'code', 'valid_from', 'valid_to', 'is_active', 'is_default', 'created_by',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];
}
