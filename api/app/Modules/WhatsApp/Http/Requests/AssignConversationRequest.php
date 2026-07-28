<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::WHATSAPP_CONVERSATION_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'uuid')
                    ->where('tenant_id', TenantContext::tenantId())
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
