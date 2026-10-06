<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * E-mail opcional no cadastro do visitante: users.email passa a aceitar
     * NULL (unique continua valendo; vários NULLs são permitidos no
     * pgsql/sqlite/mysql).
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');

            return;
        }

        if ($driver === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });

            DB::statement('ALTER TABLE users MODIFY email VARCHAR(191) NULL');

            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqlite(true);

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');

            return;
        }

        if ($driver === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });

            DB::statement('ALTER TABLE users MODIFY email VARCHAR(191) NOT NULL');

            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqlite(false);

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 255)->nullable(false)->change();
        });
    }

    /**
     * SQLite não altera nulidade via ALTER: reconstrói a tabela com o
     * esquema atual (espelho das migrations até aqui).
     */
    private function rebuildSqlite(bool $nullable): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('users_nova', function (Blueprint $table) use ($nullable) {
            $table->id();
            $table->string('uuid')->unique();
            $table->boolean('active')->default(false);
            $table->string('name');
            $table->string('tax_id')->unique()->nullable();
            $table->string('email', 255)->unique()->nullable($nullable);
            $table->string('username')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->string('role')->default('user');
            $table->boolean('can_manage_admins')->default(false);
            $table->string('system_theme')->default('light');
            $table->string('telegram_id')->nullable()->unique();
            $table->string('telegram_username')->nullable();
            $table->text('telegram_token')->nullable();
            $table->string('ultimos_digitos_telefone', 4)->nullable();
            $table->string('telefone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $colunas = Schema::getColumnListing('users');
        $lista = implode(',', array_map(fn ($c) => '"'.$c.'"', $colunas));
        DB::statement("INSERT INTO users_nova ({$lista}) SELECT {$lista} FROM users");

        Schema::drop('users');
        Schema::rename('users_nova', 'users');

        Schema::enableForeignKeyConstraints();
    }
};
