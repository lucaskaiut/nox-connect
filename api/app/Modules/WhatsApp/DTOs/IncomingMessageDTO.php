<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class IncomingMessageDTO
{
    /**
     * @param  array{id?: string|null, mime_type?: string|null, sha256?: string|null, caption?: string|null}|null  $media
     */
    public function __construct(
        public string $externalMessageId,
        public string $externalContactId,
        public string $messageType,
        public ?string $content = null,
        public ?array $media = null,
        public ?string $profileName = null,
        public ?\DateTimeInterface $receivedAt = null,
        public array $raw = [],
    ) {}
}
