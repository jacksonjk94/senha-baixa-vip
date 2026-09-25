<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item 4 do prompt de correções: distinção grupo ABERTO x FECHADO.
     * Usa string em vez de enum para portabilidade SQLite/MySQL.
     * `tipo` existente foi mantido (tipo de chat do Telegram).
     */
    public function up(): void
    {
        Schema::table('grupo_telegrams', function (Blueprint $table) {
            $table->string('modo', 10)->default('aberto')->after('tipo');
            $table->time('horario_abertura')->nullable()->after('modo');
            $table->time('horario_fechamento')->nullable()->after('horario_abertura');
            $table->time('horario_disparo')->nullable()->after('horario_fechamento');
            $table->string('dias_semana', 20)->nullable()->after('horario_disparo')
                ->comment('CSV dias ISO 1-7 (vazio = todos os dias)');
        });
    }

    public function down(): void
    {
        Schema::table('grupo_telegrams', function (Blueprint $table) {
            $table->dropColumn([
                'modo',
                'horario_abertura',
                'horario_fechamento',
                'horario_disparo',
                'dias_semana',
            ]);
        });
    }
};
