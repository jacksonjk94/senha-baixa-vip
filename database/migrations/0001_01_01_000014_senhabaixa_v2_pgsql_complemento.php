<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SenhaBaixa v2 · complemento para Postgres (e idempotente nos demais):
     * texto/posicao/total/ip, user_id nullable, status com 'cancelado'
     * (enum PG vira VARCHAR para aceitar o novo valor) e grupo_telegrams.ordem.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE grupo_telegrams ADD COLUMN IF NOT EXISTS ordem INTEGER NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS texto VARCHAR(500) NULL');
            DB::statement('ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS posicao INTEGER NULL');
            DB::statement('ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS total_grupo_horario INTEGER NULL');
            DB::statement('ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS ip_origem VARCHAR(45) NULL');
            DB::statement('ALTER TABLE agendamentos ALTER COLUMN user_id DROP NOT NULL');
            // Enum nativo do Postgres não aceita novo valor sem ALTER TYPE;
            // converte para VARCHAR (preserva valores + default).
            DB::statement('ALTER TABLE agendamentos ALTER COLUMN status DROP DEFAULT');
            DB::statement("ALTER TABLE agendamentos ALTER COLUMN status TYPE VARCHAR(255) USING status::text");
            DB::statement("ALTER TABLE agendamentos ALTER COLUMN status SET DEFAULT 'pendente'");
            DB::statement('ALTER TABLE agendamentos ALTER COLUMN status SET NOT NULL');

            return;
        }

        if (! Schema::hasColumn('grupo_telegrams', 'ordem')) {
            Schema::table('grupo_telegrams', function (Blueprint $table) {
                $table->unsignedInteger('ordem')->default(0);
            });
        }

        foreach ([
            'texto' => fn (Blueprint $t) => $t->string('texto', 500)->nullable(),
            'posicao' => fn (Blueprint $t) => $t->unsignedInteger('posicao')->nullable(),
            'total_grupo_horario' => fn (Blueprint $t) => $t->unsignedInteger('total_grupo_horario')->nullable(),
            'ip_origem' => fn (Blueprint $t) => $t->string('ip_origem', 45)->nullable(),
        ] as $coluna => $definicao) {
            if (! Schema::hasColumn('agendamentos', $coluna)) {
                Schema::table('agendamentos', function (Blueprint $table) use ($definicao) {
                    $definicao($table);
                });
            }
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE agendamentos MODIFY user_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE agendamentos MODIFY status ENUM('pendente','enviado','erro','cancelado') NOT NULL DEFAULT 'pendente'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mantém colunas (dados); apenas Postgres volta o default se preciso.
    }
};
