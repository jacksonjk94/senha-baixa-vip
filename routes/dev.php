<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HashTestController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

// Route Hash/Password Test:
Route::get('/test-hash/{password}', [HashTestController::class, 'index']);

Route::get('/run-dump-autoload', function () {
    if (Auth::check() && Auth::user()->is_admin) { // Verifica se o usuário é admin
        Artisan::call('dump-autoload');
        return "Composer dump-autoload executado com sucesso!";
    }
    abort(403, 'Acesso negado!');
});

// Clear Cache
Route::get('/clear-cache', function() {
    // 🔥 Limpando caches e dados temporários
    Artisan::call('cache:clear');
    echo Artisan::output()."<br>";

    Artisan::call('config:clear');
    echo Artisan::output()."<br>";

    Artisan::call('route:clear');
    echo Artisan::output()."<br>";

    Artisan::call('view:clear');
    echo Artisan::output()."<br>";

    Artisan::call('event:clear');
    echo Artisan::output()."<br>";

    Artisan::call('optimize:clear');
    echo Artisan::output()."<br>";

    Artisan::call('clear-compiled'); // Remove arquivos compilados
    echo Artisan::output()."<br>";

    Artisan::call('queue:clear'); // Limpa filas pendentes
    echo Artisan::output()."<br>";

    Artisan::call('schedule:clear-cache'); // Limpa cache do agendador
    echo Artisan::output()."<br>";

    Artisan::call('auth:clear-resets'); // Remove tokens expirados de redefinição de senha
    echo Artisan::output()."<br>";

    return "Cache is cleared";
});

// Link Storage
Route::get('/link-storage', function () {
    Artisan::call('storage:link');
    return "Storage is linked";
});
