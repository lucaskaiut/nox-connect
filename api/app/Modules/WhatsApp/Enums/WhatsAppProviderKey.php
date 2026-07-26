<?php

namespace App\Modules\WhatsApp\Enums;

enum WhatsAppProviderKey: string
{
    case Meta = 'meta';
    case DApi = 'd-api';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Meta Cloud API',
            self::DApi => 'D-API',
        };
    }
}
