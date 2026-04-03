<?php

namespace App\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'          => ['sometimes', 'string', 'in:pending,completed,failed,refunded,partially_refunded,cancelled,disputed'],
            'description'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'billing_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_email'   => ['sometimes', 'nullable', 'email', 'max:255'],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'billing_city'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_state'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_zip'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'billing_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'metadata'        => ['sometimes', 'nullable', 'array'],
        ];
    }
}
