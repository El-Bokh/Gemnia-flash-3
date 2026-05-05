<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'type'        => $this->type,
            'is_active'   => $this->is_active,
            'sort_order'  => $this->sort_order,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),

            // ── Plans using this feature ──
            'plans' => $this->whenLoaded('plans', fn () =>
                $this->plans->map(fn ($p) => [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'slug'            => $p->slug,
                    'is_active'       => $p->is_active,
                    'is_enabled'      => (bool) $p->pivot->is_enabled,
                    'usage_limit'     => $p->pivot->usage_limit,
                    'limit_period'    => $p->pivot->limit_period,
                    'credits_per_use' => $p->pivot->credits_per_use,
                    'constraints'     => $p->pivot->constraints ? json_decode($p->pivot->constraints, true) : null,
                ])
            ),

            // ── Counts ──
            'plans_count' => $this->whenCounted('plans'),
        ];
    }
}
