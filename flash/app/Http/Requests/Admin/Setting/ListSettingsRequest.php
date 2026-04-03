<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

class ListSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group'    => ['sometimes', 'string', 'max:50'],
            'search'   => ['sometimes', 'string', 'max:100'],
            'type'     => ['sometimes', 'string', 'in:string,text,boolean,integer,float,json'],
            'is_public'=> ['sometimes', 'boolean'],
        ];
    }
}
