<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'group'        => $this->group,
            'key'          => $this->key,
            'value'        => $this->is_encrypted ? $this->getMaskedValue() : $this->getTypedValue(),
            'raw_value'    => $this->when(! $this->is_encrypted, $this->value),
            'type'         => $this->type,
            'display_name' => $this->display_name,
            'description'  => $this->description,
            'is_public'    => $this->is_public,
            'is_encrypted' => $this->is_encrypted,
            'options'      => $this->options,
            'sort_order'   => $this->sort_order,
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
