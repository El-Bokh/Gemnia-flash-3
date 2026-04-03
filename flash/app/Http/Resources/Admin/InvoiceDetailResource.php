<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'invoice_number'  => $this->invoice_number,
            'status'          => $this->status,

            // ── Amounts ──
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount'      => (float) $this->tax_amount,
            'total'           => (float) $this->total,
            'currency'        => $this->currency,

            // ── Billing ──
            'billing_name'    => $this->billing_name,
            'billing_email'   => $this->billing_email,
            'billing_address' => $this->billing_address,
            'billing_city'    => $this->billing_city,
            'billing_state'   => $this->billing_state,
            'billing_zip'     => $this->billing_zip,
            'billing_country' => $this->billing_country,

            // ── Content ──
            'line_items'      => $this->line_items,
            'notes'           => $this->notes,
            'footer'          => $this->footer,
            'metadata'        => $this->metadata,

            // ── Dates ──
            'issued_at'       => $this->issued_at?->toIso8601String(),
            'due_at'          => $this->due_at?->toIso8601String(),
            'paid_at'         => $this->paid_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
            'deleted_at'      => $this->deleted_at?->toIso8601String(),

            // ── User ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),

            // ── Payment ──
            'payment' => $this->whenLoaded('payment', fn () => $this->payment ? [
                'id'                  => $this->payment->id,
                'uuid'                => $this->payment->uuid,
                'payment_gateway'     => $this->payment->payment_gateway,
                'gateway_payment_id'  => $this->payment->gateway_payment_id,
                'status'              => $this->payment->status,
                'amount'              => (float) $this->payment->amount,
                'net_amount'          => (float) $this->payment->net_amount,
                'payment_method'      => $this->payment->payment_method,
                'paid_at'             => $this->payment->paid_at?->toIso8601String(),
            ] : null),

            // ── Subscription ──
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'id'            => $this->subscription->id,
                'status'        => $this->subscription->status,
                'billing_cycle' => $this->subscription->billing_cycle,
                'starts_at'     => $this->subscription->starts_at?->toIso8601String(),
                'ends_at'       => $this->subscription->ends_at?->toIso8601String(),
                'plan'          => $this->subscription->plan ? [
                    'id'   => $this->subscription->plan->id,
                    'name' => $this->subscription->plan->name,
                    'slug' => $this->subscription->plan->slug,
                ] : null,
            ] : null),
        ];
    }
}
