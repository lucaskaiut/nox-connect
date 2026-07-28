<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tokens com permissions null tinham acesso total (SEC-18). Convertidos para []
        // exigem recriação com escopos explícitos.
        DB::table('api_tokens')
            ->whereNull('permissions')
            ->update(['permissions' => json_encode([])]);
    }

    public function down(): void
    {
        // Irreversível com segurança: tokens convertidos não devem voltar a null.
    }
};
