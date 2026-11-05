<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $fillable = [
        'company_id', 'name', 'code', 'address', 'city', 'state', 'pincode',
        'phone', 'email', 'manager_id', 'manager_name', 'is_active',
        'total_employees', 'active_repairs',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'current_branch_id');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
