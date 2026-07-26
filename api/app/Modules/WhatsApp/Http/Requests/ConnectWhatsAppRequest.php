<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload de onboarding — campos flexíveis por provider.
 * Meta exige account_id + channel_id; outros BSPs podem usar connection_id etc.
 */
class ConnectWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'string', 'max:255'],
            'channel_id' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'connection_id' => ['nullable', 'string', 'max:255'],
            'workspace_id' => ['nullable', 'string', 'max:255'],
            'instance_id' => ['nullable', 'string', 'max:255'],
            'webhook_verify_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
