<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();

            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notes');
    }
};
