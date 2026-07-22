<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversation_stage_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('whatsapp_kanban_stages')->nullOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->timestamp('moved_at')->useCurrent();

            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_stage_moves');
    }
};
