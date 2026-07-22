<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConversationStageMove extends Model
{
    protected $table = 'whatsapp_conversation_stage_moves';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'stage_id',
        'user_id',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(KanbanStage::class, 'stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid')->withDefault();
    }
}
