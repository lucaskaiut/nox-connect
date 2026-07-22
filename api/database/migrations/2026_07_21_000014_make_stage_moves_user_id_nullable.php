<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversation_stage_moves', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversation_stage_moves', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable(false)->change();
        });
    }
};
