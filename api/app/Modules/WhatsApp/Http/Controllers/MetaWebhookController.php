<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
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
        if ($request->isMethod('get')) {
            return $this->verifyWebhook($request, $config);
        }

        $this->webhookService->handleWebhook($config, $request->all());

        return response()->json(['status' => 'ok']);
    }

    private function verifyWebhook(Request $request, WhatsAppConfig $config): JsonResponse
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $config->verify_token) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['status' => 'forbidden'], 403);
    }
}
