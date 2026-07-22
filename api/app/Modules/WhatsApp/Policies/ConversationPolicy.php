<?php

namespace App\Modules\WhatsApp\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConversation;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_CONVERSATION_READ);
    }

    public function view(User $user, WhatsAppConversation $conversation): bool
    {
        return $user->tenant_id === $conversation->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_CONVERSATION_READ);
    }

    public function update(User $user, WhatsAppConversation $conversation): bool
    {
        return $user->tenant_id === $conversation->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_CONVERSATION_UPDATE);
    }
}
