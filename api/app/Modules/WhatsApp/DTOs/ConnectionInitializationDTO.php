<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class ConnectionInitializationDTO
{
    /**
     * @param  array<string, mixed>  $configuration  Dados públicos para o frontend (nunca secrets)
     */
    public function __construct(
        public string $type,
        public string $provider,
        public array $configuration = [],
        public ?string $webhookUrl = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'provider' => $this->provider,
            'configuration' => $this->configuration,
            'webhook_url' => $this->webhookUrl,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
