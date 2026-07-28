<?php

namespace App\Modules\User\Http\Controllers;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Http\Requests\StoreUserRequest;
use App\Modules\User\Http\Requests\UpdateUserRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function __construct(
        private readonly UserService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(UserResource::collection($users));
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->success(UserResource::make($user->load('roles.permissions')));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->service->create($request->validated());

        if ($actor = request()->user()) {
            $this->audit->record($actor, AuditAction::UserCreated);
        }

        return $this->created(UserResource::make($user), 'Usuário criado com sucesso.');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->service->update($user, $request->validated());

        return $this->success(UserResource::make($user), 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->service->delete($user);

        if ($actor = request()->user()) {
            $this->audit->record($actor, AuditAction::UserDeleted);
        }

        return $this->success(null, 'Usuário removido com sucesso.');
    }
}
