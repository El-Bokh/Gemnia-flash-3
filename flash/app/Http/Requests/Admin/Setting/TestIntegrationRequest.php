<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

class TestIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'integration' => ['required', 'string', 'in:openai,stability_ai,stripe,paypal,mailgun,smtp,google_analytics'],
        ];
    }
}
