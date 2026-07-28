<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\SendTextMessageDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TenantWhatsappSettingsTest extends TestCase
{
    use RefreshDatabase;
    public function test_whatsapp_settings_helpers(): void
    {
        $tenant = new Tenant([
            'settings' => [
                'whatsapp' => [
                    'session_id' => 'sess-1',
                ],
            ],
        ]);

        $this->assertSame('sess-1', $tenant->whatsappSetting('session_id'));
        $this->assertTrue($tenant->isWhatsappConnected());
    }

    public function test_merge_whatsapp_settings_encrypts_sensitive_keys(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => [
                'whatsapp' => [
                    'session_id' => 'sess-1',
                    'webhook_verify_token' => 'legacy-plaintext',
                ],
            ],
        ]);

        $tenant->mergeWhatsappSettings([
            'webhook_verify_token' => 'new-secret-token',
            'access_token' => 'access-123',
        ]);

        $raw = $tenant->fresh()->settings['whatsapp'];

        $this->assertNotSame('new-secret-token', $raw['webhook_verify_token']);
        $this->assertNotSame('access-123', $raw['access_token']);
        $this->assertSame('new-secret-token', $tenant->fresh()->whatsappSetting('webhook_verify_token'));
        $this->assertSame('access-123', $tenant->fresh()->whatsappSetting('access_token'));
    }

    public function test_whatsapp_settings_reads_legacy_plaintext_tokens(): void
    {
        $tenant = new Tenant([
            'settings' => [
                'whatsapp' => [
                    'webhook_verify_token' => 'legacy-plaintext',
                ],
            ],
        ]);

        $this->assertSame('legacy-plaintext', $tenant->whatsappSetting('webhook_verify_token'));
    }

    public function test_encrypted_token_roundtrip(): void
    {
        $encrypted = Crypt::encryptString('secret-value');
        $tenant = new Tenant([
            'settings' => [
                'whatsapp' => [
                    'api_token' => $encrypted,
                ],
            ],
        ]);

        $this->assertSame('secret-value', $tenant->whatsappSetting('api_token'));
    }

    public function test_send_text_dto_carries_tenant(): void
    {
        $tenant = new Tenant(['name' => 'Acme']);
        $dto = new SendTextMessageDTO(tenant: $tenant, to: '5511', body: 'oi');

        $this->assertSame($tenant, $dto->tenant);
        $this->assertSame('oi', $dto->body);
    }
}
