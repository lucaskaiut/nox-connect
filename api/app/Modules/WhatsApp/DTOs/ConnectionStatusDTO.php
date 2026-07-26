<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class ConnectionStatusDTO
{
    public function __construct(
        public bool $connected,
        public ?string $message = null,
        public array $details = [],
    ) {}
}
