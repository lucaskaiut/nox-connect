<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use App\Modules\WhatsApp\Infrastructure\Factories\WhatsAppProviderFactory;
use App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiProvider;
use App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaCloudProvider;
use Tests\TestCase;

class WhatsAppProviderFactoryTest extends TestCase
{
    public function test_make_resolves_provider_from_global_config(): void
    {
        config(['whatsapp.provider' => 'meta']);

        $provider = app(WhatsAppProviderFactory::class)->make();

        $this->assertInstanceOf(MetaCloudProvider::class, $provider);
        $this->assertSame(WhatsAppProviderKey::Meta->value, $provider->key());
    }

    public function test_make_resolves_d_api_provider(): void
    {
        config(['whatsapp.provider' => 'd-api']);

        $provider = app(WhatsAppProviderFactory::class)->make();

        $this->assertInstanceOf(DApiProvider::class, $provider);
        $this->assertSame(WhatsAppProviderKey::DApi->value, $provider->key());
    }

    public function test_container_binds_whatsapp_provider_interface(): void
    {
        config(['whatsapp.provider' => 'meta']);

        $provider = app(WhatsAppProvider::class);

        $this->assertInstanceOf(MetaCloudProvider::class, $provider);
    }

    public function test_unknown_provider_throws(): void
    {
        config(['whatsapp.provider' => 'unknown_bsp']);

        $this->expectException(\InvalidArgumentException::class);

        app(WhatsAppProviderFactory::class)->make();
    }
}
