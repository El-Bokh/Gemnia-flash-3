<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'avatar'            => $this->avatarUrl(),
            'status'            => $this->status,
            'locale'            => $this->locale,
            'timezone'          => $this->timezone,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at'     => $this->last_login_at?->toIso8601String(),
            'last_login_ip'     => $this->last_login_ip,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),

            // ── Roles ──
            'roles' => $this->roles->map(fn ($role) => [
                'id'   => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),

            // ── All Subscriptions ──
            'subscriptions' => $this->subscriptions
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($sub) => [
                    'id'                => $sub->id,
                    'plan'              => $sub->plan ? [
                        'id'   => $sub->plan->id,
                        'name' => $sub->plan->name,
                        'slug' => $sub->plan->slug,
                    ] : null,
                    'billing_cycle'     => $sub->billing_cycle,
                    'status'            => $sub->status,
                    'price'             => (float) $sub->price,
                    'currency'          => $sub->currency,
                    'credits_remaining' => $sub->credits_remaining,
                    'credits_total'     => $sub->credits_total,
                    'starts_at'         => $sub->starts_at?->toIso8601String(),
                    'ends_at'           => $sub->ends_at?->toIso8601String(),
                    'trial_starts_at'   => $sub->trial_starts_at?->toIso8601String(),
                    'trial_ends_at'     => $sub->trial_ends_at?->toIso8601String(),
                    'cancelled_at'      => $sub->cancelled_at?->toIso8601String(),
                    'auto_renew'        => $sub->auto_renew,
                    'created_at'        => $sub->created_at?->toIso8601String(),
                ]),

            // ── Aggregated Stats ──
            'stats' => [
                'ai_requests_total'     => $this->ai_requests_count ?? 0,
                'ai_requests_completed' => $this->ai_requests_completed_count ?? 0,
                'ai_requests_failed'    => $this->ai_requests_failed_count ?? 0,
                'ai_requests_pending'   => $this->ai_requests_pending_count ?? 0,
                'generated_images'      => ($this->output_images_count ?? 0) + ($this->generated_images_count ?? 0),
                'total_payments'        => (float) ($this->payments_total ?? 0),
                'payments_count'        => $this->payments_count ?? 0,
                'credit_balance'        => $this->credit_balance ?? 0,
            ],

            // ── Recent AI Requests (last 10) ──
            'recent_ai_requests' => $this->whenLoaded('aiRequests', fn () =>
                $this->aiRequests
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($req) => [
                        'id'         => $req->id,
                        'uuid'       => $req->uuid,
                        'type'       => $req->type,
                        'status'     => $req->status,
                        'prompt'     => $req->user_prompt,
                        'style'      => $req->visualStyle?->name,
                        'model'      => $req->model_used,
                        'credits'    => $req->credits_consumed,
                        'created_at' => $req->created_at?->toIso8601String(),
                    ])
            ),

            // ── Recent Generated Images (last 10) — from generated_images table ──
            'recent_generated_images' => $this->whenLoaded('generatedImages', fn () =>
                $this->generatedImages
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($img) => [
                        'id'         => $img->id,
                        'uuid'       => $img->uuid,
                        'file_path'  => $img->file_path,
                        'file_name'  => $img->file_name,
                        'width'      => $img->width,
                        'height'     => $img->height,
                        'file_size'  => $img->file_size,
                        'is_public'  => $img->is_public,
                        'is_nsfw'    => $img->is_nsfw,
                        'created_at' => $img->created_at?->toIso8601String(),
                    ])
            ),

            // ── Recent Output Images (last 10) — from media_files (chat-generated) ──
            'recent_output_images' => $this->whenLoaded('mediaFiles', fn () =>
                $this->mediaFiles
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($img) => [
                        'id'         => $img->id,
                        'file_path'  => $img->file_path,
                        'file_name'  => $img->file_name,
                        'mime_type'  => $img->mime_type,
                        'file_size'  => $img->file_size,
                        'created_at' => $img->created_at?->toIso8601String(),
                    ])
            ),

            // ── Recent Payments (last 10) ──
            'recent_payments' => $this->whenLoaded('payments', fn () =>
                $this->payments
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($p) => [
                        'id'         => $p->id,
                        'uuid'       => $p->uuid,
                        'amount'     => (float) $p->amount,
                        'net_amount' => (float) $p->net_amount,
                        'currency'   => $p->currency,
                        'status'     => $p->status,
                        'method'     => $p->payment_method,
                        'paid_at'    => $p->paid_at?->toIso8601String(),
                        'created_at' => $p->created_at?->toIso8601String(),
                    ])
            ),

            // ── Credit Ledger Summary ──
            'credit_ledger' => $this->whenLoaded('creditLedgers', fn () => [
                'balance' => $this->creditLedgers->sortByDesc('created_at')->first()?->balance_after ?? 0,
                'recent'  => $this->creditLedgers
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($entry) => [
                        'id'            => $entry->id,
                        'type'          => $entry->type,
                        'amount'        => $entry->amount,
                        'balance_after' => $entry->balance_after,
                        'source'        => $entry->source,
                        'description'   => $entry->description,
                        'created_at'    => $entry->created_at?->toIso8601String(),
                    ]),
            ]),
        ];
    }
}
