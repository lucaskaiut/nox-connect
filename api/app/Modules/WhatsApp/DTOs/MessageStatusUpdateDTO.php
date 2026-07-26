<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class MessageStatusUpdateDTO
{
    public function __construct(
        public string $externalMessageId,
        public string $status,
        public ?\DateTimeInterface $occurredAt = null,
        public ?ProviderErrorDTO $error = null,
        public array $raw = [],
    ) {}
}
