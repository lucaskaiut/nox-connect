<?php

namespace Tests\Feature\Shared;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FileUploadAuthorizationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_member_without_media_upload_permission_gets_forbidden(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createMember($tenant));

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();
    }

    public function test_admin_with_media_upload_permission_can_upload(): void
    {
        $tenant = $this->createTenantWithRoles();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['url', 'path']]);
    }

    public function test_custom_role_with_media_upload_can_upload(): void
    {
        $tenant = $this->createTenantWithRoles();

        $role = Role::factory()->for($tenant)->create(['name' => 'Uploader']);
        $role->grantPermissions(Permission::MEDIA_UPLOAD);

        $user = \App\Modules\User\Models\User::factory()->for($tenant)->create();
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated();
    }
}
