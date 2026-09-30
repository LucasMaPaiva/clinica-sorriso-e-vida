<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dentist_procedure', function (Blueprint $table) {
            $table->foreignId('dentist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['dentist_id', 'procedure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentist_procedure');
    }
};
