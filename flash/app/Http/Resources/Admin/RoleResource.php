<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'is_default'        => $this->is_default,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),

            // ── Permissions ──
            'permissions' => $this->whenLoaded('permissions', fn () =>
                $this->permissions->map(fn ($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'slug'  => $p->slug,
                    'group' => $p->group,
                ])
            ),

            // ── Counts ──
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count'       => $this->whenCounted('users'),
        ];
    }
}
