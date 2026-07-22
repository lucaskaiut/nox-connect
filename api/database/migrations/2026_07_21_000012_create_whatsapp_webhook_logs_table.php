<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_config_id')->constrained('whatsapp_configs')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('url')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_config_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_logs');
    }
};
