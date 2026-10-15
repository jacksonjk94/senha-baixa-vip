<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rastreio da mensagem no grupo: id retornado no envio, posição entre
     * as recentes na captura e retrato do entorno (para o modal admin).
     */
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('telegram_message_id')->nullable()->after('destino_nome');
            $table->unsignedInteger('posicao_grupo')->nullable()->after('telegram_message_id');
            $table->json('contexto_grupo')->nullable()->after('posicao_grupo');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn(['telegram_message_id', 'posicao_grupo', 'contexto_grupo']);
        });
    }
};
