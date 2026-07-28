<?php

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantScopeFailClosedTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_query_without_tenant_context_returns_empty(): void
    {
        $tenant = $this->createTenantWithRoles();
        User::factory()->count(3)->for($tenant)->create();

        $this->forgetTenantContext();

        $this->assertSame(0, User::query()->count());
    }

    public function test_query_with_tenant_context_returns_tenant_records(): void
    {
        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        User::factory()->count(2)->for($tenantA)->create();
        User::factory()->count(3)->for($tenantB)->create();

        TenantContext::set($tenantA);

        $this->assertSame(2, User::query()->count());
    }
}
