<?php

namespace App\Modules\WhatsApp\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppTag;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_TAG_READ);
    }

    public function view(User $user, WhatsAppTag $tag): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $tag->tenant_id)
            && $user->hasPermission(Permission::WHATSAPP_TAG_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_TAG_CREATE);
    }

    public function update(User $user, WhatsAppTag $tag): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $tag->tenant_id)
            && $user->hasPermission(Permission::WHATSAPP_TAG_UPDATE);
    }

    public function delete(User $user, WhatsAppTag $tag): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $tag->tenant_id)
            && $user->hasPermission(Permission::WHATSAPP_TAG_DELETE);
    }
}
