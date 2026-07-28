<?php

namespace App\Modules\Tenant\Models;

use App\Modules\ACL\Models\Role;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\User\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuid;

    /** @var list<string> */
    private const WHATSAPP_ENCRYPTED_KEYS = [
        'webhook_verify_token',
        'access_token',
        'api_token',
        'api_secret',
        'app_secret',
        'secret_key',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'document',
        'email',
        'phone',
        'domain',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function whatsappSettings(): array
    {
        $settings = $this->settings ?? [];

        if (! is_array($settings['whatsapp'] ?? null)) {
            return [];
        }

        $whatsapp = $settings['whatsapp'];

        foreach (self::WHATSAPP_ENCRYPTED_KEYS as $key) {
            if (array_key_exists($key, $whatsapp)) {
                $whatsapp[$key] = $this->decryptWhatsappValue($whatsapp[$key]);
            }
        }

        return $whatsapp;
    }

    public function whatsappSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->whatsappSettings(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $whatsapp
     */
    public function mergeWhatsappSettings(array $whatsapp): void
    {
        $settings = $this->settings ?? [];
        $current = is_array($settings['whatsapp'] ?? null) ? $settings['whatsapp'] : [];

        foreach ($whatsapp as $key => $value) {
            if (in_array($key, self::WHATSAPP_ENCRYPTED_KEYS, true) && filled($value)) {
                $current[$key] = Crypt::encryptString((string) $value);
            } else {
                $current[$key] = $value;
            }
        }

        $settings['whatsapp'] = $current;
        $this->settings = $settings;
        $this->save();
    }

    public function clearWhatsappSettings(): void
    {
        $settings = $this->settings ?? [];
        unset($settings['whatsapp']);
        $this->settings = $settings;
        $this->save();
    }

    public function isWhatsappConnected(): bool
    {
        return filled($this->whatsappSetting('channel_id'))
            || filled($this->whatsappSetting('connection_id'))
            || filled($this->whatsappSetting('workspace_id'))
            || filled($this->whatsappSetting('instance_id'))
            || filled($this->whatsappSetting('session_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function onboardingSettings(): array
    {
        $settings = $this->settings ?? [];

        return is_array($settings['onboarding'] ?? null) ? $settings['onboarding'] : [];
    }

    /**
     * @param  array<string, mixed>  $onboarding
     */
    public function mergeOnboardingSettings(array $onboarding): void
    {
        $settings = $this->settings ?? [];
        $settings['onboarding'] = array_merge($settings['onboarding'] ?? [], $onboarding);
        $this->settings = $settings;
        $this->save();
    }

    public function isOnboardingCompleted(): bool
    {
        if ($this->isUmbrella()) {
            return true;
        }

        return filled($this->onboardingSettings()['completed_at'] ?? null);
    }

    public function needsOnboarding(): bool
    {
        return ! $this->isOnboardingCompleted();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function isUmbrella(): bool
    {
        return $this->parent_id === null;
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    private function decryptWhatsappValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Rotação: valores legados em plaintext continuam legíveis.
            return $value;
        }
    }
}
