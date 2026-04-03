<?php

namespace App\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'          => ['sometimes', 'string', 'max:255'],
            'user_id'         => ['sometimes', 'integer', 'exists:users,id'],
            'subscription_id' => ['sometimes', 'integer', 'exists:subscriptions,id'],
            'coupon_id'       => ['sometimes', 'integer', 'exists:coupons,id'],
            'status'          => ['sometimes', 'string', 'in:pending,completed,failed,refunded,partially_refunded,cancelled,disputed'],
            'payment_gateway' => ['sometimes', 'string', 'max:50'],
            'payment_method'  => ['sometimes', 'string', 'max:50'],
            'currency'        => ['sometimes', 'string', 'size:3'],
            'amount_min'      => ['sometimes', 'numeric', 'min:0'],
            'amount_max'      => ['sometimes', 'numeric', 'min:0'],
            'date_from'       => ['sometimes', 'date'],
            'date_to'         => ['sometimes', 'date', 'after_or_equal:date_from'],
            'paid_from'       => ['sometimes', 'date'],
            'paid_to'         => ['sometimes', 'date', 'after_or_equal:paid_from'],
            'has_refund'      => ['sometimes', 'boolean'],
            'trashed'         => ['sometimes', 'in:only,with'],
            'sort_by'         => ['sometimes', 'string', 'in:id,amount,net_amount,status,payment_gateway,created_at,paid_at,refunded_at'],
            'sort_dir'        => ['sometimes', 'string', 'in:asc,desc'],
            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
