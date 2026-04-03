<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleDetailResource extends JsonResource
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

            // ── Full Permissions ──
            'permissions' => $this->permissions->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'slug'        => $p->slug,
                'group'       => $p->group,
                'description' => $p->description,
            ]),

            // ── Permissions grouped by category ──
            'permissions_grouped' => $this->permissions
                ->groupBy('group')
                ->map(fn ($perms, $group) => [
                    'group'       => $group,
                    'permissions' => $perms->map(fn ($p) => [
                        'id'   => $p->id,
                        'name' => $p->name,
                        'slug' => $p->slug,
                    ])->values(),
                ])
                ->values(),

            // ── Counts ──
            'permissions_count' => $this->permissions->count(),
            'users_count'       => $this->users_count ?? 0,

            // ── Users assigned to this role (last 10) ──
            'recent_users' => $this->whenLoaded('users', fn () =>
                $this->users
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->values()
                    ->map(fn ($u) => [
                        'id'     => $u->id,
                        'name'   => $u->name,
                        'email'  => $u->email,
                        'avatar' => $u->avatar,
                        'status' => $u->status,
                    ])
            ),
        ];
    }
}
