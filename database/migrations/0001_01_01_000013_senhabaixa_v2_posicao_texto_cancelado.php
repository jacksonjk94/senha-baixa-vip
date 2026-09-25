<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SenhaBaixa v2: posição/ordem de envio, texto livre, IP de origem,
     * status cancelado, user opcional (fluxo público sem login) e ordem de grupos.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE agendamentos ADD COLUMN texto VARCHAR(500) NULL AFTER destino_nome");
            DB::statement('ALTER TABLE agendamentos ADD COLUMN posicao INT UNSIGNED NULL AFTER texto');
            DB::statement('ALTER TABLE agendamentos ADD COLUMN total_grupo_horario INT UNSIGNED NULL AFTER posicao');
            DB::statement('ALTER TABLE agendamentos ADD COLUMN ip_origem VARCHAR(45) NULL AFTER enviado_em');
            DB::statement('ALTER TABLE agendamentos MODIFY user_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE agendamentos MODIFY status ENUM('pendente','enviado','erro','cancelado') NOT NULL DEFAULT 'pendente'");
            DB::statement('ALTER TABLE grupo_telegrams ADD COLUMN ordem INT UNSIGNED NOT NULL DEFAULT 0');

            return;
        }

        // SQLite (dev + snapshot Vercel): rebuild para CHECK do enum + user nullable.
        Schema::create('agendamentos_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('grupo_telegram_id')->nullable()->constrained('grupo_telegrams')->nullOnDelete();
            $table->string('nome_visitante')->nullable();
            $table->text('dados_senha')->nullable();
            $table->string('senha_codigo', 20)->nullable();
            $table->string('texto', 500)->nullable();
            $table->unsignedInteger('posicao')->nullable();
            $table->unsignedInteger('total_grupo_horario')->nullable();
            $table->enum('destino_tipo', ['grupo', 'pessoa'])->default('grupo');
            $table->string('destino_id');
            $table->string('destino_nome')->nullable();
            $table->dateTime('data_hora_envio')->index();
            $table->enum('status', ['pendente', 'enviado', 'erro', 'cancelado'])->default('pendente')->index();
            $table->text('mensagem_erro')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->string('ip_origem', 45)->nullable();
            $table->timestamps();
        });

        DB::statement('INSERT INTO agendamentos_v2 (id, user_id, grupo_telegram_id, nome_visitante, dados_senha, senha_codigo, destino_tipo, destino_id, destino_nome, data_hora_envio, status, mensagem_erro, enviado_em, created_at, updated_at) SELECT id, user_id, grupo_telegram_id, nome_visitante, dados_senha, senha_codigo, destino_tipo, destino_id, destino_nome, data_hora_envio, status, mensagem_erro, enviado_em, created_at, updated_at FROM agendamentos');
        Schema::drop('agendamentos');
        Schema::rename('agendamentos_v2', 'agendamentos');

        if (! Schema::hasColumn('grupo_telegrams', 'ordem')) {
            Schema::table('grupo_telegrams', function (Blueprint $table) {
                $table->unsignedInteger('ordem')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE agendamentos DROP COLUMN texto, DROP COLUMN posicao, DROP COLUMN total_grupo_horario, DROP COLUMN ip_origem');
            DB::statement('ALTER TABLE grupo_telegrams DROP COLUMN ordem');

            return;
        }

        if (Schema::hasColumn('grupo_telegrams', 'ordem')) {
            Schema::table('grupo_telegrams', function (Blueprint $table) {
                $table->dropColumn('ordem');
            });
        }
    }
};
