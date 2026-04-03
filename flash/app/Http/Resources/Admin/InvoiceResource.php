<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'invoice_number'  => $this->invoice_number,
            'status'          => $this->status,
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount'      => (float) $this->tax_amount,
            'total'           => (float) $this->total,
            'currency'        => $this->currency,
            'issued_at'       => $this->issued_at?->toIso8601String(),
            'due_at'          => $this->due_at?->toIso8601String(),
            'paid_at'         => $this->paid_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'deleted_at'      => $this->deleted_at?->toIso8601String(),

            // ── User ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
            ]),

            // ── Payment (summary) ──
            'payment' => $this->whenLoaded('payment', fn () => $this->payment ? [
                'id'              => $this->payment->id,
                'uuid'            => $this->payment->uuid,
                'payment_gateway' => $this->payment->payment_gateway,
                'status'          => $this->payment->status,
                'amount'          => (float) $this->payment->amount,
            ] : null),

            // ── Subscription (summary) ──
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'id'     => $this->subscription->id,
                'status' => $this->subscription->status,
                'plan'   => $this->subscription->plan ? [
                    'id'   => $this->subscription->plan->id,
                    'name' => $this->subscription->plan->name,
                ] : null,
            ] : null),
        ];
    }
}
