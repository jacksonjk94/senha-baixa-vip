<?php

namespace App\Http\Controllers;

use App\Models\GrupoTelegram;
use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrupoController extends Controller
{
    /**
     * Etapa 5 — Lista grupos disponíveis (API Telegram + cadastrados).
     */
    public function selecionar(Request $request, TelegramBotService $telegram)
    {
        $busca = trim((string) $request->query('busca', ''));
        $viaApi = $telegram->listarGrupos();

        // Persiste grupos vindos da API para o dono do fluxo (logado ou visitante).
        // Evita duplicar chat_id por usuário.
        $usuarioId = Auth::id() ?? $request->session()->get('agendafacil.visitante_id');
        foreach ($viaApi as $grupoApi) {
            GrupoTelegram::firstOrCreate(
                [
                    'chat_id' => $grupoApi['chat_id'],
                    'usuario_id' => $usuarioId,
                ],
                [
                    'nome' => $grupoApi['nome'],
                    'tipo' => $grupoApi['tipo'],
                    'ativo' => true,
                ]
            );
        }

        $query = GrupoTelegram::query()->where('ativo', true);

        if ($busca !== '') {
            $query->where('nome', 'like', "%{$busca}%");
        }

        $grupos = $query->orderBy('nome')->paginate(12)->withQueryString();

        $telegramStatus = $telegram->configurado() ? $telegram->testarConexao() : null;

        return view('agendafacil.selecionar-grupo', [
            'grupos' => $grupos,
            'busca' => $busca,
            'telegramConfigurado' => $telegram->configurado(),
            'telegramBot' => (string) (TelegramBot::defaultBot()?->username ?? config('services.telegram.bot', '')),
            'telegramStatus' => $telegramStatus,
        ]);
    }

    /**
     * Etapa 6 — Confirmação visual do grupo escolhido.
     */
    public function confirmar(Request $request)
    {
        $dados = $request->validate([
            'destino_tipo' => ['required', 'in:grupo,pessoa'],
            'grupo_id' => ['required_if:destino_tipo,grupo', 'nullable', 'exists:grupo_telegrams,id'],
        ], [
            'destino_tipo.required' => 'Escolha o tipo de destino.',
            'destino_tipo.in' => 'Tipo de destino inválido.',
            'grupo_id.required_if' => 'Selecione um grupo para continuar.',
            'grupo_id.exists' => 'Grupo selecionado inválido.',
        ]);

        // Envio individual não exige grupo: usa o chat do próprio usuário.
        if ($dados['destino_tipo'] === 'pessoa') {
            $request->session()->put('agendafacil.grupo', [
                'destino_tipo' => 'pessoa',
                'grupo_id' => null,
                'destino_nome' => 'Chat individual',
            ]);

            return redirect()->route('agendamento.novo');
        }

        $grupo = GrupoTelegram::find($dados['grupo_id'] ?? null);

        if (! $grupo) {
            return back()
                ->withErrors(['grupo_id' => 'Selecione um grupo válido para continuar.'])
                ->withInput();
        }

        $request->session()->put('agendafacil.grupo', [
            'destino_tipo' => 'grupo',
            'grupo_id' => $grupo->id,
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
        ]);

        return view('agendafacil.confirmar-grupo', compact('grupo'));
    }

    public function confirmarGrupoFinal(Request $request)
    {
        if (! $request->session()->get('agendafacil.grupo')) {
            return redirect()->route('grupos.selecionar');
        }

        return redirect()->route('agendamento.novo');
    }
}
