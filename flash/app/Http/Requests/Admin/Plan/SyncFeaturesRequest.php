<?php

namespace App\Http\Requests\Admin\Plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'features'                   => ['required', 'array', 'min:1'],
            'features.*.feature_id'      => ['required', 'integer', Rule::exists('features', 'id')],
            'features.*.is_enabled'      => ['sometimes', 'boolean'],
            'features.*.usage_limit'     => ['nullable', 'integer', 'min:0'],
            'features.*.limit_period'    => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'year', 'lifetime'])],
            'features.*.credits_per_use' => ['sometimes', 'integer', 'min:0'],
            'features.*.constraints'     => ['sometimes', 'nullable', 'array'],
        ];
    }
}
