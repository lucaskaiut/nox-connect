<?php

namespace Tests\Feature\Auth;

use App\Modules\User\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_response_for_unknown_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Se o e-mail estiver cadastrado, enviaremos instruções para redefinir a senha.',
            );
    }

    public function test_forgot_password_sends_notification_for_existing_user(): void
    {
        Notification::fake();

        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Se o e-mail estiver cadastrado, enviaremos instruções para redefinir a senha.',
            );

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant, ['email' => 'admin@empresa.com']);
        $user->createToken('auth_token');

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Senha redefinida com sucesso.');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = User::factory()->for($tenant)->create(['email' => 'user@empresa.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
