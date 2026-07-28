<?php

namespace App\Modules\Auth\Providers;

use App\Modules\User\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Carrega usuários para autenticação sem TenantScope.
 *
 * O middleware auth:sanctum roda antes de ResolveTenant; com fail-closed no
 * User, retrieveById() retornava null e /auth/me respondia 401 após login.
 */
class AuthUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::query()->withoutTenancy()->find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = User::query()->withoutTenancy()->find($identifier);

        if ($user === null) {
            return null;
        }

        $rememberToken = $user->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $user : null;
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $credentials = array_filter(
            $credentials,
            fn (mixed $value, string $key): bool => ! str_contains($key, 'password') && filled($value),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($credentials === []) {
            return null;
        }

        $query = User::query()->withoutTenancy();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        if ($user instanceof User) {
            parent::rehashPasswordIfRequired($user, $credentials, $force);
        }
    }
}
