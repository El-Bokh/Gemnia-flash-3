<?php

namespace App\Http\Requests\Admin\SupportTicket;

use Illuminate\Foundation\Http\FormRequest;

class ListSupportTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'       => ['sometimes', 'string', 'max:255'],
            'user_id'      => ['sometimes', 'integer', 'exists:users,id'],
            'assigned_to'  => ['sometimes', 'integer', 'exists:users,id'],
            'status'       => ['sometimes', 'string', 'in:open,in_progress,waiting_reply,resolved,closed'],
            'priority'     => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'category'     => ['sometimes', 'string', 'max:100'],
            'unassigned'   => ['sometimes', 'boolean'],
            'date_from'    => ['sometimes', 'date'],
            'date_to'      => ['sometimes', 'date', 'after_or_equal:date_from'],
            'last_reply_from' => ['sometimes', 'date'],
            'last_reply_to'   => ['sometimes', 'date', 'after_or_equal:last_reply_from'],
            'trashed'      => ['sometimes', 'in:only,with'],
            'sort_by'      => ['sometimes', 'string', 'in:id,ticket_number,status,priority,created_at,updated_at,last_reply_at,closed_at'],
            'sort_dir'     => ['sometimes', 'string', 'in:asc,desc'],
            'per_page'     => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
