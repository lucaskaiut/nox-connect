<?php

namespace App\Modules\Webhook\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Webhook\Enums\WebhookEvent;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'method' => ['sometimes', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'event' => ['sometimes', 'string', Rule::enum(WebhookEvent::class)],
            'headers' => ['nullable', 'array'],
            'query_params' => ['nullable', 'array'],
            'body_template' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'secret' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
