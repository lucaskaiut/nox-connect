<?php

namespace App\Modules\Audit\Enums;

enum AuditAction: string
{
    case MasterLogin = 'master_login';
    case TenantSelected = 'tenant_selected';
    case TenantSwitched = 'tenant_switched';
    case WhatsAppConnected = 'whatsapp_connected';
    case WhatsAppDisconnected = 'whatsapp_disconnected';
    case OnboardingFinished = 'onboarding_finished';
    case ApiTokenCreated = 'api_token_created';
    case ApiTokenRevoked = 'api_token_revoked';
    case WebhookCreated = 'webhook_created';
    case WebhookUpdated = 'webhook_updated';
    case WebhookDeleted = 'webhook_deleted';
    case UserCreated = 'user_created';
    case UserDeleted = 'user_deleted';
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case RoleDeleted = 'role_deleted';
}
