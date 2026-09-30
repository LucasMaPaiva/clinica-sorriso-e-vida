<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O Filament consulta as notificações com operadores JSON ("data"->>'format'),
     * que no Postgres exigem json/jsonb — a tabela padrão do Laravel usa TEXT.
     * No SQLite (testes) os operadores JSON funcionam em TEXT, então nada a fazer.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
