<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'waba_id' => ['sometimes', 'string', 'max:255'],
            'phone_number_id' => ['sometimes', 'string', 'max:255'],
            'access_token' => ['sometimes', 'string', 'max:2048'],
            'verify_token' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
