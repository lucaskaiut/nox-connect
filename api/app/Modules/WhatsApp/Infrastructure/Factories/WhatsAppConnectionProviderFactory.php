<?php

namespace App\Modules\WhatsApp\Infrastructure\Factories;

use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

final class WhatsAppConnectionProviderFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(): WhatsAppConnectionProvider
    {
        $key = (string) config('whatsapp.provider', 'meta');
        $map = config('whatsapp.connection_providers', []);

        if (! isset($map[$key]) || ! is_string($map[$key])) {
            throw new InvalidArgumentException("Connection provider WhatsApp [{$key}] não está registrado.");
        }

        $provider = $this->container->make($map[$key]);

        if (! $provider instanceof WhatsAppConnectionProvider) {
            throw new RuntimeException("A classe {$map[$key]} deve implementar WhatsAppConnectionProvider.");
        }

        return $provider;
    }
}
