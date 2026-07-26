<?php

namespace App\Modules\WhatsApp\Infrastructure\Webhooks;

use App\Modules\WhatsApp\Contracts\WebhookNormalizer;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

final class WebhookNormalizerRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function forActiveProvider(): WebhookNormalizer
    {
        $key = (string) config('whatsapp.provider', 'meta');
        $map = config('whatsapp.webhook_normalizers', []);

        if (! isset($map[$key]) || ! is_string($map[$key])) {
            throw new InvalidArgumentException("Normalizer de webhook para [{$key}] não está registrado.");
        }

        $normalizer = $this->container->make($map[$key]);

        if (! $normalizer instanceof WebhookNormalizer) {
            throw new RuntimeException("A classe {$map[$key]} deve implementar WebhookNormalizer.");
        }

        return $normalizer;
    }
}
