<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->index(['conversation_id', 'id'], 'whatsapp_messages_conversation_id_id_index');
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'current_stage_id', 'last_message_at'],
                'whatsapp_conversations_tenant_stage_last_msg_index',
            );
            $table->index(
                ['tenant_id', 'last_message_at'],
                'whatsapp_conversations_tenant_last_msg_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex('whatsapp_messages_conversation_id_id_index');
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropIndex('whatsapp_conversations_tenant_stage_last_msg_index');
            $table->dropIndex('whatsapp_conversations_tenant_last_msg_index');
        });
    }
};
