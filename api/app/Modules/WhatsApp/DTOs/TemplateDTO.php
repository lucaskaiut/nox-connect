<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class TemplateDTO
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $status = null,
        public ?string $category = null,
        public ?string $language = null,
        public ?array $components = null,
        public array $raw = [],
    ) {}

    public static function fromProviderArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            name: $data['name'] ?? null,
            status: $data['status'] ?? null,
            category: $data['category'] ?? null,
            language: $data['language'] ?? null,
            components: $data['components'] ?? null,
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw !== [] ? $this->raw : array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'category' => $this->category,
            'language' => $this->language,
            'components' => $this->components,
        ], fn (mixed $v): bool => $v !== null);
    }
}
