<?php

namespace Foundry\Http\Resources;

use Foundry\Http\Resources\Coupon\PlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'promotion_code' => $this->promotion_code,
            'duration' => $this->duration,
            'duration_in_months' => $this->duration_in_months,
            'discount_type' => $this->discount_type,
            'max_redemptions' => $this->max_redemptions,
            'value' => $this->value,
            'active' => $this->active,
            'auto_apply' => $this->auto_apply,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Appended attributes
            'has_max_redemptions' => $this->whenAppended('has_max_redemptions'),
            'specific_plans' => $this->whenAppended('specific_plans'),
            'has_expires_at' => $this->whenAppended('has_expires_at'),

            // Relationships
            'plans' => PlanResource::collection($this->whenLoaded('plans')),
            'logs' => $this->whenLoaded('logs'),
        ];
    }
}
