<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\WhatsApp\DTOs\WebhookResultDTO;
use App\Modules\WhatsApp\Services\WhatsAppWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 60];

    public function __construct(
        public readonly int $tenantId,
        public readonly WebhookResultDTO $result,
    ) {
        $this->onQueue('whatsapp-webhooks');
    }

    public function handle(WhatsAppWebhookService $webhookService): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('[WhatsApp] Webhook job skipped: tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        TenantContext::set($tenant);

        try {
            $webhookService->handleNormalized($tenant, $this->result);
        } finally {
            TenantContext::forget();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('[WhatsApp] Webhook job failed', [
            'tenant_id' => $this->tenantId,
            'message' => $exception?->getMessage(),
            'messages_count' => count($this->result->messages),
            'statuses_count' => count($this->result->statuses),
        ]);
    }
}
