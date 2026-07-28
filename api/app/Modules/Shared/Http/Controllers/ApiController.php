<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Para requests autenticados via API token, o middleware EnsurePermission
     * já validou os escopos — a verificação do Gate de permissão é redundante.
     * Regras de recurso (tenant, roles default, etc.) continuam sendo aplicadas.
     */
    public function authorize($ability, $arguments = []): void
    {
        if (request()->attributes->get('api_token')) {
            $this->authorizeForApiToken($ability, $arguments);

            return;
        }

        Gate::forUser(request()->user())->authorize($ability, $arguments);
    }

    protected function authorizeForApiToken(string $ability, mixed $arguments): void
    {
        $model = $this->resolveAuthorizationModel($arguments);

        if ($model === null) {
            return;
        }

        if ($this->modelHasTenantId($model)) {
            if (! TenantAuthorization::matchesCurrentTenant((int) $model->getAttribute('tenant_id'))) {
                throw new AuthorizationException;
            }
        }

        if ($model instanceof Role && in_array($ability, ['update', 'delete'], true) && $model->isDefault()) {
            throw new AuthorizationException;
        }
    }

    private function resolveAuthorizationModel(mixed $arguments): ?object
    {
        if (is_object($arguments)) {
            return $arguments;
        }

        if (is_array($arguments)) {
            foreach ($arguments as $argument) {
                if (is_object($argument)) {
                    return $argument;
                }
            }
        }

        return null;
    }

    private function modelHasTenantId(object $model): bool
    {
        return method_exists($model, 'getAttribute')
            && $model->getAttribute('tenant_id') !== null;
    }

    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function paginated(AnonymousResourceCollection $collection, ?string $message = null): JsonResponse
    {
        $payload = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $payload['data'] ?? [],
            'meta' => $payload['meta'] ?? null,
            'links' => $payload['links'] ?? null,
        ]);
    }
}
