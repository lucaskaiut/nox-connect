<?php

namespace App\Modules\WhatsApp\Http\Resources;

use App\Modules\WhatsApp\Models\WhatsAppConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppConfig
 */
class WhatsAppConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'waba_id' => $this->waba_id,
            'phone_number_id' => $this->phone_number_id,
            'is_active' => $this->is_active,
            'webhook_url' => $this->webhook_url,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
