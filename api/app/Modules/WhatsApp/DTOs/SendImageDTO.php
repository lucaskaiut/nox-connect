<?php

namespace App\Modules\WhatsApp\DTOs;

use App\Modules\Tenant\Models\Tenant;

final readonly class SendImageDTO
{
    public function __construct(
        public Tenant $tenant,
        public string $to,
        public string $mediaId,
        public ?string $caption = null,
    ) {}
}
