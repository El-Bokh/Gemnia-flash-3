<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiRequestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'uuid'               => $this->uuid,
            'type'               => $this->type,
            'status'             => $this->status,

            // ── Prompts ──
            'user_prompt'        => $this->user_prompt,
            'processed_prompt'   => $this->processed_prompt,
            'negative_prompt'    => $this->negative_prompt,
            'hidden_prompt'      => $this->hidden_prompt,

            // ── AI Engine / Model ──
            'model_used'         => $this->model_used,
            'engine_provider'    => $this->engine_provider,

            // ── Generation Parameters ──
            'width'              => $this->width,
            'height'             => $this->height,
            'steps'              => $this->steps,
            'cfg_scale'          => $this->cfg_scale ? (float) $this->cfg_scale : null,
            'sampler'            => $this->sampler,
            'seed'               => $this->seed,
            'num_images'         => $this->num_images,
            'denoising_strength' => $this->denoising_strength ? (float) $this->denoising_strength : null,

            // ── Input Images (for image_to_image / inpainting) ──
            'input_image_path'   => $this->input_image_path,
            'output_image_path'  => $this->output_image_path,
            'mask_image_path'    => $this->mask_image_path,

            // ── Credits & Performance ──
            'credits_consumed'   => $this->credits_consumed,
            'processing_time_ms' => $this->processing_time_ms,

            // ── Error Info ──
            'error_message'      => $this->error_message,
            'error_code'         => $this->error_code,
            'retry_count'        => $this->retry_count,

            // ── Client Info ──
            'ip_address'         => $this->ip_address,
            'user_agent'         => $this->user_agent,

            // ── Payloads ──
            'request_payload'    => $this->request_payload,
            'response_payload'   => $this->response_payload,
            'metadata'           => $this->metadata,

            // ── Timestamps ──
            'started_at'         => $this->started_at?->toIso8601String(),
            'completed_at'       => $this->completed_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
            'deleted_at'         => $this->deleted_at?->toIso8601String(),

            // ── User ──
            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar,
                'status' => $this->user->status,
            ]),

            // ── Subscription ──
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'id'     => $this->subscription->id,
                'status' => $this->subscription->status,
                'plan'   => $this->subscription->plan ? [
                    'id'   => $this->subscription->plan->id,
                    'name' => $this->subscription->plan->name,
                    'slug' => $this->subscription->plan->slug,
                ] : null,
            ] : null),

            // ── Visual Style ──
            'visual_style' => $this->whenLoaded('visualStyle', fn () => $this->visualStyle ? [
                'id'            => $this->visualStyle->id,
                'name'          => $this->visualStyle->name,
                'slug'          => $this->visualStyle->slug,
                'thumbnail'     => $this->visualStyle->thumbnail,
                'category'      => $this->visualStyle->category,
                'prompt_prefix' => $this->visualStyle->prompt_prefix,
                'prompt_suffix' => $this->visualStyle->prompt_suffix,
            ] : null),

            // ── Generated Images ──
            'generated_images' => $this->whenLoaded('generatedImages', fn () =>
                $this->generatedImages->map(fn ($img) => [
                    'id'             => $img->id,
                    'uuid'           => $img->uuid,
                    'file_path'      => $img->file_path,
                    'file_name'      => $img->file_name,
                    'disk'           => $img->disk,
                    'mime_type'      => $img->mime_type,
                    'file_size'      => $img->file_size,
                    'width'          => $img->width,
                    'height'         => $img->height,
                    'thumbnail_path' => $img->thumbnail_path,
                    'is_public'      => $img->is_public,
                    'is_nsfw'        => $img->is_nsfw,
                    'download_count' => $img->download_count,
                    'view_count'     => $img->view_count,
                    'created_at'     => $img->created_at?->toIso8601String(),
                ])
            ),

            // ── Usage Logs ──
            'usage_logs' => $this->whenLoaded('usageLogs', fn () =>
                $this->usageLogs->map(fn ($log) => [
                    'id'           => $log->id,
                    'action'       => $log->action,
                    'credits_used' => $log->credits_used,
                    'feature'      => $log->feature ? [
                        'id'   => $log->feature->id,
                        'name' => $log->feature->name,
                        'slug' => $log->feature->slug,
                    ] : null,
                    'created_at'   => $log->created_at?->toIso8601String(),
                ])
            ),

            // ── Stats ──
            'stats' => [
                'generated_images_count' => $this->generatedImages->count() ?: ($this->output_image_path ? 1 : 0),
                'usage_logs_count'       => $this->usageLogs->count(),
                'total_credits_logged'   => $this->usageLogs->sum('credits_used'),
            ],
        ];
    }
}
