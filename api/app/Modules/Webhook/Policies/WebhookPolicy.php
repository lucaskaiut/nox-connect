<?php

namespace App\Modules\Webhook\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\Webhook\Models\Webhook;

class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WEBHOOK_READ);
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id
            && $user->hasPermission(Permission::WEBHOOK_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WEBHOOK_CREATE);
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id
            && $user->hasPermission(Permission::WEBHOOK_UPDATE);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id
            && $user->hasPermission(Permission::WEBHOOK_DELETE);
    }
}
