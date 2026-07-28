<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('direction');
            $table->string('message_type');
            $table->text('content')->nullable();
            $table->json('media')->nullable();
            // Unique por tenant (não global): mesmo external_message_id pode existir em tenants distintos.
            $table->string('external_message_id')->nullable();
            $table->string('status')->default('received');
            $table->json('metadata')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            // Índice de tenant coberto pelo unique composto (prefixo tenant_id) + FK.
            $table->unique(['tenant_id', 'external_message_id'], 'whatsapp_messages_tenant_external_message_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
