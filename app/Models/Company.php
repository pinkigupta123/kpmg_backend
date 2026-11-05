<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name', 'code', 'address', 'city', 'state', 'state_code', 'pincode',
        'phone', 'email', 'gstin', 'pan', 'job_sequence', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
