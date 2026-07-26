<?php

namespace App\Modules\WhatsApp\Enums;

enum TemplateStatus: string
{
    case Approved = 'APPROVED';
    case Archived = 'ARCHIVED';
    case Deleted = 'DELETED';
    case Disabled = 'DISABLED';
    case InAppeal = 'IN_APPEAL';
    case LimitExceeded = 'LIMIT_EXCEEDED';
    case Paused = 'PAUSED';
    case Pending = 'PENDING';
    case PendingDeletion = 'PENDING_DELETION';
    case Rejected = 'REJECTED';
}
