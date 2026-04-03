<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'uuid'                => $this->uuid,
            'payment_gateway'     => $this->payment_gateway,
            'gateway_payment_id'  => $this->gateway_payment_id,
            'status'              => $this->status,
            'amount'              => (float) $this->amount,
            'discount_amount'     => (float) $this->discount_amount,
            'tax_amount'          => (float) $this->tax_amount,
            'net_amount'          => (float) $this->net_amount,
            'currency'            => $this->currency,
            'payment_method'      => $this->payment_method,
            'description'         => $this->description,
            'refunded_amount'     => (float) $this->refunded_amount,
            'refunded_at'         => $this->refunded_at?->toIso8601String(),
            'paid_at'             => $this->paid_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
            'deleted_at'          => $this->deleted_at?->toIso8601String(),

            // ── User ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),

            // ── Subscription (summary) ──
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'id'     => $this->subscription->id,
                'status' => $this->subscription->status,
                'plan'   => $this->subscription->plan ? [
                    'id'   => $this->subscription->plan->id,
                    'name' => $this->subscription->plan->name,
                    'slug' => $this->subscription->plan->slug,
                ] : null,
            ] : null),

            // ── Coupon (summary) ──
            'coupon' => $this->whenLoaded('coupon', fn () => $this->coupon ? [
                'id'             => $this->coupon->id,
                'code'           => $this->coupon->code,
                'discount_type'  => $this->coupon->discount_type,
                'discount_value' => (float) $this->coupon->discount_value,
            ] : null),

            // ── Counts ──
            'invoices_count' => $this->whenCounted('invoices'),
        ];
    }
}
