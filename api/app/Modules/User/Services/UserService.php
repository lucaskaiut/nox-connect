<?php

namespace App\Modules\User\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->with('roles.permissions')
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{name: string, email: string, phone: ?string, document: ?string, password: string}  $data
     */
    public function create(array $data): User
    {
        unset($data['is_master']);

        $user = User::query()->create($data);
        $user->forceFill(['is_master' => false])->save();

        return $user->load('roles.permissions');
    }

    /**
     * @param  array{name: string, email: string, phone: ?string, document: ?string, password: string}  $data
     */
    public function createForTenant(Tenant $tenant, array $data): User
    {
        unset($data['is_master']);

        $user = $tenant->users()->create($data);
        $user->forceFill(['is_master' => false])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['is_master']);

        $user->fill($data);
        $user->save();

        return $user->refresh()->load('roles.permissions');
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
