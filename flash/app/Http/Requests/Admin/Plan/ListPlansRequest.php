<?php

namespace App\Http\Requests\Admin\Plan;

use Illuminate\Foundation\Http\FormRequest;

class ListPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'      => ['sometimes', 'string', 'max:100'],
            'is_active'   => ['sometimes', 'boolean'],
            'is_free'     => ['sometimes', 'boolean'],
            'with_trashed'=> ['sometimes', 'boolean'],
            'sort_by'     => ['sometimes', 'string', 'in:name,slug,price_monthly,price_yearly,sort_order,created_at,subscriptions_count'],
            'sort_dir'    => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
