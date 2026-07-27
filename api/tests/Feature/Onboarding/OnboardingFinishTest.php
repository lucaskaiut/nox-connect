<?php

namespace Tests\Feature\Onboarding;

use App\Modules\Onboarding\Services\OnboardingService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OnboardingFinishTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_finish_allows_access_without_whatsapp(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'settings' => [
                'onboarding' => [
                    'company_completed' => true,
                    'whatsapp_completed' => false,
                    'current_step' => 'whatsapp',
                    'completed_at' => null,
                ],
            ],
        ]);
        $admin = $this->createAdmin($child);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->postJson('/api/onboarding/finish')
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.whatsapp_completed', false)
            ->assertJsonPath('data.required', false);

        $this->assertFalse($child->fresh()->needsOnboarding());
        $this->assertNotNull($child->fresh()->onboardingSettings()['completed_at'] ?? null);
    }

    public function test_finish_still_requires_company(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $admin = $this->createAdmin($child);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->postJson('/api/onboarding/finish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company']);
    }

    public function test_service_finish_preserves_whatsapp_flag_when_connected(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'settings' => [
                'onboarding' => [
                    'company_completed' => true,
                    'whatsapp_completed' => true,
                    'current_step' => 'finish',
                    'completed_at' => null,
                ],
                'whatsapp' => [
                    'status' => 'connected',
                    'connection_id' => 'conn-1',
                ],
            ],
        ]);

        $status = app(OnboardingService::class)->finish($child);

        $this->assertTrue($status['completed']);
        $this->assertTrue($status['whatsapp_completed']);
    }
}
