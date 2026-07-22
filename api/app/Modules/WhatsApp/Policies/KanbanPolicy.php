<?php

namespace App\Modules\WhatsApp\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\WhatsApp\Models\KanbanStage;

class KanbanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_KANBAN_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WHATSAPP_KANBAN_UPDATE);
    }

    public function update(User $user, KanbanStage $stage): bool
    {
        return $user->tenant_id === $stage->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_KANBAN_UPDATE);
    }

    public function delete(User $user, KanbanStage $stage): bool
    {
        return $user->tenant_id === $stage->tenant_id
            && $user->hasPermission(Permission::WHATSAPP_KANBAN_UPDATE);
    }
}
