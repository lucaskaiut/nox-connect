<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class WebhookChallengeDTO
{
    public function __construct(
        public bool $valid,
        public ?string $challenge = null,
        public int $status = 200,
    ) {}
}
