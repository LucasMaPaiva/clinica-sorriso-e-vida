<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('dentist_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_id')->constrained()->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status')->default('scheduled');
            $table->string('source')->default('admin');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('reminder_24h_sent_at')->nullable();
            $table->timestampTz('reminder_2h_sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['dentist_id', 'starts_at']);
            $table->index(['patient_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
