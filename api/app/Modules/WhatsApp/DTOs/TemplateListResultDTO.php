<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class TemplateListResultDTO
{
    /**
     * @param  list<TemplateDTO>  $templates
     */
    public function __construct(
        public array $templates = [],
        public ?array $paging = null,
        public ?ProviderErrorDTO $error = null,
        public array $raw = [],
    ) {}
}
