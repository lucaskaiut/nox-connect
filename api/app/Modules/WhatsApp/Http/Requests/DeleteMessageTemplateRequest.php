<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:512'],
            'hsm_id' => ['nullable', 'string'],
            'hsm_ids' => ['nullable', 'json'],
        ];
    }
}
