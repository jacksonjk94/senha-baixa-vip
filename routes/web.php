<?php

use App\Http\Controllers\AcessoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\DisparoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\TelegramController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::get('/', function () {
    // Redireciona para a rota de acesso do AgendaFácil
    return redirect()->route('agendafacil.acesso');
});

// ── AgendaFácil · Fluxo principal (Blade, identidade Telegram) ──
Route::get('/acesso', [AcessoController::class, 'acesso'])->name('agendafacil.acesso');
Route::post('/acesso', [AcessoController::class, 'validarAcesso'])->name('agendafacil.acesso.validar');
Route::get('/confirmar-dados', [AcessoController::class, 'confirmarDados'])->name('agendafacil.confirmar-dados');
Route::post('/confirmar-dados', [AcessoController::class, 'salvarConfirmacao'])->name('agendafacil.confirmar-dados.salvar');
Route::get('/codigo', [AcessoController::class, 'codigo'])->name('agendafacil.codigo');
Route::post('/codigo', [AcessoController::class, 'validarCodigo'])->name('agendafacil.codigo.validar');

// Autenticação Telegram (Socialite)
Route::get('/telegram/connect', [TelegramController::class, 'connect'])->name('telegram.connect');
Route::get('/telegram/callback', [TelegramController::class, 'callback'])->name('telegram.callback');
Route::post('/telegram/disconnect', [TelegramController::class, 'disconnect'])->middleware('auth')->name('telegram.disconnect');

// Seleção de grupo + agendamento
Route::get('/grupos/selecionar', [GrupoController::class, 'selecionar'])->name('grupos.selecionar');
Route::post('/grupos/confirmar', [GrupoController::class, 'confirmar'])->name('grupos.confirmar');
Route::post('/grupos/confirmar-final', [GrupoController::class, 'confirmarGrupoFinal'])->name('grupos.confirmar-final');

Route::get('/painel', [AgendamentoController::class, 'index'])->name('dashboard.painel');
Route::get('/agendamento/novo', [AgendamentoController::class, 'create'])->name('agendamento.novo');
Route::post('/agendamento', [AgendamentoController::class, 'store'])->name('agendamento.salvar');
Route::get('/agendamento/{agendamento}/concluido', [AgendamentoController::class, 'concluido'])->name('agendamento.concluido');
Route::post('/agendamento/{agendamento}/disparar', [DisparoController::class, 'dispararAgora'])->middleware('auth')->name('agendamento.disparar');

// Área Administrativa (protegida por papel admin)
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.inicio');
    Route::get('/cadastros', [AdminController::class, 'cadastros'])->name('admin.cadastros');
    Route::get('/disparos', [AdminController::class, 'disparos'])->name('admin.disparos');
    Route::get('/grupos', [AdminController::class, 'grupos'])->name('admin.grupos');
    Route::post('/grupos', [AdminController::class, 'armazenarGrupo'])->name('admin.grupos.salvar');
    Route::patch('/grupos/{grupo}/alternar', [AdminController::class, 'alternarGrupo'])->name('admin.grupos.alternar');
    Route::patch('/agendamento/{agendamento}/status', [AdminController::class, 'atualizarStatus'])->name('admin.status');
    Route::post('/agendamento/{agendamento}/reenviar', [DisparoController::class, 'reenviar'])->name('admin.reenviar');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});


include __DIR__ . '/auth.php';
include __DIR__ . '/dev.php';
