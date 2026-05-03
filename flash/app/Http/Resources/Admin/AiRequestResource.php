<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'uuid'              => $this->uuid,
            'type'              => $this->type,
            'status'            => $this->status,
            'user_prompt'       => $this->user_prompt,
            'model_used'        => $this->model_used,
            'engine_provider'   => $this->engine_provider,
            'width'             => $this->width,
            'height'            => $this->height,
            'num_images'        => $this->num_images,
            'credits_consumed'  => $this->credits_consumed,
            'retry_count'       => $this->retry_count,
            'processing_time_ms'=> $this->processing_time_ms,
            'output_video_path' => $this->output_video_path,
            'error_message'     => $this->when($this->status === 'failed', $this->error_message),
            'error_code'        => $this->when($this->status === 'failed', $this->error_code),
            'started_at'        => $this->started_at?->toIso8601String(),
            'completed_at'      => $this->completed_at?->toIso8601String(),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'deleted_at'        => $this->deleted_at?->toIso8601String(),

            // ── User (summary) ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),

            // ── Visual Style (summary) ──
            'visual_style' => $this->whenLoaded('visualStyle', fn () => $this->visualStyle ? [
                'id'        => $this->visualStyle->id,
                'name'      => $this->visualStyle->name,
                'slug'      => $this->visualStyle->slug,
                'thumbnail' => $this->visualStyle->thumbnail,
            ] : null),

            // ── Generated Images count ──
            'generated_images_count' => $this->whenCounted('generatedImages'),
        ];
    }
}
