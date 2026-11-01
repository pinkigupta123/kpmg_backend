<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadUserDevice extends Model
{
    protected $table = 'lead_user_devices';

    protected $fillable = ['lead_user_id', 'expo_token', 'platform'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(LeadUser::class, 'lead_user_id');
    }
}
