<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'avatar'   => ['nullable', 'string', 'max:500'],
            'status'   => ['nullable', Rule::in(['active', 'suspended', 'banned', 'pending'])],
            'locale'   => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],

            // Role assignment
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['integer', 'exists:roles,id'],

            // Optional: assign a plan at creation
            'plan_id'       => ['nullable', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'    => 'This email address is already registered.',
            'password.min'    => 'Password must be at least 8 characters.',
            'roles.*.exists'  => 'One or more selected roles are invalid.',
            'plan_id.exists'  => 'The selected plan does not exist.',
        ];
    }
}
