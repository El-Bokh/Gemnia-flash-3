<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', Password::min(8)->mixedCase()->numbers()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'avatar'   => ['nullable', 'string', 'max:500'],
            'status'   => ['sometimes', Rule::in(['active', 'suspended', 'banned', 'pending'])],
            'locale'   => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],

            // Role assignment
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'   => 'This email address is already taken by another user.',
            'roles.*.exists' => 'One or more selected roles are invalid.',
        ];
    }
}
