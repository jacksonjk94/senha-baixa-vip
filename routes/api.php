<?php

use App\Http\Controllers\N8nDisparoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Worker de disparos (n8n faz o papel de cron a cada minuto — ver
// instruções em `.docs/INSTRUCOES/n8n-disparos-cron.md`).
// Sem middleware `auth`: o n8n não faz login; a proteção é a chave
// secreta `X-N8N-Secret` validada no controller.
// URL final: GET https://seu-dominio.com/api/n8n/disparos/processar
Route::get('/n8n/disparos/processar', [N8nDisparoController::class, 'processar'])
    ->name('n8n.disparos.processar');

// Parâmetros de precisão (latência/antecedência) para o serviço
// disparos-cron disparar adiantado. Mesma proteção por chave secreta.
Route::get('/n8n/disparos/precisao', [N8nDisparoController::class, 'precisao'])
    ->name('n8n.disparos.precisao');
