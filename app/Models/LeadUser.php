<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadUser extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The table used by this model — fully isolated from employees/users.
     */
    protected $table = 'lead_users';

    protected $fillable = [
        'employee_id',
        'name',
        'mobile',
        'email',
        'password',
        'role',
        'branch',
        'designation',
        'permissions',
        'status',
        'is_temp_password',
        'temp_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'is_temp_password' => 'boolean',
            'permissions'      => 'array',
        ];
    }

    /**
     * Get customers managed by this Sales Executive.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(LeadCustomer::class, 'executive_id');
    }

    /**
     * Get timelines created by this user.
     */
    public function createdTimelines(): HasMany
    {
        return $this->hasMany(LeadCustomerTimeline::class, 'created_by');
    }

    /** Super admins implicitly hold every permission; executives need it granted. */
    public function hasPermission(string $permission): bool
    {
        return $this->role === 'super_admin'
            || in_array($permission, $this->permissions ?? [], true);
    }
}
