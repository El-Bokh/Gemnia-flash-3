<?php

namespace App\Http\Requests\Admin\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'          => ['sometimes', 'string', 'in:draft,issued,paid,overdue,cancelled,refunded'],
            'billing_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_email'   => ['sometimes', 'nullable', 'email', 'max:255'],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'billing_city'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_state'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_zip'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'billing_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:2000'],
            'footer'          => ['sometimes', 'nullable', 'string', 'max:1000'],
            'due_at'          => ['sometimes', 'nullable', 'date'],
            'metadata'        => ['sometimes', 'nullable', 'array'],
        ];
    }
}
