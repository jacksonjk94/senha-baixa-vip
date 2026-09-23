<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 🔥 Limpando caches e dados temporários
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('event:clear');
        Artisan::call('optimize:clear');
        Artisan::call('clear-compiled'); // Remove arquivos compilados
        Artisan::call('queue:clear'); // Limpa filas pendentes
        Artisan::call('schedule:clear-cache'); // Limpa cache do agendador
        Artisan::call('auth:clear-resets'); // Remove tokens expirados de redefinição de senha

        // 🌱 Executando os Seeders necessários
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class]);
        Artisan::call('db:seed', ['--class' => UserSeeder::class]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
