<?php

namespace App\Modules\WhatsApp\Infrastructure\Factories;

use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\Contracts\WhatsAppTemplateCatalog;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

final class WhatsAppProviderFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(): WhatsAppProvider
    {
        $key = (string) config('whatsapp.provider', 'meta');
        $class = $this->classFor($key);

        $provider = $this->container->make($class);

        if (! $provider instanceof WhatsAppProvider) {
            throw new RuntimeException("A classe {$class} deve implementar WhatsAppProvider.");
        }

        if ($provider->key() !== $key) {
            throw new RuntimeException(
                "Provider [{$class}] retornou key [{$provider->key()}] diferente de [{$key}]."
            );
        }

        return $provider;
    }

    public function templateCatalog(): WhatsAppTemplateCatalog
    {
        $provider = $this->make();

        if (! $provider instanceof WhatsAppTemplateCatalog) {
            throw new InvalidArgumentException(
                "O provedor [{$provider->key()}] não suporta catálogo de templates."
            );
        }

        return $provider;
    }

    public function classFor(string $key): string
    {
        $map = config('whatsapp.providers', []);

        if (! isset($map[$key]) || ! is_string($map[$key])) {
            throw new InvalidArgumentException("Provedor WhatsApp [{$key}] não está registrado.");
        }

        if (! class_exists($map[$key])) {
            throw new RuntimeException("Classe do provedor WhatsApp [{$map[$key]}] não encontrada.");
        }

        return $map[$key];
    }
}
