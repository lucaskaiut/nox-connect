<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\WhatsApp\DTOs\CreateTemplateDTO;
use App\Modules\WhatsApp\DTOs\DeleteTemplatesDTO;
use App\Modules\WhatsApp\DTOs\UpdateTemplateDTO;
use App\Modules\WhatsApp\Infrastructure\Factories\WhatsAppProviderFactory;

class MessageTemplateService
{
    public function __construct(
        private readonly WhatsAppProviderFactory $providerFactory,
    ) {}

    public function list(array $params = []): array
    {
        $catalog = $this->providerFactory->templateCatalog();
        $result = $catalog->listTemplates($this->tenant(), $params);

        if ($result->error !== null) {
            return [
                'error' => $result->error->raw !== [] ? $result->error->raw : [
                    'message' => $result->error->message,
                    'code' => $result->error->code,
                ],
            ];
        }

        return [
            'data' => array_map(fn ($t) => $t->toArray(), $result->templates),
            'paging' => $result->paging,
        ];
    }

    public function get(string $templateId, ?string $fields = null): array
    {
        return $this->providerFactory->templateCatalog()
            ->getTemplate($this->tenant(), $templateId, $fields)
            ->toArray();
    }

    public function create(array $data): array
    {
        return $this->providerFactory->templateCatalog()
            ->createTemplate($this->tenant(), new CreateTemplateDTO($data));
    }

    public function update(string $templateId, array $data): array
    {
        return $this->providerFactory->templateCatalog()
            ->updateTemplate($this->tenant(), $templateId, new UpdateTemplateDTO($data));
    }

    public function delete(array $params): array
    {
        return $this->providerFactory->templateCatalog()
            ->deleteTemplates($this->tenant(), new DeleteTemplatesDTO($params));
    }

    private function tenant(): Tenant
    {
        return TenantContext::tenant();
    }
}
