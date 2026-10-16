<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Latência/antecedência aplicada no disparo (ms configurados no painel
     * e efetivamente usados no instante de envio).
     */
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->unsignedInteger('latencia_ms')->nullable()->after('posicao_grupo');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('latencia_ms');
        });
    }
};
