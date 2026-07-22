<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppContact extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'wa_id',
        'profile_name',
        'display_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'contact_id');
    }
}
