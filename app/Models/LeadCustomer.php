<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadCustomer extends Model
{
    use HasFactory;

    protected $table = 'lead_customers';

    protected $fillable = [
        'executive_id',
        'name',
        'mobile',
        'email',
        'city',
        'company',
        'lead_source',
        'interested_product',
        'device_brand',
        'device_model',
        'customer_query',
        'status',
        'followup_date',
        'notes',
    ];

    public function followups(): HasMany
    {
        return $this->hasMany(LeadFollowup::class, 'customer_id')->orderByRaw("status = 'pending' desc")->orderBy('id', 'desc');
    }

    /**
     * Get the sales executive who manages this lead.
     */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(LeadUser::class, 'executive_id');
    }

    /**
     * Get the interaction timeline for this customer.
     */
    public function timelines(): HasMany
    {
        return $this->hasMany(LeadCustomerTimeline::class, 'customer_id')->orderBy('created_at', 'desc');
    }
}
