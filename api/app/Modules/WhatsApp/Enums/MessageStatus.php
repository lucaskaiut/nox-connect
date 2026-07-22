<?php

namespace App\Modules\WhatsApp\Enums;

enum MessageStatus: string
{
    case Received = 'received';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
}
