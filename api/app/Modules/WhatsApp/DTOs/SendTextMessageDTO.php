<?php

namespace App\Modules\WhatsApp\DTOs;

use App\Modules\Tenant\Models\Tenant;

final readonly class SendTextMessageDTO
{
    public function __construct(
        public Tenant $tenant,
        public string $to,
        public string $body,
        public ?bool $previewUrl = null,
    ) {}
}
