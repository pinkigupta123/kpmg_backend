<?php

namespace App\Http\Resources\Lead;

use App\Support\LeadPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'employee_id'      => $this->employee_id,
            'name'             => $this->name,
            'mobile'           => $this->mobile,
            'email'            => $this->email,
            'role'             => $this->role,
            'branch'           => $this->branch,
            'designation'      => $this->designation,
            'permissions'      => $this->role === 'super_admin' ? LeadPermissions::keys() : ($this->permissions ?? []),
            'status'           => $this->status,
            'is_temp_password' => $this->is_temp_password,
            'temp_password'    => $this->temp_password,
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }
}
