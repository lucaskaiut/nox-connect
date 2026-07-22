<?php

namespace App\Modules\ACL\Enums;

enum Permission: string
{
    case USER_CREATE = 'user.create';
    case USER_READ = 'user.read';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';

    case TENANT_READ = 'tenant.read';
    case TENANT_UPDATE = 'tenant.update';

    case ROLE_CREATE = 'role.create';
    case ROLE_READ = 'role.read';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';

    case API_TOKEN_CREATE = 'api-token.create';
    case API_TOKEN_READ = 'api-token.read';
    case API_TOKEN_DELETE = 'api-token.delete';

    case WEBHOOK_CREATE = 'webhook.create';
    case WEBHOOK_READ = 'webhook.read';
    case WEBHOOK_UPDATE = 'webhook.update';
    case WEBHOOK_DELETE = 'webhook.delete';

    case WHATSAPP_CONFIG_CREATE = 'whatsapp-config.create';
    case WHATSAPP_CONFIG_READ = 'whatsapp-config.read';
    case WHATSAPP_CONFIG_UPDATE = 'whatsapp-config.update';
    case WHATSAPP_CONFIG_DELETE = 'whatsapp-config.delete';

    case WHATSAPP_CONVERSATION_READ = 'whatsapp.conversation.read';
    case WHATSAPP_CONVERSATION_UPDATE = 'whatsapp.conversation.update';

    case WHATSAPP_TAG_CREATE = 'whatsapp.tag.create';
    case WHATSAPP_TAG_READ = 'whatsapp.tag.read';
    case WHATSAPP_TAG_UPDATE = 'whatsapp.tag.update';
    case WHATSAPP_TAG_DELETE = 'whatsapp.tag.delete';

    case WHATSAPP_KANBAN_READ = 'whatsapp.kanban.read';
    case WHATSAPP_KANBAN_UPDATE = 'whatsapp.kanban.update';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
