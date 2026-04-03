<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'price_monthly'   => (float) $this->price_monthly,
            'price_yearly'    => (float) $this->price_yearly,
            'currency'        => $this->currency,
            'credits_monthly' => $this->credits_monthly,
            'credits_yearly'  => $this->credits_yearly,
            'is_free'         => $this->is_free,
            'is_active'       => $this->is_active,
            'is_featured'     => $this->is_featured,
            'sort_order'      => $this->sort_order,
            'trial_days'      => $this->trial_days,
            'metadata'        => $this->metadata,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
            'deleted_at'      => $this->deleted_at?->toIso8601String(),

            // ── Features with pivot limits (full detail) ──
            'features' => $this->features->map(fn ($f) => [
                'id'              => $f->id,
                'name'            => $f->name,
                'slug'            => $f->slug,
                'type'            => $f->type,
                'description'     => $f->description,
                'is_active'       => $f->is_active,
                'sort_order'      => $f->sort_order,
                'pivot' => [
                    'id'              => $f->pivot->id,
                    'is_enabled'      => (bool) $f->pivot->is_enabled,
                    'usage_limit'     => $f->pivot->usage_limit,
                    'limit_period'    => $f->pivot->limit_period,
                    'credits_per_use' => $f->pivot->credits_per_use,
                    'constraints'     => $f->pivot->constraints ? json_decode($f->pivot->constraints, true) : null,
                ],
            ]),

            // ── Features grouped by type ──
            'features_by_type' => $this->features
                ->groupBy('type')
                ->map(fn ($items, $type) => [
                    'type'     => $type,
                    'features' => $items->map(fn ($f) => [
                        'id'           => $f->id,
                        'name'         => $f->name,
                        'slug'         => $f->slug,
                        'is_enabled'   => (bool) $f->pivot->is_enabled,
                        'usage_limit'  => $f->pivot->usage_limit,
                        'limit_period' => $f->pivot->limit_period,
                    ])->values(),
                ])
                ->values(),

            // ── Stats ──
            'stats' => [
                'total_features'          => $this->features->count(),
                'enabled_features'        => $this->features->where('pivot.is_enabled', true)->count(),
                'total_subscriptions'     => $this->subscriptions_count ?? 0,
                'active_subscriptions'    => $this->active_subscriptions_count ?? 0,
            ],

            // ── Recent subscribers (last 10) ──
            'recent_subscribers' => $this->whenLoaded('subscriptions', fn () =>
                $this->subscriptions
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($sub) => [
                        'id'              => $sub->id,
                        'user'            => $sub->user ? [
                            'id'     => $sub->user->id,
                            'name'   => $sub->user->name,
                            'email'  => $sub->user->email,
                            'avatar' => $sub->user->avatar,
                        ] : null,
                        'billing_cycle'   => $sub->billing_cycle,
                        'status'          => $sub->status,
                        'price'           => (float) $sub->price,
                        'starts_at'       => $sub->starts_at?->toIso8601String(),
                        'ends_at'         => $sub->ends_at?->toIso8601String(),
                        'created_at'      => $sub->created_at?->toIso8601String(),
                    ])
            ),
        ];
    }
}
