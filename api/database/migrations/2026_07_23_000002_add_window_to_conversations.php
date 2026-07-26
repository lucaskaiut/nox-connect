<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->timestamp('window_expires_at')->nullable()->after('last_message_at');
            $table->timestamp('last_customer_message_at')->nullable()->after('window_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropColumn(['window_expires_at', 'last_customer_message_at']);
        });
    }
};
