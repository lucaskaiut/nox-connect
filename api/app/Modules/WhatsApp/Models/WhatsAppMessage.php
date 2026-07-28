<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolamento multi-tenant via BelongsToTenant (tenant_id denormalizado + TenantScope / forTenant).
 * Escolha preferida em relação a filtrar só via conversation.tenant_id: o unique composto
 * (tenant_id, external_message_id) e upserts/status de webhook precisam do tenant na própria linha.
 */
class WhatsAppMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'direction',
        'message_type',
        'content',
        'media',
        'external_message_id',
        'status',
        'metadata',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'metadata' => 'array',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }
}
