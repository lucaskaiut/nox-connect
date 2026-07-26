<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('external_contact_id');
            $table->string('profile_name')->nullable();
            $table->string('display_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
