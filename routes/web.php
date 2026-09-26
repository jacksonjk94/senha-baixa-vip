<?php

use App\Http\Controllers\AcessoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\AjudaController;
use App\Http\Controllers\CadastroVisitanteController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\DisparoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\TelegramBotsController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TelegramTesteController;
use App\Http\Controllers\TelegramValidacaoController;
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

Route::get('/', fn () => redirect()->route('agendafacil.acesso'))->name('inicio');

// SenhaBaixa v2 · fluxo público separado (OCULTO — fluxo único é o /acesso).
// Rotas mantidas por compatibilidade, sem links na interface.
Route::get('/agendar', [AgendamentoController::class, 'agendar'])->name('agendar');
Route::post('/agendar', [AgendamentoController::class, 'salvar'])->name('agendar.salvar');
Route::get('/agendamento/{agendamento}/confirmacao', [AgendamentoController::class, 'confirmacao'])->name('agendamento.confirmacao');
Route::get('/meus-agendamentos', [AgendamentoController::class, 'meus'])->name('agendamento.meus');

// ── AgendaFácil · Fluxo único (Blade, identidade Telegram) ──
// 1. Acesso → 2. Confirmar os Dados → 3. Código → 4. Grupos do Telegram → 5. Concluído
Route::get('/acesso', [AcessoController::class, 'acesso'])->name('agendafacil.acesso');
Route::post('/acesso', [AcessoController::class, 'validarAcesso'])->name('agendafacil.acesso.validar');
Route::get('/confirmar-dados', [AcessoController::class, 'confirmarDados'])->name('agendafacil.confirmar-dados');
Route::post('/confirmar-dados', [AcessoController::class, 'salvarConfirmacao'])->name('agendafacil.confirmar-dados.salvar');
Route::get('/confirmar-dados/resumo', [AcessoController::class, 'resumo'])->name('agendafacil.resumo');
Route::post('/confirmar-dados/confirmar', [AcessoController::class, 'confirmarResumo'])->name('agendafacil.resumo.confirmar');
Route::get('/proxima-senha', [AcessoController::class, 'proximaSenha'])->name('agendafacil.proxima-senha');
Route::get('/codigo', [AcessoController::class, 'codigo'])->name('agendafacil.codigo');
Route::post('/codigo', [AcessoController::class, 'validarCodigo'])->name('agendafacil.codigo.validar');
Route::post('/codigo/reenviar', [AcessoController::class, 'reenviarCodigo'])->name('agendafacil.codigo.reenviar');
Route::get('/concluido', [AcessoController::class, 'concluido'])->name('agendafacil.concluido');

// Ajuda pública (guest): passo a passo do bot/grupos do Telegram.
Route::get('/ajuda/bot-telegram', [AjudaController::class, 'botTelegram'])->name('ajuda.bot-telegram');

// Cadastro externo do visitante (sem auth) → redireciona ao acesso.
Route::get('/cadastro', [CadastroVisitanteController::class, 'criar'])->name('visitante.cadastro');
Route::post('/cadastro', [CadastroVisitanteController::class, 'salvar'])->name('visitante.cadastro.salvar');

// Autenticação Telegram (Socialite)
Route::get('/telegram/connect', [TelegramController::class, 'connect'])->name('telegram.connect');
Route::get('/telegram/callback', [TelegramController::class, 'callback'])->name('telegram.callback');

