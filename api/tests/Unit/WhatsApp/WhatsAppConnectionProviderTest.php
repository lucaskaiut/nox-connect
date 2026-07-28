<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use App\Modules\WhatsApp\Infrastructure\Factories\WhatsAppConnectionProviderFactory;
use App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiConnectionProvider;
use App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaConnectionProvider;
use App\Modules\WhatsApp\Services\WhatsAppConnectionOwnership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WhatsAppConnectionProviderTest extends TestCase
{
    use RefreshDatabase;

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
            'whatsapp.d_api.connect_mode' => 'standard',
            'whatsapp.d_api.webhook_base_url' => null,
            'app.url' => 'http://localhost',
        ]);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $tenant = Tenant::factory()->create([
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $init = $provider->initialize($tenant)->toArray();

        $this->assertSame('sdk', $init['type']);
        $this->assertSame('d-api', $init['provider']);
        $this->assertSame('pk_live_test', $init['configuration']['publishable_key']);
        $this->assertSame('standard', $init['configuration']['mode']);
        $this->assertNotEmpty($init['configuration']['connection_nonce'] ?? null);
        // Localhost: webhook omitido (D-API não alcança).
        $this->assertArrayNotHasKey('webhook_url', $init);
        $this->assertNull($init['configuration']['webhook_url'] ?? null);
        $this->assertStringNotContainsString('sk_secret', json_encode($init));
    }

    public function test_d_api_initialize_includes_public_webhook_url(): void
    {
        config([
            'whatsapp.provider' => 'd-api',
            'whatsapp.credentials.public_key' => 'pk_live_test',
            'whatsapp.d_api.webhook_base_url' => 'https://saas.example.com',
        ]);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $tenant = Tenant::factory()->create([
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $init = $provider->initialize($tenant)->toArray();

        $expected = 'https://saas.example.com/api/webhooks/whatsapp/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $this->assertSame($expected, $init['webhook_url']);
        $this->assertSame($expected, $init['configuration']['webhook_url']);
    }

    public function test_d_api_complete_persists_connection_id_with_nonce(): void
    {
        config([
            'whatsapp.provider' => 'd-api',
            'whatsapp.credentials.secret_key' => 'sk_test',
            'whatsapp.d_api.base_url' => 'https://api.d-api.cloud',
            'whatsapp.d_api.webhook_base_url' => null,
            'app.url' => 'http://localhost',
        ]);

        $tenant = Tenant::factory()->create();
        $nonce = WhatsAppConnectionOwnership::beginConnect($tenant);

        Http::fake([
            'api.d-api.cloud/api/v1/sessions/conn_123' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
        ]);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);

        $result = $provider->complete($tenant, [
            'connectionId' => 'conn_123',
            'phoneNumber' => '5511999999999',
            'status' => 'connected',
            'connection_nonce' => $nonce,
        ]);

        $this->assertTrue($result->connected);
        $this->assertSame('conn_123', $result->settings['connection_id']);
        $this->assertSame('conn_123', $result->settings['session_id']);
        $this->assertSame('5511999999999', $result->settings['phone_number']);
        $this->assertSame('d-api', $result->settings['provider']);
    }

    public function test_d_api_complete_rejects_foreign_connection_id(): void
    {
        config(['whatsapp.provider' => 'd-api']);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create([
            'settings' => [
                'whatsapp' => [
                    'connection_id' => 'conn_owned_by_b',
                    'status' => 'connected',
                ],
            ],
        ]);

        $nonce = WhatsAppConnectionOwnership::beginConnect($tenantA);

        $this->expectException(ValidationException::class);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $provider->complete($tenantA, [
            'connection_id' => 'conn_owned_by_b',
            'connection_nonce' => $nonce,
        ]);

        unset($tenantB);
    }

    public function test_d_api_complete_rejects_missing_nonce(): void
    {
        config(['whatsapp.provider' => 'd-api']);

        $tenant = Tenant::factory()->create();

        $this->expectException(ValidationException::class);

        /** @var WhatsAppConnectionProvider $provider */
        $provider = app(WhatsAppConnectionProvider::class);
        $provider->complete($tenant, [
            'connection_id' => 'conn_no_nonce',
        ]);
    }
}
