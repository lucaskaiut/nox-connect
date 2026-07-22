<?php

namespace App\Modules\WhatsApp\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
