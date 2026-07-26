<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class ProviderErrorDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $type = null,
        public ?string $message = null,
        public ?string $subcode = null,
        public ?string $traceId = null,
        public ?array $data = null,
        public array $raw = [],
    ) {}

    public static function fromArray(array $error): self
    {
        return new self(
            code: isset($error['code']) ? (string) $error['code'] : null,
            type: isset($error['type']) ? (string) $error['type'] : null,
            message: $error['message'] ?? $error['error_user_msg'] ?? 'Erro desconhecido do provedor',
            subcode: isset($error['error_subcode']) ? (string) $error['error_subcode'] : null,
            traceId: $error['fbtrace_id'] ?? $error['trace_id'] ?? $error['provider_trace_id'] ?? null,
            data: $error['error_data'] ?? null,
            raw: $error,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return array_filter([
            'error_code' => $this->code,
            'error_type' => $this->type,
            'error_message' => $this->message,
            'error_subcode' => $this->subcode,
            'provider_trace_id' => $this->traceId,
            'error_data' => $this->data,
        ], fn (mixed $value): bool => $value !== null);
    }
}
