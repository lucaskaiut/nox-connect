<?php

namespace App\Modules\ApiToken\Http\Controllers;

use App\Modules\ApiToken\Http\Requests\StoreApiTokenRequest;
use App\Modules\ApiToken\Http\Resources\ApiTokenResource;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ApiTokenController extends ApiController
{
    public function __construct(
        private readonly ApiTokenService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ApiToken::class);

        return $this->success(ApiTokenResource::collection($this->service->list()));
    }

    public function store(StoreApiTokenRequest $request): JsonResponse
    {
        $this->authorize('create', ApiToken::class);

        $expiresAt = $request->validated('expires_at');
        $permissions = $request->validated('permissions');

        $issued = $this->service->issue(
            $request->validated('name'),
            $expiresAt !== null ? Carbon::parse($expiresAt) : null,
            $permissions !== null ? $permissions : null,
        );

        if ($user = request()->user()) {
            $this->audit->record($user, AuditAction::ApiTokenCreated);
        }

        return $this->created([
            'token' => $issued->plainTextToken,
            'api_token' => ApiTokenResource::make($issued->apiToken),
        ], 'Token criado com sucesso. Guarde-o em local seguro: ele não será exibido novamente.');
    }

    public function destroy(ApiToken $apiToken): JsonResponse
    {
        $this->authorize('delete', $apiToken);

        $this->service->revoke($apiToken);

        if ($user = request()->user()) {
            $this->audit->record($user, AuditAction::ApiTokenRevoked);
        }

        return $this->success(null, 'Token revogado com sucesso.');
    }
}
