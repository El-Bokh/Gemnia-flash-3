<?php

namespace App\Http\Requests\Admin\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'    => ['required', 'string', 'max:50'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
            'amount'  => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
