<?php

namespace App\Modules\Webhook\Support;

final class WebhookHeaderRedactor
{
    /** @var list<string> */
    private const SENSITIVE_NAMES = [
        'authorization',
        'x-api-key',
        'x-auth-token',
        'x-webhook-secret',
        'proxy-authorization',
        'cookie',
        'set-cookie',
    ];

    /**
     * @param  array<string, mixed>|null  $headers
     * @return array<string, mixed>|null
     */
    public function redact(?array $headers): ?array
    {
        if ($headers === null || $headers === []) {
            return $headers;
        }

        $redacted = [];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_NAMES, true)) {
                $redacted[$name] = '[REDACTED]';

                continue;
            }

            $redacted[$name] = $value;
        }

        return $redacted;
    }
}
