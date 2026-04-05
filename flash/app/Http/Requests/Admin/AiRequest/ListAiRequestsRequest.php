<?php

namespace App\Http\Requests\Admin\AiRequest;

use Illuminate\Foundation\Http\FormRequest;

class ListAiRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'          => ['sometimes', 'string', 'max:200'],
            'status'          => ['sometimes', 'string', 'in:pending,processing,completed,failed,cancelled,timeout'],
            'type'            => ['sometimes', 'string', 'in:text_to_image,image_to_image,inpainting,upscale,chat,styled_chat,multimodal,other'],
            'user_id'         => ['sometimes', 'integer', 'exists:users,id'],
            'visual_style_id' => ['sometimes', 'integer', 'exists:visual_styles,id'],
            'model_used'      => ['sometimes', 'string', 'max:100'],
            'engine_provider' => ['sometimes', 'string', 'max:100'],
            'date_from'       => ['sometimes', 'date'],
            'date_to'         => ['sometimes', 'date', 'after_or_equal:date_from'],
            'has_images'      => ['sometimes', 'boolean'],
            'min_credits'     => ['sometimes', 'integer', 'min:0'],
            'max_credits'     => ['sometimes', 'integer', 'min:0'],
            'with_trashed'    => ['sometimes', 'boolean'],
            'sort_by'         => ['sometimes', 'string', 'in:id,created_at,updated_at,status,type,credits_consumed,processing_time_ms,retry_count,user_prompt'],
            'sort_dir'        => ['sometimes', 'string', 'in:asc,desc'],
            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'            => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
