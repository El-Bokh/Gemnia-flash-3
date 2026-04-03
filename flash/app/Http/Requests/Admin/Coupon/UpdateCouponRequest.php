<?php

namespace App\Http\Requests\Admin\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'              => ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($this->route('coupon'))],
            'name'              => ['sometimes', 'string', 'max:255'],
            'description'       => ['sometimes', 'nullable', 'string', 'max:1000'],
            'discount_type'     => ['sometimes', 'string', 'in:percentage,fixed_amount,credits'],
            'discount_value'    => ['sometimes', 'numeric', 'min:0.01'],
            'currency'          => ['sometimes', 'nullable', 'string', 'size:3'],
            'max_uses'          => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_order_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'applicable_plan_id'=> ['sometimes', 'nullable', 'integer', 'exists:plans,id'],
            'is_active'         => ['sometimes', 'boolean'],
            'starts_at'         => ['sometimes', 'nullable', 'date'],
            'expires_at'        => ['sometimes', 'nullable', 'date'],
            'metadata'          => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->discount_type ?? $this->route('coupon')?->discount_type ?? null;
            if ($type === 'percentage' && $this->has('discount_value') && $this->discount_value > 100) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
            }
        });
    }
}
