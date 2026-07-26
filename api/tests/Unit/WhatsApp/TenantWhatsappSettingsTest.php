<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\SendTextMessageDTO;
use Tests\TestCase;

class TenantWhatsappSettingsTest extends TestCase
{
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

    public function test_send_text_dto_carries_tenant(): void
    {
        $tenant = new Tenant(['name' => 'Acme']);
        $dto = new SendTextMessageDTO(tenant: $tenant, to: '5511', body: 'oi');

        $this->assertSame($tenant, $dto->tenant);
        $this->assertSame('oi', $dto->body);
    }
}
