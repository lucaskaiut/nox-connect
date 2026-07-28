<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-02: denormaliza tenant_id em whatsapp_messages e troca o unique global
 * de external_message_id por unique composto (tenant_id, external_message_id).
 *
 * Idempotente: no-op se tenant_id já existir (ex.: create migration já atualizada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_messages', 'tenant_id')) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();
            });

            DB::table('whatsapp_messages')
                ->orderBy('id')
                ->chunkById(500, function ($messages): void {
                    foreach ($messages as $message) {
                        $tenantId = DB::table('whatsapp_conversations')
                            ->where('id', $message->conversation_id)
                            ->value('tenant_id');

                        if ($tenantId === null) {
                            continue;
                        }

                        DB::table('whatsapp_messages')
                            ->where('id', $message->id)
                            ->update(['tenant_id' => $tenantId]);
                    }
                });

            $orphans = DB::table('whatsapp_messages')->whereNull('tenant_id')->count();

            if ($orphans > 0) {
                throw new RuntimeException(
                    "Cannot harden whatsapp_messages: {$orphans} row(s) have no tenant via conversation."
                );
            }

            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        $this->dropLegacyExternalMessageIdUnique();

        if (! $this->hasCompositeExternalMessageIdUnique()) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'external_message_id'], 'whatsapp_messages_tenant_external_message_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('whatsapp_messages', 'whatsapp_messages_tenant_external_message_unique')) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->dropUnique('whatsapp_messages_tenant_external_message_unique');
            });
        }

        if (Schema::hasColumn('whatsapp_messages', 'tenant_id')) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        if (! Schema::hasIndex('whatsapp_messages', 'whatsapp_messages_external_message_id_unique')) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->unique('external_message_id');
            });
        }
    }

    private function dropLegacyExternalMessageIdUnique(): void
    {
        if (Schema::hasIndex('whatsapp_messages', 'whatsapp_messages_external_message_id_unique')) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->dropUnique('whatsapp_messages_external_message_id_unique');
            });
        }

        if (Schema::hasIndex('whatsapp_messages', 'whatsapp_messages_external_message_id_index')) {
            Schema::table('whatsapp_messages', function (Blueprint $table): void {
                $table->dropIndex('whatsapp_messages_external_message_id_index');
            });
        }
    }

    private function hasCompositeExternalMessageIdUnique(): bool
    {
        return Schema::hasIndex('whatsapp_messages', 'whatsapp_messages_tenant_external_message_unique')
            || Schema::hasIndex('whatsapp_messages', ['tenant_id', 'external_message_id']);
    }
};
