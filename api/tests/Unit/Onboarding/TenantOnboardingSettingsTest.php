<?php

namespace Tests\Unit\Onboarding;

use App\Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class TenantOnboardingSettingsTest extends TestCase
{
    public function test_incomplete_onboarding_requires_flow(): void
    {
        $tenant = new Tenant([
            'name' => 'New Co',
            'parent_id' => 1,
            'settings' => [
                'onboarding' => [
                    'company_completed' => false,
                    'whatsapp_completed' => false,
                    'completed_at' => null,
                ],
            ],
        ]);

        $this->assertFalse($tenant->isOnboardingCompleted());
        $this->assertTrue($tenant->needsOnboarding());
    }

    public function test_completed_at_marks_onboarding_done(): void
    {
        $tenant = new Tenant([
            'name' => 'Ready Co',
            'parent_id' => 1,
            'settings' => [
                'onboarding' => [
                    'company_completed' => true,
                    'whatsapp_completed' => true,
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $this->assertTrue($tenant->isOnboardingCompleted());
        $this->assertFalse($tenant->needsOnboarding());
    }

    public function test_umbrella_skips_onboarding(): void
    {
        $tenant = new Tenant([
            'name' => 'Root',
            'parent_id' => null,
            'settings' => [
                'onboarding' => [
                    'completed_at' => null,
                ],
            ],
        ]);

        $this->assertTrue($tenant->isOnboardingCompleted());
    }
}
