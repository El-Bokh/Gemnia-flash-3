<?php

namespace App\Http\Requests\Admin\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class ListInvoicesRequest extends FormRequest
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
            'payment_id'      => ['sometimes', 'integer', 'exists:payments,id'],
            'subscription_id' => ['sometimes', 'integer', 'exists:subscriptions,id'],
            'status'          => ['sometimes', 'string', 'in:draft,issued,paid,overdue,cancelled,refunded'],
            'currency'        => ['sometimes', 'string', 'size:3'],
            'total_min'       => ['sometimes', 'numeric', 'min:0'],
            'total_max'       => ['sometimes', 'numeric', 'min:0'],
            'issued_from'     => ['sometimes', 'date'],
            'issued_to'       => ['sometimes', 'date', 'after_or_equal:issued_from'],
            'due_from'        => ['sometimes', 'date'],
            'due_to'          => ['sometimes', 'date', 'after_or_equal:due_from'],
            'overdue'         => ['sometimes', 'boolean'],
            'trashed'         => ['sometimes', 'in:only,with'],
            'sort_by'         => ['sometimes', 'string', 'in:id,invoice_number,total,status,issued_at,due_at,paid_at,created_at'],
            'sort_dir'        => ['sometimes', 'string', 'in:asc,desc'],
            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
