<?php

namespace App\Modules\Webhook\Jobs;

use App\Modules\Post\Models\Post;
use App\Modules\Webhook\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SendWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Webhook $webhook,
        private readonly Post $post,
    ) {}

    public function handle(): void
    {
        $method = strtoupper($this->webhook->method);
        $url = $this->webhook->url;
        $query = $this->webhook->query_params ?? [];
        $headers = $this->webhook->headers ?? [];

        if (! empty($query)) {
            $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . http_build_query($query);
        }

        $payload = $this->buildPayload();

        if ($this->webhook->secret) {
            $headers['X-Webhook-Signature'] = $this->sign($payload);
        }

        $request = Http::withHeaders($headers)->timeout(30)->connectTimeout(10);

        match ($method) {
            'GET' => $request->get($url, $payload),
            'PUT' => $request->put($url, $payload),
            'PATCH' => $request->patch($url, $payload),
            'DELETE' => $request->delete($url, $payload),
            default => $request->post($url, $payload),
        };
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
            'data' => $this->post->toArray(),
        ];
    }

    private function resolveTemplate(array $template): array
    {
        $data = $this->post->toArray();
        $json = json_encode($template);

        $replacements = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements["{{$key}}"] = (string) ($value ?? '');
            }
        }

        return json_decode(str_replace(array_keys($replacements), array_values($replacements), $json), true);
    }

    private function sign(array $payload): string
    {
        return 'sha256=' . hash_hmac('sha256', json_encode($payload), $this->webhook->secret);
    }
}
