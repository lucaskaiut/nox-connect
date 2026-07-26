<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $templatePermissions = [
            Permission::WHATSAPP_TEMPLATE_CREATE,
            Permission::WHATSAPP_TEMPLATE_READ,
            Permission::WHATSAPP_TEMPLATE_UPDATE,
            Permission::WHATSAPP_TEMPLATE_DELETE,
        ];

        $roles = Role::query()->with('tenant')->get();

        foreach ($roles as $role) {
            $role->grantPermissions(...$templatePermissions);
        }
    }

    public function down(): void
    {
        $templatePermissions = [
            Permission::WHATSAPP_TEMPLATE_CREATE,
            Permission::WHATSAPP_TEMPLATE_READ,
            Permission::WHATSAPP_TEMPLATE_UPDATE,
            Permission::WHATSAPP_TEMPLATE_DELETE,
        ];

        $roles = Role::query()->with('tenant')->get();

        foreach ($roles as $role) {
            $role->revokePermissions(...$templatePermissions);
        }
    }
};
