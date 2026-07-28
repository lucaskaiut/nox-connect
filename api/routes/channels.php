<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantUuid}', function ($user, $tenantUuid) {
    $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();

    if (! $tenant) {
        return false;
    }

    return $user->canAccessTenant($tenant);
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = WhatsAppConversation::query()->withoutTenancy()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    return $user->canAccessTenant($conversation->tenant_id)
        && $user->hasPermission(Permission::WHATSAPP_CONVERSATION_READ);
});
