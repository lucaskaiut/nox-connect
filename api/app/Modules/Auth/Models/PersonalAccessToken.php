<?php

namespace App\Modules\Auth\Models;

use App\Modules\Tenant\Models\Scopes\TenantScope;
use App\Modules\User\Models\User;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum PAT: tokenable (User) deve ignorar TenantScope na autenticação.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function tokenable()
    {
        $relation = $this->morphTo();

        if ($this->tokenable_type === User::class || $this->tokenable_type === (new User)->getMorphClass()) {
            return $relation->withoutGlobalScope(TenantScope::class);
        }

        return $relation;
    }
}
