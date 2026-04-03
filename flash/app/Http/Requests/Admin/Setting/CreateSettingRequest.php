<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

class CreateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group'        => ['sometimes', 'string', 'max:50'],
            'key'          => ['required', 'string', 'max:100', 'unique:settings,key', 'regex:/^[a-z][a-z0-9_]*$/'],
            'value'        => ['present', 'nullable'],
            'type'         => ['sometimes', 'string', 'in:string,text,boolean,integer,float,json'],
            'display_name' => ['sometimes', 'string', 'max:150'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_public'    => ['sometimes', 'boolean'],
            'is_encrypted' => ['sometimes', 'boolean'],
            'options'      => ['sometimes', 'nullable', 'array'],
            'sort_order'   => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'Key must start with a letter and contain only lowercase letters, numbers, and underscores.',
        ];
    }
}
