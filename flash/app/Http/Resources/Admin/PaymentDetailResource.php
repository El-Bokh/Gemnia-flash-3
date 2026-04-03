<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'uuid'                 => $this->uuid,

            // ── Gateway ──
            'payment_gateway'      => $this->payment_gateway,
            'gateway_payment_id'   => $this->gateway_payment_id,
            'gateway_customer_id'  => $this->gateway_customer_id,
            'payment_method'       => $this->payment_method,

            // ── Status & Amounts ──
            'status'               => $this->status,
            'amount'               => (float) $this->amount,
            'discount_amount'      => (float) $this->discount_amount,
            'tax_amount'           => (float) $this->tax_amount,
            'net_amount'           => (float) $this->net_amount,
            'currency'             => $this->currency,
            'description'          => $this->description,

            // ── Refund ──
            'refunded_amount'      => (float) $this->refunded_amount,
            'refunded_at'          => $this->refunded_at?->toIso8601String(),
            'refund_reason'        => $this->refund_reason,

            // ── Billing ──
            'billing_name'         => $this->billing_name,
            'billing_email'        => $this->billing_email,
            'billing_address'      => $this->billing_address,
            'billing_city'         => $this->billing_city,
            'billing_state'        => $this->billing_state,
            'billing_zip'          => $this->billing_zip,
            'billing_country'      => $this->billing_country,

            // ── Payloads ──
            'gateway_response'     => $this->gateway_response,
            'metadata'             => $this->metadata,

            // ── Timestamps ──
            'paid_at'              => $this->paid_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
            'deleted_at'           => $this->deleted_at?->toIso8601String(),

            // ── User ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar,
                'status' => $this->user->status,
            ]),

            // ── Subscription ──
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'id'            => $this->subscription->id,
                'status'        => $this->subscription->status,
                'billing_cycle' => $this->subscription->billing_cycle,
                'starts_at'     => $this->subscription->starts_at?->toIso8601String(),
                'ends_at'       => $this->subscription->ends_at?->toIso8601String(),
                'plan'          => $this->subscription->plan ? [
                    'id'            => $this->subscription->plan->id,
                    'name'          => $this->subscription->plan->name,
                    'slug'          => $this->subscription->plan->slug,
                    'price_monthly' => (float) $this->subscription->plan->price_monthly,
                    'price_yearly'  => (float) $this->subscription->plan->price_yearly,
                ] : null,
            ] : null),

            // ── Coupon ──
            'coupon' => $this->whenLoaded('coupon', fn () => $this->coupon ? [
                'id'             => $this->coupon->id,
                'code'           => $this->coupon->code,
                'name'           => $this->coupon->name,
                'discount_type'  => $this->coupon->discount_type,
                'discount_value' => (float) $this->coupon->discount_value,
            ] : null),

            // ── Invoices ──
            'invoices' => $this->whenLoaded('invoices', fn () =>
                $this->invoices->map(fn ($inv) => [
                    'id'             => $inv->id,
                    'uuid'           => $inv->uuid,
                    'invoice_number' => $inv->invoice_number,
                    'status'         => $inv->status,
                    'total'          => (float) $inv->total,
                    'issued_at'      => $inv->issued_at?->toIso8601String(),
                    'paid_at'        => $inv->paid_at?->toIso8601String(),
                ])
            ),
        ];
    }
}
