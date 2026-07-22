<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Models\WhatsAppWebhookLog;
use App\Modules\WhatsApp\Services\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaWebhookController extends ApiController
{
    public function __construct(
        private readonly WhatsAppWebhookService $webhookService,
    ) {}

    public function receive(Request $request, WhatsAppConfig $config): JsonResponse
    {
        $start = microtime(true);

        try {
            if ($request->isMethod('get')) {
                $response = $this->verifyWebhook($request, $config);

                $this->logRequest($request, $config, $response->getStatusCode(), (string) $response->getContent(), $start);

                return $response;
            }

            $this->webhookService->handleWebhook($config, $request->all());

            $this->logRequest($request, $config, 200, '{"status":"ok"}', $start);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            $this->logError($request, $config, $e, $start);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function verifyWebhook(Request $request, WhatsAppConfig $config): \Illuminate\Http\Response|JsonResponse
    {
        $mode = $request->query->get('hub.mode');
        $token = $request->query->get('hub.verify_token');
        $challenge = $request->query->get('hub.challenge');

        if ($mode === 'subscribe' && $token === $config->verify_token) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['status' => 'forbidden'], 403);
    }

    private function logRequest(Request $request, WhatsAppConfig $config, int $status, string $body, float $start): void
    {
        WhatsAppWebhookLog::query()->create([
            'whatsapp_config_id' => $config->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_headers' => $request->headers->all(),
            'request_payload' => $request->all(),
            'response_status' => $status,
            'response_body' => $body,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    private function logError(Request $request, WhatsAppConfig $config, \Throwable $e, float $start): void
    {
        WhatsAppWebhookLog::query()->create([
            'whatsapp_config_id' => $config->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_headers' => $request->headers->all(),
            'request_payload' => $request->all(),
            'response_status' => 500,
            'response_body' => '{"status":"error"}',
            'error_message' => $e->getMessage(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }
}
