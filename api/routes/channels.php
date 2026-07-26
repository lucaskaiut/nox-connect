<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantUuid}', function ($user, $tenantUuid) {
    return (string) $user->tenantUuid() === (string) $tenantUuid;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = WhatsAppConversation::query()->withoutTenancy()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    return (string) $user->tenant_id === (string) $conversation->tenant_id
        && $user->hasPermission(Permission::WHATSAPP_CONVERSATION_READ);
});
