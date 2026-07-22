<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $whatsappPermissions = [
            Permission::WHATSAPP_CONFIG_CREATE,
            Permission::WHATSAPP_CONFIG_READ,
            Permission::WHATSAPP_CONFIG_UPDATE,
            Permission::WHATSAPP_CONFIG_DELETE,
            Permission::WHATSAPP_CONVERSATION_READ,
            Permission::WHATSAPP_CONVERSATION_UPDATE,
            Permission::WHATSAPP_TAG_CREATE,
            Permission::WHATSAPP_TAG_READ,
            Permission::WHATSAPP_TAG_UPDATE,
            Permission::WHATSAPP_TAG_DELETE,
            Permission::WHATSAPP_KANBAN_READ,
            Permission::WHATSAPP_KANBAN_UPDATE,
        ];

        $roles = Role::query()->with('tenant')->get();

        foreach ($roles as $role) {
            $role->grantPermissions(...$whatsappPermissions);
        }
    }

    public function down(): void
    {
        $whatsappPermissions = [
            Permission::WHATSAPP_CONFIG_CREATE,
            Permission::WHATSAPP_CONFIG_READ,
            Permission::WHATSAPP_CONFIG_UPDATE,
            Permission::WHATSAPP_CONFIG_DELETE,
            Permission::WHATSAPP_CONVERSATION_READ,
            Permission::WHATSAPP_CONVERSATION_UPDATE,
            Permission::WHATSAPP_TAG_CREATE,
            Permission::WHATSAPP_TAG_READ,
            Permission::WHATSAPP_TAG_UPDATE,
            Permission::WHATSAPP_TAG_DELETE,
            Permission::WHATSAPP_KANBAN_READ,
            Permission::WHATSAPP_KANBAN_UPDATE,
        ];

        $roles = Role::query()->with('tenant')->get();

        foreach ($roles as $role) {
            $role->revokePermissions(...$whatsappPermissions);
        }
    }
};
