<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'executive_id'       => $this->executive_id,
            'name'               => $this->name,
            'mobile'             => $this->mobile,
            'email'              => $this->email,
            'city'               => $this->city,
            'company'            => $this->company,
            'lead_source'        => $this->lead_source,
            'interested_product' => $this->interested_product,
            'device_brand'       => $this->device_brand,
            'device_model'       => $this->device_model,
            'customer_query'     => $this->customer_query,
            'status'             => $this->status,
            'followup_date'      => $this->followup_date,
            'notes'              => $this->notes,
            'created_at'         => $this->created_at->toIso8601String(),
            'updated_at'         => $this->updated_at->toIso8601String(),
            'executive'          => new LeadUserResource($this->whenLoaded('executive')),
            'timelines'          => LeadCustomerTimelineResource::collection($this->whenLoaded('timelines')),
            'followups'          => LeadFollowupResource::collection($this->whenLoaded('followups')),
        ];
    }
}
