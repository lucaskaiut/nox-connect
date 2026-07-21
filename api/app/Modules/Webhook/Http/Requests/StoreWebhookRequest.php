<?php

namespace App\Modules\Webhook\Http\Requests;

use App\Modules\Webhook\Enums\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'method' => ['sometimes', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'event' => ['required', 'string', Rule::enum(WebhookEvent::class)],
            'headers' => ['nullable', 'array'],
            'query_params' => ['nullable', 'array'],
            'body_template' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'secret' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
