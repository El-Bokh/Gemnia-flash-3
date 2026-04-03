<?php

namespace App\Http\Requests\Admin\Role;

use Illuminate\Foundation\Http\FormRequest;

class ListRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'       => ['sometimes', 'string', 'max:100'],
            'with_counts'  => ['sometimes', 'boolean'],
            'sort_by'      => ['sometimes', 'string', 'in:name,slug,created_at,users_count,permissions_count'],
            'sort_dir'     => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
