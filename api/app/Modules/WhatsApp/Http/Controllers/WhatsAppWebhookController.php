<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Infrastructure\Webhooks\WebhookNormalizerRegistry;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppWebhookJob;
use App\Modules\WhatsApp\Models\WhatsAppWebhookLog;
use App\Modules\WhatsApp\Support\WebhookLogSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends ApiController
{
    public function __construct(
        private readonly WebhookNormalizerRegistry $normalizerRegistry,
        private readonly WebhookLogSanitizer $logSanitizer,
    ) {}

    public function receive(Request $request, string $tenantUuid): \Illuminate\Http\Response|JsonResponse
    {
        $start = microtime(true);

        $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();

        if (! $tenant) {
            return response()->json(['status' => 'not_found'], 404);
        }

        try {
            $normalizer = $this->normalizerRegistry->forActiveProvider();

            if ($request->isMethod('get')) {
                $challenge = $normalizer->verify($tenant, $request);

                if ($challenge === null) {
                    $response = response()->json(['status' => 'forbidden'], 403);
                } elseif ($challenge->valid) {
                    $response = response($challenge->challenge ?? '', $challenge->status)
                        ->header('Content-Type', 'text/plain');
                } else {
                    $response = response()->json(['status' => 'forbidden'], $challenge->status);
                }

                $this->tryLog(fn () => $this->logRequest($request, $tenant, $response->getStatusCode(), (string) $response->getContent(), $start));

                return $response;
            }

            $normalized = $normalizer->normalize($tenant, $request);

            ProcessWhatsAppWebhookJob::dispatch($tenant->id, $normalized);

            $this->tryLog(fn () => $this->logRequest($request, $tenant, 200, '{"status":"accepted"}', $start));

            return response()->json(['status' => 'accepted'], 200);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] Webhook error', [
                'tenant_uuid' => $tenantUuid,
                'message' => $e->getMessage(),
            ]);

            $this->tryLog(fn () => $this->logError($request, $tenant, $e, $start));

            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    private function logRequest(Request $request, Tenant $tenant, int $status, string $body, float $start): void
    {
        WhatsAppWebhookLog::query()->create([
            'tenant_id' => $tenant->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_headers' => $this->logSanitizer->redactHeaders($request->headers->all()),
            'request_payload' => $this->logSanitizer->sanitizePayload($request),
            'response_status' => $status,
            'response_body' => $body,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    private function logError(Request $request, Tenant $tenant, \Throwable $e, float $start): void
    {
        WhatsAppWebhookLog::query()->create([
            'tenant_id' => $tenant->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_headers' => $this->logSanitizer->redactHeaders($request->headers->all()),
            'request_payload' => $this->logSanitizer->sanitizePayload($request),
            'response_status' => 500,
            'response_body' => '{"status":"error"}',
            'error_message' => $e->getMessage(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    private function tryLog(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable) {
        }
    }
}
