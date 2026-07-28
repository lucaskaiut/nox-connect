<?php

namespace Tests\Feature\Onboarding;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OnboardingAuthorizationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_member_can_read_onboarding_status(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $member = $this->createMember($child);

        Sanctum::actingAs($member);
        TenantContext::set($child);

        $this->getJson('/api/onboarding')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_member_cannot_mutate_onboarding(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'settings' => [
                'onboarding' => [
                    'company_completed' => false,
                    'whatsapp_completed' => false,
                    'current_step' => 'company',
                    'completed_at' => null,
                ],
            ],
        ]);
        $member = $this->createMember($child);

        Sanctum::actingAs($member);
        TenantContext::set($child);

        $this->postJson('/api/onboarding/company', [
            'name' => $child->name,
            'document' => '52998224725',
            'email' => 'empresa@test.com',
            'phone' => '41999998888',
        ])->assertForbidden();

        $this->getJson('/api/onboarding/whatsapp/initialize')->assertForbidden();

        $this->postJson('/api/onboarding/whatsapp/complete', [
            'connection_id' => 'conn-test',
        ])->assertForbidden();

        $this->postJson('/api/onboarding/finish')->assertForbidden();
    }

    public function test_admin_can_mutate_onboarding(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella, [
            'settings' => [
                'onboarding' => [
                    'company_completed' => true,
                    'whatsapp_completed' => false,
                    'current_step' => 'finish',
                    'completed_at' => null,
                ],
            ],
        ]);
        $admin = $this->createAdmin($child);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $this->postJson('/api/onboarding/finish')
            ->assertOk()
            ->assertJsonPath('data.completed', true);
    }
}
