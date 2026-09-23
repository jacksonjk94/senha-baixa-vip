<?php

namespace App\Http\Controllers;

use App\Models\GrupoTelegram;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramTesteController extends Controller
{
    /**
     * Tela de diagnóstico: conexão (getMe), envio de mensagem e código de verificação.
     */
    public function index(TelegramBotService $telegram)
    {
        $status = $telegram->testarConexao();
        $cadastrados = GrupoTelegram::query()->pluck('chat_id')->map(fn ($id) => (string) $id)->all();

        return view('admin.telegram', [
            'configurado' => $telegram->configurado(),
            'status' => $status,
            'bot' => (string) config('services.telegram.bot', ''),
            'codigoEnviado' => session('telegram_teste.enviado_para'),
            'chats' => GrupoTelegram::with('usuario')->orderBy('nome')->get(),
            // Grupos vindos da Bot API (getUpdates) listados direto na tela, sem redirect.
            'gruposApi' => $telegram->configurado() ? $telegram->listarGrupos() : [],
            'chatIdsCadastrados' => $cadastrados,
        ]);
    }

    /**
     * Envia uma mensagem de teste para um chat_id.
     */
    public function enviar(Request $request, TelegramBotService $telegram)
    {
        $dados = $request->validate([
            'chat_id' => ['required', 'string', 'max:60'],
            'mensagem' => ['required', 'string', 'max:1000'],
        ]);

        $envio = $telegram->enviarMensagem($dados['chat_id'], $dados['mensagem']);

        if (! $envio['ok']) {
            Log::warning('Teste Telegram falhou', ['resposta' => $envio['resposta']]);

            return back()->with('erro', 'Falha no envio: ' . (is_string($envio['resposta']) ? $envio['resposta'] : 'resposta inválida da API'));
        }

        return back()->with('sucesso', 'Mensagem de teste enviada para ' . $dados['chat_id'] . '!');
    }

    /**
     * Gera um código de 6 dígitos, envia ao chat e guarda na sessão para verificação.
     */
    public function gerarCodigo(Request $request, TelegramBotService $telegram)
    {
        $dados = $request->validate([
            'chat_id' => ['required', 'string', 'max:60'],
        ]);

        $codigo = (string) random_int(100000, 999999);

        $envio = $telegram->enviarMensagem(
            $dados['chat_id'],
            "Seu código de verificação AgendaFácil: {$codigo}"
        );

        if (! $envio['ok']) {
            return back()->with('erro', 'Falha ao enviar o código: ' . (is_string($envio['resposta']) ? $envio['resposta'] : 'resposta inválida da API'));
        }

        $request->session()->put('telegram_teste.codigo', $codigo);
        $request->session()->put('telegram_teste.enviado_para', $dados['chat_id']);

        return back()->with('sucesso', 'Código enviado para ' . $dados['chat_id'] . '! Digite-o abaixo para verificar a conexão de ponta a ponta.');
    }

    /**
     * Verifica o código digitado contra o enviado.
     */
    public function verificarCodigo(Request $request)
    {
        $dados = $request->validate([
            'codigo' => ['required', 'digits:6'],
        ]);

        $esperado = $request->session()->get('telegram_teste.codigo');

        if (! $esperado || $dados['codigo'] !== $esperado) {
            return back()->with('erro', 'Código inválido. Gere um novo código e tente novamente.');
        }

        $request->session()->forget('telegram_teste.codigo');

        return back()->with('sucesso', 'Código verificado! Conexão com o Telegram funcionando de ponta a ponta. ✅');
    }
}
