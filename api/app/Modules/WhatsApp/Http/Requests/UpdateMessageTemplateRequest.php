<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\WhatsApp\Enums\TemplateCategory;
use App\Modules\WhatsApp\Enums\TemplateParameterFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::enum(TemplateCategory::class)],
            'parameter_format' => ['nullable', Rule::enum(TemplateParameterFormat::class)],
            'components' => ['nullable', 'array'],
            'allow_category_change' => ['nullable', 'boolean'],
            'cta_url_link_tracking_opted_out' => ['nullable', 'boolean'],
            'message_send_ttl_seconds' => ['nullable', 'integer'],
            'sub_category' => ['nullable', 'string'],
            'display_format' => ['nullable', 'string'],
            'is_primary_device_delivery_only' => ['nullable', 'boolean'],
        ];
    }
}
