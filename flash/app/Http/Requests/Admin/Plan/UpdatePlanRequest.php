<?php

namespace App\Http\Requests\Admin\Plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan');

        return [
            'name'            => ['sometimes', 'string', 'max:100'],
            'slug'            => ['sometimes', 'string', 'max:100', Rule::unique('plans', 'slug')->ignore($planId), 'regex:/^[a-z0-9_-]+$/'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'price_monthly'   => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'price_yearly'    => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'currency'        => ['sometimes', 'string', 'size:3'],
            'credits_monthly' => ['sometimes', 'integer', 'min:0'],
            'credits_yearly'  => ['sometimes', 'integer', 'min:0'],
            'is_free'         => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
            'is_featured'     => ['sometimes', 'boolean'],
            'sort_order'      => ['sometimes', 'integer', 'min:0'],
            'trial_days'      => ['sometimes', 'integer', 'min:0', 'max:365'],
            'metadata'        => ['sometimes', 'nullable', 'array'],

            // Features with limits
            'features'                   => ['sometimes', 'array'],
            'features.*.feature_id'      => ['required_with:features', 'integer', Rule::exists('features', 'id')],
            'features.*.is_enabled'      => ['sometimes', 'boolean'],
            'features.*.usage_limit'     => ['nullable', 'integer', 'min:0'],
            'features.*.limit_period'    => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'year', 'lifetime'])],
            'features.*.credits_per_use' => ['sometimes', 'integer', 'min:0'],
            'features.*.constraints'     => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug must contain only lowercase letters, numbers, hyphens, and underscores.',
        ];
    }
}
