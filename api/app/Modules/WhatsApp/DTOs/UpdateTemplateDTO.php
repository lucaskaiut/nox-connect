<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class UpdateTemplateDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
