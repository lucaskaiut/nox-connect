<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Tenant;

class TenantService
{
    /**
     * @param  array{name: string, document: string, email: string, phone: ?string, domain: string, parent_id?: int|null}  $data
     */
    public function create(array $data): Tenant
    {
        $data['parent_id'] = $this->resolveParentId();
        $isRoot = ($data['parent_id'] ?? null) === null;

        if (! isset($data['settings']['onboarding'])) {
            $data['settings'] = array_merge($data['settings'] ?? [], [
                'onboarding' => [
                    'company_completed' => $isRoot,
                    'whatsapp_completed' => $isRoot,
                    'current_step' => $isRoot ? 'done' : 'company',
                    'completed_at' => $isRoot ? now()->toIso8601String() : null,
                ],
            ]);
        }

        return Tenant::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        unset($data['parent_id']);

        $tenant->fill($data);
        $tenant->save();

        return $tenant->refresh();
    }

    /**
     * Novos tenants ficam sob o primeiro cadastrado na base.
     * O primeiro tenant permanece como raiz (parent_id nulo).
     */
    private function resolveParentId(): ?int
    {
        $firstTenantId = Tenant::query()->orderBy('id')->value('id');

        return $firstTenantId !== null ? (int) $firstTenantId : null;
    }
}
