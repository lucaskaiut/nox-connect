<?php

namespace App\Modules\WhatsApp\Contracts;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\CreateTemplateDTO;
use App\Modules\WhatsApp\DTOs\DeleteTemplatesDTO;
use App\Modules\WhatsApp\DTOs\TemplateDTO;
use App\Modules\WhatsApp\DTOs\TemplateListResultDTO;
use App\Modules\WhatsApp\DTOs\UpdateTemplateDTO;

interface WhatsAppTemplateCatalog
{
    /**
     * @param  array{fields?: string, limit?: int, after?: string, before?: string}  $params
     */
    public function listTemplates(Tenant $tenant, array $params = []): TemplateListResultDTO;

    public function getTemplate(Tenant $tenant, string $externalTemplateId, ?string $fields = null): TemplateDTO;

    public function createTemplate(Tenant $tenant, CreateTemplateDTO $dto): array;

    public function updateTemplate(Tenant $tenant, string $externalTemplateId, UpdateTemplateDTO $dto): array;

    public function deleteTemplates(Tenant $tenant, DeleteTemplatesDTO $dto): array;
}
