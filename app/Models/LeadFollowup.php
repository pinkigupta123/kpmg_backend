<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowup extends Model
{
    protected $table = 'lead_followups';

    protected $fillable = [
        'customer_id',
        'scheduled_for',
        'status',
        'remarks',
        'outcome',
        'resolved_at',
        'resolved_by',
        'created_by',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'resolved_at'   => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(LeadCustomer::class, 'customer_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(LeadUser::class, 'resolved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LeadUser::class, 'created_by');
    }
}
