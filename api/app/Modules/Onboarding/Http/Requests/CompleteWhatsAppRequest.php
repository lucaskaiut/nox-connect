<?php

namespace App\Modules\Onboarding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Payload flexível por estratégia (SDK / form / oauth).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_id' => ['nullable', 'string', 'max:255'],
            'connectionId' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'phoneNumber' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:50'],
            'account_id' => ['nullable', 'string', 'max:255'],
            'channel_id' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'webhook_verify_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
