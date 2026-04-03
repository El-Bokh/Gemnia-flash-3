<?php

namespace App\Http\Requests\Admin\AiRequest;

use Illuminate\Foundation\Http\FormRequest;

class BulkAiRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'request_ids.*' => ['required', 'integer', 'exists:ai_requests,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_ids.max' => 'You can process a maximum of 100 requests at a time.',
        ];
    }
}
