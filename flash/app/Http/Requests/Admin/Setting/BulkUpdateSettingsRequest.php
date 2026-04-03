<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings'         => ['required', 'array', 'min:1'],
            'settings.*.key'   => ['required', 'string', 'exists:settings,key'],
            'settings.*.value' => ['present', 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required'           => 'At least one setting is required.',
            'settings.*.key.exists'       => 'Setting key ":input" does not exist.',
            'settings.*.value.present'    => 'Each setting must include a value (can be null).',
        ];
    }
}
