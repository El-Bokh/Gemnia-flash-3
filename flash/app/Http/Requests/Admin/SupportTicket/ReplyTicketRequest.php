<?php

namespace App\Http\Requests\Admin\SupportTicket;

use Illuminate\Foundation\Http\FormRequest;

class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'     => ['required', 'string', 'min:1', 'max:10000'],
            'attachments' => ['sometimes', 'nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx,xls,xlsx,csv'],
        ];
    }
}
