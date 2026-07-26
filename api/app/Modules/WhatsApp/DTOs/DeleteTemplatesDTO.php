<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class DeleteTemplatesDTO
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function __construct(
        public array $criteria,
    ) {}
}
