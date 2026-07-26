<?php

namespace App\Modules\WhatsApp\Contracts;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\WebhookChallengeDTO;
use App\Modules\WhatsApp\DTOs\WebhookResultDTO;
use Illuminate\Http\Request;

interface WebhookNormalizer
{
    public function providerKey(): string;

    public function verify(Tenant $tenant, Request $request): ?WebhookChallengeDTO;

    public function normalize(Tenant $tenant, Request $request): WebhookResultDTO;
}
