<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DApiProviderSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_connection_rejects_client_supplied_session_id(): void
    {
        config([
            'whatsapp.dapi.secret_key' => 'sk_test',
        ]);

        $provider = app(DApiProvider::class);
        $tenant = Tenant::factory()->create();

        $result = $provider->createConnection($tenant, ['session_id' => 'evil-session']);

        $this->assertFalse($result->connected);
        $this->assertStringContainsString('session_id', strtolower($result->message ?? ''));
    }

    public function test_create_connection_rejects_client_supplied_connection_id(): void
    {
        config([
            'whatsapp.dapi.secret_key' => 'sk_test',
        ]);

        $provider = app(DApiProvider::class);
        $tenant = Tenant::factory()->create();

        $result = $provider->createConnection($tenant, ['connection_id' => 'evil-connection']);

        $this->assertFalse($result->connected);
    }

    public function test_ulid_is_opaque_and_not_predictable_nox_prefix(): void
    {
        $sessionId = (string) Str::ulid();

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $sessionId);
        $this->assertFalse(str_starts_with($sessionId, 'nox-'));
    }
}
