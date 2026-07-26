<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class CreateTemplateDTO
{
    /**
     * Payload neutro; o adapter mapeia para o formato do provedor.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
