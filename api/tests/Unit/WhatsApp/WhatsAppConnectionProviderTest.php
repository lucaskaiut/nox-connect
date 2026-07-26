<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use App\Modules\WhatsApp\Infrastructure\Factories\WhatsAppConnectionProviderFactory;
use App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiConnectionProvider;
use App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaConnectionProvider;
use Tests\TestCase;

class WhatsAppConnectionProviderTest extends TestCase
{
    public function test_factory_resolves_d_api_connection_provider(): void
    {
        config([
            'whatsapp.provider' => 'd-api',
            'whatsapp.credentials.public_key' => 'pk_test',
        ]);

        $provider = app(WhatsAppConnectionProviderFactory::class)->make();

        $this->assertInstanceOf(DApiConnectionProvider::class, $provider);
        $this->assertSame('d-api', $provider->key());
    }

    public function test_factory_resolves_meta_connection_provider(): void
    {
        config(['whatsapp.provider' => 'meta']);

        $provider = app(WhatsAppConnectionProviderFactory::class)->make();

        $this->assertInstanceOf(MetaConnectionProvider::class, $provider);
    }

    public function test_d_api_initialize_returns_sdk_bootstrap_without_secrets(): void
    {
        config([
            'whatsapp.provider' => 'd-api',
            'whatsapp.credentials.public_key' => 'pk_live_test',
            'whatsapp.credentials.secret_key' => 'sk_secret_must_not_leak',
            'whatsapp.d_api.connect_base_url' => 'https://connect.d-api.cloud',
            'whatsapp.d_api.webhook_base_url' => null,
            'app.url' => 'http://localhost',
        ]);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $tenant = new Tenant;
        $tenant->forceFill(['uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);

        $init = $provider->initialize($tenant)->toArray();

        $this->assertSame('sdk', $init['type']);
        $this->assertSame('d-api', $init['provider']);
        $this->assertSame('pk_live_test', $init['configuration']['publishable_key']);
        $this->assertSame('standard', $init['configuration']['mode']);
        // Webhook não vai no SDK — só pending para registro pós-complete.
        $this->assertArrayNotHasKey('webhook_url', $init);
        $this->assertNull($init['configuration']['pending_webhook_url'] ?? null);
        $this->assertStringNotContainsString('sk_secret', json_encode($init));
    }

    public function test_d_api_initialize_includes_pending_public_webhook_url(): void
    {
        config([
            'whatsapp.provider' => 'd-api',
            'whatsapp.credentials.public_key' => 'pk_live_test',
            'whatsapp.d_api.webhook_base_url' => 'https://saas.example.com',
        ]);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $tenant = new Tenant;
        $tenant->forceFill(['uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);

        $init = $provider->initialize($tenant)->toArray();

        $this->assertArrayNotHasKey('webhook_url', $init);
        $this->assertSame(
            'https://saas.example.com/api/webhooks/whatsapp/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            $init['configuration']['pending_webhook_url'],
        );
    }

    public function test_d_api_complete_persists_connection_id(): void
    {
        config(['whatsapp.provider' => 'd-api']);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $tenant = new Tenant(['uuid' => 'tenant-uuid']);

        $result = $provider->complete($tenant, [
            'connectionId' => 'conn_123',
            'phoneNumber' => '5511999999999',
            'status' => 'connected',
        ]);

        $this->assertTrue($result->connected);
        $this->assertSame('conn_123', $result->settings['connection_id']);
        $this->assertSame('conn_123', $result->settings['session_id']);
        $this->assertSame('5511999999999', $result->settings['phone_number']);
        $this->assertSame('d-api', $result->settings['provider']);
    }
}
