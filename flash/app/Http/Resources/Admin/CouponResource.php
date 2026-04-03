<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'name'             => $this->name,
            'discount_type'    => $this->discount_type,
            'discount_value'   => (float) $this->discount_value,
            'currency'         => $this->currency,
            'is_active'        => (bool) $this->is_active,

            // ── Usage ──
            'max_uses'         => $this->max_uses,
            'max_uses_per_user'=> $this->max_uses_per_user,
            'times_used'       => (int) $this->times_used,
            'usage_percentage' => $this->max_uses
                ? round(($this->times_used / $this->max_uses) * 100, 1)
                : null,

            // ── Validity ──
            'starts_at'        => $this->starts_at?->toIso8601String(),
            'expires_at'       => $this->expires_at?->toIso8601String(),
            'is_expired'       => $this->expires_at && $this->expires_at->isPast(),

            // ── Plan restriction ──
            'applicable_plan' => $this->whenLoaded('applicablePlan', fn () => $this->applicablePlan ? [
                'id'   => $this->applicablePlan->id,
                'name' => $this->applicablePlan->name,
                'slug' => $this->applicablePlan->slug,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
