<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'roles' => $this->whenLoaded('roles', fn () =>
                $this->roles->map(fn ($role) => [
                    'id'   => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])
            ),

            // ── Active Subscription ──
            'active_subscription' => $this->whenLoaded('subscriptions', function () {
                $sub = $this->subscriptions
                    ->whereIn('status', ['active', 'trialing'])
                    ->sortByDesc('created_at')
                    ->first();

                if (! $sub) {
                    return null;
                }

                return [
                    'id'                => $sub->id,
                    'plan'              => $sub->relationLoaded('plan') ? [
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
                    'trial_ends_at'     => $sub->trial_ends_at?->toIso8601String(),
                    'auto_renew'        => $sub->auto_renew,
                ];
            }),

            // ── Aggregated Stats (only when requested) ──
            'stats' => $this->when($this->relationLoaded('aiRequests'), fn () => [
                'ai_requests_count'      => $this->aiRequests->count(),
                'generated_images_count' => $this->generatedImages->count(),
                'total_credits_used'     => $this->usageLogs->sum('credits_used'),
            ]),
        ];
    }
}
