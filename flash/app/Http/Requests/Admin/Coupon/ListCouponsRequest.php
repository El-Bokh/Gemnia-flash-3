<?php

namespace App\Http\Requests\Admin\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class ListCouponsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'            => ['sometimes', 'string', 'max:255'],
            'discount_type'     => ['sometimes', 'string', 'in:percentage,fixed_amount,credits'],
            'is_active'         => ['sometimes', 'boolean'],
            'applicable_plan_id'=> ['sometimes', 'integer', 'exists:plans,id'],
            'expired'           => ['sometimes', 'boolean'],
            'has_uses_remaining'=> ['sometimes', 'boolean'],
            'trashed'           => ['sometimes', 'in:only,with'],
            'sort_by'           => ['sometimes', 'string', 'in:id,code,name,discount_value,times_used,created_at,starts_at,expires_at'],
            'sort_dir'          => ['sometimes', 'string', 'in:asc,desc'],
            'per_page'          => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
