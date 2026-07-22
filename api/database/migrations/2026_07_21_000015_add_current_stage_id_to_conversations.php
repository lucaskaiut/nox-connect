<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->foreignId('current_stage_id')->nullable()->after('status')
                ->constrained('whatsapp_kanban_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropForeign(['current_stage_id']);
            $table->dropColumn('current_stage_id');
        });
    }
};
