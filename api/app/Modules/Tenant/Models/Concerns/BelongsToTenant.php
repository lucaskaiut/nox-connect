<?php

namespace App\Modules\Tenant\Models\Concerns;

use App\Modules\Tenant\Models\Scopes\TenantScope;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Builder withoutTenancy()
 * @method static \Illuminate\Database\Eloquent\Builder forTenant(\App\Modules\Tenant\Models\Tenant|int $tenant)
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('tenant_id')) && TenantContext::isResolved()) {
                $model->setAttribute('tenant_id', TenantContext::tenantId());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Public tenant identifier used by the API and realtime channels (UUID),
     * not the internal numeric foreign key.
     */
    public function tenantUuid(): string
    {
        if ($this->relationLoaded('tenant') && $this->tenant) {
            return (string) $this->tenant->uuid;
        }

        $uuid = Tenant::query()->whereKey($this->getAttribute('tenant_id'))->value('uuid');

        return (string) $uuid;
    }
}
