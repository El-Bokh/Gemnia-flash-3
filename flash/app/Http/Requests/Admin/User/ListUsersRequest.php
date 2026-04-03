<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'        => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', Rule::in(['active', 'suspended', 'banned', 'pending'])],
            'role'          => ['nullable', 'string', 'max:50'],
            'plan'          => ['nullable', 'string', 'max:50'],
            'subscription_status' => ['nullable', Rule::in(['active', 'cancelled', 'expired', 'past_due', 'trialing', 'paused', 'pending'])],
            'sort_by'       => ['nullable', Rule::in(['name', 'email', 'created_at', 'last_login_at', 'status'])],
            'sort_dir'      => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
            'with_trashed'  => ['nullable', 'boolean'],
        ];
    }
}
