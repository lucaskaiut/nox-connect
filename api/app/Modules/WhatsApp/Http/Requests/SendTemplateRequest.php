<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name' => ['required', 'string', 'max:512'],
            'language' => ['required', 'string', 'max:10'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string'],
        ];
    }
}
