<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grupo_telegram_id')->nullable()->constrained('grupo_telegrams')->nullOnDelete();
            $table->string('nome_visitante');
            $table->text('dados_senha')->nullable();
            $table->string('senha_codigo', 20);
            $table->enum('destino_tipo', ['grupo', 'pessoa'])->default('grupo');
            $table->string('destino_id'); // chat_id do grupo ou telegram_id da pessoa
            $table->string('destino_nome')->nullable();
            $table->dateTime('data_hora_envio')->index();
            $table->enum('status', ['pendente', 'enviado', 'erro'])->default('pendente')->index();
            $table->text('mensagem_erro')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
