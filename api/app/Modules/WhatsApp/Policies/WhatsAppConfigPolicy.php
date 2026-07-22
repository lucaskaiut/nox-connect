<?php

namespace App\Modules\WhatsApp\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConfig;

class WhatsAppConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_CONFIG_READ);
    }

    public function view(User $user, WhatsAppConfig $config): bool
    {
        return $user->tenant_id === $config->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_CONFIG_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_CONFIG_CREATE);
    }

    public function update(User $user, WhatsAppConfig $config): bool
    {
        return $user->tenant_id === $config->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_CONFIG_UPDATE);
    }

    public function delete(User $user, WhatsAppConfig $config): bool
    {
        return $user->tenant_id === $config->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_CONFIG_DELETE);
    }
}