// Validação da pessoa pelo próprio Telegram (Login Widget — etapa 3, /codigo).
// Perna independente do robô: só confirma identidade, cria sessão local de 2h.
// Confirmação da etapa 3 (/codigo): login no Telegram vinculado ao cadastro.
Route::get('/telegram/confirmar-callback', [TelegramValidacaoController::class, 'confirmar'])->name('telegram.confirmar-callback');
Route::post('/sair', [TelegramValidacaoController::class, 'sair'])->name('agendafacil.sair');
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
    Route::post('/cadastros', [AdminController::class, 'armazenarVisitante'])->name('admin.cadastros.salvar');
    Route::patch('/cadastros/{visitante}', [AdminController::class, 'atualizarVisitante'])->name('admin.cadastros.atualizar');
    Route::delete('/cadastros/{visitante}', [AdminController::class, 'excluirVisitante'])->name('admin.cadastros.excluir');
    Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('admin.usuarios');
    Route::post('/usuarios', [AdminController::class, 'armazenarUsuario'])->name('admin.usuarios.salvar');
    Route::patch('/usuarios/{usuario}', [AdminController::class, 'atualizarUsuario'])->name('admin.usuarios.atualizar');
    Route::delete('/usuarios/{usuario}', [AdminController::class, 'excluirUsuario'])->name('admin.usuarios.excluir');
    Route::get('/disparos', [AdminController::class, 'disparos'])->name('admin.disparos');
    Route::get('/grupos', [AdminController::class, 'grupos'])->name('admin.grupos');
    Route::post('/grupos', [AdminController::class, 'armazenarGrupo'])->name('admin.grupos.salvar');
    Route::post('/grupos/importar', [AdminController::class, 'importarGrupo'])->name('admin.grupos.importar');
    Route::patch('/grupos/{grupo}', [AdminController::class, 'atualizarGrupo'])->name('admin.grupos.atualizar');
    Route::patch('/grupos/{grupo}/alternar', [AdminController::class, 'alternarGrupo'])->name('admin.grupos.alternar');
    Route::patch('/agendamento/{agendamento}/status', [AdminController::class, 'atualizarStatus'])->name('admin.status');
    Route::post('/agendamento/{agendamento}/reenviar', [DisparoController::class, 'reenviar'])->name('admin.reenviar');
    Route::patch('/agendamento/{agendamento}/cancelar', [AdminController::class, 'cancelar'])->name('admin.cancelar');
    Route::get('/configuracoes', [ConfiguracaoController::class, 'index'])->name('admin.configuracoes');
    Route::post('/configuracoes', [ConfiguracaoController::class, 'salvar'])->name('admin.configuracoes.salvar');
    Route::get('/telegram', [TelegramTesteController::class, 'index'])->name('admin.telegram');
    Route::post('/telegram/enviar', [TelegramTesteController::class, 'enviar'])->name('admin.telegram.enviar');
    Route::post('/telegram/codigo', [TelegramTesteController::class, 'gerarCodigo'])->name('admin.telegram.codigo');
    Route::post('/telegram/verificar', [TelegramTesteController::class, 'verificarCodigo'])->name('admin.telegram.verificar');
    Route::get('/telegram-bots', [TelegramBotsController::class, 'index'])->name('admin.telegram-bots');
    Route::post('/telegram-bots', [TelegramBotsController::class, 'store'])->name('admin.telegram-bots.salvar');
    Route::post('/telegram-bots/importar-env', [TelegramBotsController::class, 'importEnv'])->name('admin.telegram-bots.importar-env');
    Route::patch('/telegram-bots/{bot}', [TelegramBotsController::class, 'update'])->name('admin.telegram-bots.atualizar');
    Route::delete('/telegram-bots/{bot}', [TelegramBotsController::class, 'destroy'])->name('admin.telegram-bots.excluir');
    Route::post('/telegram-bots/{bot}/padrao', [TelegramBotsController::class, 'setDefault'])->name('admin.telegram-bots.padrao');
    Route::patch('/telegram-bots/{bot}/alternar', [TelegramBotsController::class, 'toggle'])->name('admin.telegram-bots.alternar');
    Route::get('/manual', [ManualController::class, 'index'])->name('admin.manual');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        // return Inertia::render('Dashboard');
        return redirect()->route('admin.inicio');
    })->name('dashboard');
});


include __DIR__ . '/auth.php';
include __DIR__ . '/dev.php';
