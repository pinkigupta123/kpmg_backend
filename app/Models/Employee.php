<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'company_id', 'name', 'code', 'mobile', 'email', 'password', 'designation',
        'branch_id', 'branch_name', 'role', 'is_active', 'must_change_password',
        'is_first_login', 'assigned_devices', 'completed_jobs', 'avatar_url', 'joined_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'is_first_login' => 'boolean',
        'joined_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // keep the primary branch inside the assignment set
        static::saved(function (Employee $e) {
            if ($e->wasRecentlyCreated || $e->wasChanged('branch_id')) {
                $e->branches()->syncWithoutDetaching([$e->branch_id]);
            }
        });
    }

    /** All branches this employee works in (primary branch always included). */
    public function branches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'employee_branch')->withTimestamps();
    }

    /** @return int[] ids of every branch this employee may work in / see data for */
    public function allBranchIds(): array
    {
        $ids = $this->relationLoaded('branches')
            ? $this->branches->pluck('id')->all()
            : $this->branches()->pluck('branches.id')->all();

        return array_values(array_unique(array_map('intval', array_merge([$this->branch_id], $ids))));
    }

    public function canAccessBranch($branchId): bool
    {
        return $this->role === 'super_admin' || in_array((int) $branchId, $this->allBranchIds(), true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedDevices(): HasMany
    {
        return $this->hasMany(Device::class, 'assigned_technician_id');
    }

    public function createdDevices(): HasMany
    {
        return $this->hasMany(Device::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
