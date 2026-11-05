<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadOption extends Model
{
    /** Dropdown groups the admin can manage. */
    public const TYPES = ['lead_source', 'designation'];

    protected $table = 'lead_options';

    protected $fillable = ['type', 'label', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
