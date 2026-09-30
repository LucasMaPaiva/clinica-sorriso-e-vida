<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dentist_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('ISO-8601: 1=segunda, 7=domingo');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(30);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['dentist_id', 'weekday', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
