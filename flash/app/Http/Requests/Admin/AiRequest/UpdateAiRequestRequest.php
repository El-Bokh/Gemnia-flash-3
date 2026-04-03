<?php

namespace App\Http\Requests\Admin\AiRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'           => ['sometimes', 'string', Rule::in(['pending', 'processing', 'completed', 'failed', 'cancelled', 'timeout'])],
            'processed_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'negative_prompt'  => ['sometimes', 'nullable', 'string', 'max:5000'],
            'model_used'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'engine_provider'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'error_message'    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'error_code'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'metadata'         => ['sometimes', 'nullable', 'array'],
        ];
    }
}
