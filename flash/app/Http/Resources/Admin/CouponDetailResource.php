<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'discount_type'     => $this->discount_type,
            'discount_value'    => (float) $this->discount_value,
            'currency'          => $this->currency,
            'is_active'         => (bool) $this->is_active,

            // ── Usage limits ──
            'max_uses'          => $this->max_uses,
            'max_uses_per_user' => $this->max_uses_per_user,
            'times_used'        => (int) $this->times_used,
            'usage_percentage'  => $this->max_uses
                ? round(($this->times_used / $this->max_uses) * 100, 1)
                : null,

            // ── Constraints ──
            'min_order_amount'  => $this->min_order_amount ? (float) $this->min_order_amount : null,
            'metadata'          => $this->metadata,

            // ── Validity ──
            'starts_at'         => $this->starts_at?->toIso8601String(),
            'expires_at'        => $this->expires_at?->toIso8601String(),
            'is_expired'        => $this->expires_at && $this->expires_at->isPast(),

            // ── Dates ──
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'deleted_at'        => $this->deleted_at?->toIso8601String(),

            // ── Plan restriction ──
            'applicable_plan' => $this->whenLoaded('applicablePlan', fn () => $this->applicablePlan ? [
                'id'   => $this->applicablePlan->id,
                'name' => $this->applicablePlan->name,
                'slug' => $this->applicablePlan->slug,
            ] : null),

            // ── Payments using this coupon ──
            'payments_count' => $this->whenCounted('payments'),
            'payments_total' => $this->when(
                isset($this->payments_sum_amount),
                fn () => (float) $this->payments_sum_amount
            ),
            'discount_given_total' => $this->when(
                isset($this->payments_sum_discount_amount),
                fn () => (float) $this->payments_sum_discount_amount
            ),
        ];
    }
}
