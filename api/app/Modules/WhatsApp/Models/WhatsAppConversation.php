<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppConversation extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'status',
        'current_stage_id',
        'last_message_preview',
        'last_message_at',
        'window_expires_at',
        'last_customer_message_at',
        'is_unread',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'window_expires_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'is_unread' => 'boolean',
        ];
    }

    public function isWindowOpen(): bool
    {
        return $this->window_expires_at !== null
            && $this->window_expires_at->isFuture();
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function lastMessage(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->latest()->limit(1);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WhatsAppConversationAssignment::class, 'conversation_id')->latest();
    }

    public function currentAssignment()
    {
        return $this->hasOne(WhatsAppConversationAssignment::class, 'conversation_id')
            ->whereNull('unassigned_at')
            ->latest('assigned_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsAppNote::class, 'conversation_id')->latest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppTag::class, 'whatsapp_conversation_tags', 'conversation_id', 'tag_id');
    }

    public function currentStage()
    {
        return $this->belongsTo(KanbanStage::class, 'current_stage_id');
    }

    public function stageMoves(): HasMany
    {
        return $this->hasMany(WhatsAppConversationStageMove::class, 'conversation_id')->latest('moved_at');
    }
}
