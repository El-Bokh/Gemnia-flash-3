<?php

namespace App\Http\Requests\Admin\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'              => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'discount_type'     => ['required', 'string', 'in:percentage,fixed_amount,credits'],
            'discount_value'    => ['required', 'numeric', 'min:0.01'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'max_uses'          => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'min_order_amount'  => ['nullable', 'numeric', 'min:0'],
            'applicable_plan_id'=> ['nullable', 'integer', 'exists:plans,id'],
            'is_active'         => ['sometimes', 'boolean'],
            'starts_at'         => ['nullable', 'date'],
            'expires_at'        => ['nullable', 'date', 'after:starts_at'],
            'metadata'          => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->discount_type === 'percentage' && $this->discount_value > 100) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
            }
        });
    }
}
