<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class MessageResultDTO
{
    public function __construct(
        public ?string $externalMessageId = null,
        public bool $success = false,
        public ?ProviderErrorDTO $error = null,
        public array $raw = [],
    ) {}

    public static function success(string $externalMessageId, array $raw = []): self
    {
        return new self(
            externalMessageId: $externalMessageId,
            success: true,
            raw: $raw,
        );
    }

    public static function failure(?ProviderErrorDTO $error = null, array $raw = []): self
    {
        return new self(
            externalMessageId: null,
            success: false,
            error: $error,
            raw: $raw,
        );
    }
}
