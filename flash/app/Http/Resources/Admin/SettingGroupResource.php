<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingGroupResource extends JsonResource
{
    /**
     * Expects a collection of Setting models for a single group.
     * Wrap with: new SettingGroupResource($groupedCollection)
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;

        return [
            'group'    => $settings->first()?->group,
            'count'    => $settings->count(),
            'settings' => SettingResource::collection($settings),
        ];
    }
}
