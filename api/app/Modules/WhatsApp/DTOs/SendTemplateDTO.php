<?php

namespace App\Modules\WhatsApp\DTOs;

use App\Modules\Tenant\Models\Tenant;

final readonly class SendTemplateDTO
{
    /**
     * @param  list<string>  $variables
     */
    public function __construct(
        public Tenant $tenant,
        public string $to,
        public string $templateName,
        public string $language,
        public array $variables = [],
    ) {}
}
