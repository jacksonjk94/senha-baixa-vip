<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adiciona o papel 'root' (topo da hierarquia administrativa).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('root','system','master','super-admin','admin','user','guest') NOT NULL DEFAULT 'user'");
        }
        // SQLite/Postgres: enum é flexível (varchar), nada a alterar.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('system','master','super-admin','admin','user','guest') NOT NULL DEFAULT 'user'");
        }
    }
};
