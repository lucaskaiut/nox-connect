<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class ConnectionResultDTO
{
    /**
     * @param  array<string, mixed>  $settings  Identificadores a mesclar em tenant.settings.whatsapp
     */
    public function __construct(
        public array $settings,
        public bool $connected = true,
        public ?string $message = null,
    ) {}
}
