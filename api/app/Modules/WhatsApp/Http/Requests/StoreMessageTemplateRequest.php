<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\WhatsApp\Enums\TemplateCategory;
use App\Modules\WhatsApp\Enums\TemplateParameterFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:10'],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            'parameter_format' => ['nullable', Rule::enum(TemplateParameterFormat::class)],
            'components' => ['nullable', 'array'],
            'allow_category_change' => ['nullable', 'boolean'],
            'cta_url_link_tracking_opted_out' => ['nullable', 'boolean'],
            'message_send_ttl_seconds' => ['nullable', 'integer'],
            'sub_category' => ['nullable', 'string'],
            'display_format' => ['nullable', 'string'],
            'library_template_name' => ['nullable', 'string', 'max:512'],
            'library_template_button_inputs' => ['nullable', 'array'],
            'library_template_body_inputs' => ['nullable', 'array'],
            'is_primary_device_delivery_only' => ['nullable', 'boolean'],
            'send_type' => ['nullable', 'string'],
        ];
    }
}
