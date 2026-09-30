<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state')->default('idle');
            $table->json('context')->nullable();
            $table->timestampTz('last_interaction_at')->useCurrent();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
