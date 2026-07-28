<?php

namespace App\Modules\WhatsApp\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class WebhookLogSanitizer
{
    /** @var list<string> */
    private const SENSITIVE_HEADER_NAMES = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-webhook-secret',
        'proxy-authorization',
    ];

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, list<string|null>>
     */
    public function redactHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $lower = strtolower($name);

            if (in_array($lower, self::SENSITIVE_HEADER_NAMES, true)) {
                $sanitized[$name] = ['[REDACTED]'];

                continue;
            }

            $sanitized[$name] = $values;
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizePayload(Request $request): array
    {
        return $this->sanitizeValue($request->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeForApi(array $headers, array $payload, ?string $errorMessage): array
    {
        return [
            'payload_keys' => array_keys($payload),
            'payload_summary' => $this->buildPayloadSummary($payload),
            'has_sensitive_headers' => $this->hasSensitiveHeaders($headers),
            'error_present' => filled($errorMessage),
        ];
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     */
    private function hasSensitiveHeaders(array $headers): bool
    {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower($name), self::SENSITIVE_HEADER_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_string($value) && strlen($value) > 500
                ? Str::limit($value, 500, '…')
                : $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $keyString = (string) $key;

            if ($this->isMessageBodyKey($keyString)) {
                $sanitized[$keyString] = $this->summarizeMessageBody($item);

                continue;
            }

            $sanitized[$keyString] = is_array($item)
                ? $this->sanitizeValue($item)
                : $this->sanitizeScalar($item, $keyString);
        }

        return $sanitized;
    }

    private function isMessageBodyKey(string $key): bool
    {
        $lower = strtolower($key);

        return in_array($lower, [
            'body',
            'text',
            'message',
            'content',
            'caption',
            'conversation',
            'messages',
        ], true);
    }

    /**
     * @return array<string, mixed>|string
     */
    private function summarizeMessageBody(mixed $value): array|string
    {
        if (is_string($value)) {
            return '[omitted:'.strlen($value).' chars]';
        }

        if (! is_array($value)) {
            return '[omitted]';
        }

        $summary = [];

        foreach (['id', 'type', 'message_id', 'messageId', 'from', 'to', 'timestamp', 'status'] as $field) {
            if (array_key_exists($field, $value)) {
                $summary[$field] = $value[$field];
            }
        }

        if ($summary === []) {
            $summary['keys'] = array_keys($value);
        }

        return $summary;
    }

    private function sanitizeScalar(mixed $value, string $key): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (strlen($value) > 500) {
            return Str::limit($value, 500, '…');
        }

        if (in_array(strtolower($key), ['token', 'secret', 'password', 'authorization'], true)) {
            return '[REDACTED]';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildPayloadSummary(array $payload): array
    {
        $summary = ['top_level_keys' => array_keys($payload)];

        foreach (['event', 'type', 'instance', 'instanceId', 'messageId', 'id', 'status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $summary[$field] = $payload[$field];
            }
        }

        return $summary;
    }
}
