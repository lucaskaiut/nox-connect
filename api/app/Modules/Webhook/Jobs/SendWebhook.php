<?php

namespace App\Modules\Webhook\Jobs;

use App\Modules\Shared\Support\UrlSecurityValidator;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SendWebhook implements ShouldQueue
{
    use Queueable;

    private const TIMEOUT_SECONDS = 10;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly Webhook $webhook,
        private readonly array $data,
    ) {}

    public function handle(UrlSecurityValidator $urlValidator): void
    {
        $method = strtoupper($this->webhook->method);
        $url = $this->webhook->url;

        try {
            $urlValidator->assertSafe($url);
        } catch (\InvalidArgumentException $e) {
            $this->logError([], $e->getMessage(), microtime(true));

            return;
        }

        $query = $this->webhook->query_params ?? [];
        $headers = $this->webhook->headers ?? [];

        if (! empty($query)) {
            $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?').http_build_query($query);
        }

        $payload = $this->buildPayload();

        if ($this->webhook->secret) {
            $headers['X-Webhook-Signature'] = $this->sign($payload);
        }

        $start = microtime(true);

        try {
            $request = Http::withHeaders($headers)
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->withOptions([
                    'allow_redirects' => false,
                ]);

            $response = match ($method) {
                'GET' => $request->get($url, $payload),
                'PUT' => $request->put($url, $payload),
                'PATCH' => $request->patch($url, $payload),
                'DELETE' => $request->delete($url, $payload),
                default => $request->post($url, $payload),
            };

            if ($response->redirect()) {
                $this->logError($payload, 'Redirecionamento HTTP não permitido.', $start);

                return;
            }

            $this->log($payload, $response, $start);
        } catch (ConnectionException|Throwable $e) {
            $this->logError($payload, $e->getMessage(), $start);
        }
    }

    private function buildPayload(): array
    {
        $template = $this->webhook->body_template;

        if (empty($template)) {
            return $this->defaultPayload();
        }

        return $this->resolveTemplate($template);
    }

    private function defaultPayload(): array
    {
        return [
            'event' => $this->webhook->event,
            'webhook' => $this->webhook->name,
            'data' => $this->data,
        ];
    }

    private function resolveTemplate(array $template): array
    {
        $json = json_encode($template);

        $replacements = [];
        foreach ($this->data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements["{{$key}}"] = (string) ($value ?? '');
            }
        }

        return json_decode(str_replace(array_keys($replacements), array_values($replacements), $json), true);
    }

    private function sign(array $payload): string
    {
        return 'sha256='.hash_hmac('sha256', json_encode($payload), $this->webhook->secret);
    }

    private function log(array $payload, Response $response, float $start): void
    {
        WebhookLog::query()->create([
            'webhook_id' => $this->webhook->id,
            'status_code' => $response->status(),
            'response_body' => Str::limit($response->body(), 500),
            'request_payload' => $payload,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    private function logError(array $payload, string $message, float $start): void
    {
        WebhookLog::query()->create([
            'webhook_id' => $this->webhook->id,
            'request_payload' => $payload,
            'error_message' => Str::limit($message, 500),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }
}
