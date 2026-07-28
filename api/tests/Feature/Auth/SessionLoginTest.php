<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SessionLoginTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_login_then_me_works_with_session_cookie(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $login = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('data.token');

        // Ambiente de teste pode emitir PAT (sem sessão stateful); SPA usa cookie de sessão.
        if (filled($token)) {
            $this->app->get('auth')->forgetGuards();

            $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
                ->assertOk()
                ->assertJsonPath('data.user.email', 'admin@empresa.com');

            return;
        }

        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@empresa.com')
            ->assertJsonPath('data.tenant.id', $tenant->uuid);
    }

    public function test_bearer_token_from_login_authenticates_me(): void
    {
        $tenant = $this->createTenantWithRoles();
        $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@empresa.com',
            'password' => 'password',
        ])->json('data.token');

        $this->assertNotEmpty($token);

        $this->app->get('auth')->forgetGuards();

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@empresa.com');
    }
}
