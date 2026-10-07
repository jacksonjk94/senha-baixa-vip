<?php

namespace App\Http\Controllers;

use App\Services\TelegramMtprotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class TelegramTesteApiController extends Controller
{
    public function index(TelegramMtprotoService $telegram): View
    {
        return view('agendafacil.telegram-teste-api', [
            'configurado' => $telegram->configurado(),
            'operationId' => session('telegram_teste_operation_id'),
            'resultado' => session('telegram_teste_resultado'),
            'erro' => session('telegram_teste_erro'),
        ]);
    }

    public function enviar(Request $request, TelegramMtprotoService $telegram): RedirectResponse
    {
        $dados = $request->validate([
            'telefone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
        ]);

        try {
            $resposta = $telegram->enviarCodigo($dados['telefone']);
            session()->put('telegram_teste_operation_id', $resposta['operationId'] ?? null);

            return back()->with('telegram_teste_resultado', json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (ValidationException $e) {
            return back()->with('telegram_teste_erro', json_encode($e->errors(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function verificar(Request $request, TelegramMtprotoService $telegram): RedirectResponse
    {
        $dados = $request->validate([
            'operation_id' => ['required', 'string'],
            'codigo' => ['nullable', 'string'],
            'senha_2fa' => ['nullable', 'string'],
        ]);

        try {
            $resposta = $telegram->verificarCodigo(
                $dados['operation_id'],
                (string) ($dados['codigo'] ?? ''),
                $dados['senha_2fa'] ?? null
            );

            return back()->with('telegram_teste_resultado', json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (ValidationException $e) {
            return back()->with('telegram_teste_erro', json_encode($e->errors(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
