<?php

namespace Tests\Feature\ApiToken;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ApiTokenResourceAuthorizationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_api_token_with_role_delete_cannot_delete_default_role(): void
    {
        $tenant = $this->createTenantWithRoles();
        $adminRole = $this->roleFor($tenant, DefaultRole::ADMINISTRATOR);

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenant)->create([
            'token_hash' => ApiToken::hash($plain),
            'permissions' => [Permission::ROLE_DELETE->value],
        ]);

        $this->deleteJson("/api/roles/{$adminRole->getKey()}", [], [
            'Authorization' => "Bearer {$plain}",
        ])->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $adminRole->getKey()]);
    }

    public function test_api_token_cannot_delete_role_from_other_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $foreignRole = Role::factory()->for($tenantB)->create(['name' => 'Financeiro']);

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenantA)->create([
            'token_hash' => ApiToken::hash($plain),
            'permissions' => [Permission::ROLE_DELETE->value],
        ]);

        $this->deleteJson("/api/roles/{$foreignRole->getKey()}", [], [
            'Authorization' => "Bearer {$plain}",
        ])->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $foreignRole->getKey()]);
    }

    public function test_api_token_cannot_delete_user_from_other_tenant(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        $foreignUser = User::factory()->for($tenantB)->create();

        $plain = ApiToken::PREFIX.Str::random(48);
        ApiToken::factory()->for($tenantA)->create([
            'token_hash' => ApiToken::hash($plain),
            'permissions' => [Permission::USER_DELETE->value],
        ]);

        $this->deleteJson("/api/users/{$foreignUser->uuid}", [], [
            'Authorization' => "Bearer {$plain}",
        ])->assertNotFound();

        $this->assertDatabaseHas('users', ['uuid' => $foreignUser->uuid]);
    }
}
