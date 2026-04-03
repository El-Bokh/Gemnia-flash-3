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
            'attachments' => ['sometimes', 'nullable', 'array'],
            'attachments.*' => ['string', 'max:500'],
        ];
    }
}
