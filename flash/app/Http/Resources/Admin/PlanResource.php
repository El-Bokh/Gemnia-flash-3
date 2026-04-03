<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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

            // ── Features with pivot limits ──
            'features' => $this->whenLoaded('features', fn () =>
                $this->features->map(fn ($f) => [
                    'id'              => $f->id,
                    'name'            => $f->name,
                    'slug'            => $f->slug,
                    'type'            => $f->type,
                    'is_active'       => $f->is_active,
                    'is_enabled'      => (bool) $f->pivot->is_enabled,
                    'usage_limit'     => $f->pivot->usage_limit,
                    'limit_period'    => $f->pivot->limit_period,
                    'credits_per_use' => $f->pivot->credits_per_use,
                    'constraints'     => $f->pivot->constraints ? json_decode($f->pivot->constraints, true) : null,
                ])
            ),

            // ── Counts ──
            'features_count'      => $this->whenCounted('features'),
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'active_subscriptions_count' => $this->when(
                isset($this->active_subscriptions_count),
                $this->active_subscriptions_count ?? 0
            ),
        ];
    }
}
