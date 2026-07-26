<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->string('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_unread')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
