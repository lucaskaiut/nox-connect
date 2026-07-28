<?php

namespace App\Modules\ACL\Http\Middleware;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = array_map('trim', explode('|', $permission));
        $enums = [];

        foreach ($permissions as $value) {
            $enum = Permission::tryFrom($value);

            if ($enum === null) {
                throw new InvalidArgumentException("Permissão desconhecida [{$value}].");
            }

            $enums[] = $enum;
        }

        $apiToken = $request->attributes->get('api_token');
        $user = $request->user();

        if ($apiToken instanceof ApiToken) {
            foreach ($enums as $enum) {
                if ($apiToken->can($enum->value)) {
                    return $next($request);
                }
            }

            throw new AuthorizationException('Este token não possui escopo para executar esta ação.');
        }

        if ($user instanceof User) {
            foreach ($enums as $enum) {
                if ($user->hasPermission($enum)) {
                    return $next($request);
                }
            }

            throw new AuthorizationException('Você não possui permissão para executar esta ação.');
        }

        throw new AuthenticationException;
    }
}
