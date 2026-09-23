<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AcessoController;
use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgendamentoController extends Controller
{
    /**
     * Painel do fluxo (lista agendamentos do usuário / visitante).
     */
    public function index(Request $request)
    {
        $agendamentos = Agendamento::query()
            ->when(Auth::id(), fn ($q) => $q->where('user_id', Auth::id()))
            ->when(
                ! Auth::id() && $request->session()->get('agendafacil.visitante_id'),
                fn ($q) => $q->where('user_id', $request->session()->get('agendafacil.visitante_id'))
            )
            ->latest()
            ->paginate(10);

        return view('agendafacil.dashboard', compact('agendamentos'));
    }

    /**
     * Etapa 7 — Formulário de data/hora do disparo.
     */
    public function create(Request $request)
    {
        $grupoSessao = $request->session()->get('agendafacil.grupo');
        $confirmacao = $request->session()->get('agendafacil.confirmacao');

        if (! $grupoSessao || ! $confirmacao) {
            return redirect()->route('agendafacil.acesso')
                ->with('aviso', 'Comece pelo acesso com os 4 dígitos do telefone.');
        }

        return view('agendafacil.agendar', [
            'grupoSessao' => $grupoSessao,
            'confirmacao' => $confirmacao,
        ]);
    }

    /**
     * Persiste o agendamento com status pendente.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome_visitante' => ['required', 'string', 'max:120'],
            'destino_tipo' => ['required', 'in:grupo,pessoa'],
            'grupo_telegram_id' => ['nullable', 'exists:grupo_telegrams,id'],
            'data_envio' => ['required', 'date'],
            'hora_envio' => ['required', 'date_format:H:i'],
            'dados_senha' => ['nullable', 'string', 'max:500'],
        ]);

        $dataHora = \Carbon\Carbon::parse($dados['data_envio'] . ' ' . $dados['hora_envio']);

        if ($dataHora->isPast()) {
            return back()
                ->withErrors(['data_envio' => 'A data/hora do envio precisa estar no futuro.'])
                ->withInput();
        }

        $grupoSessao = $request->session()->get('agendafacil.grupo', []);
        $visitanteId = $request->session()->get('agendafacil.visitante_id');

        $userId = Auth::id() ?? $visitanteId;

        if (! $userId) {
            return redirect()->route('agendafacil.acesso');
        }

        $usuario = User::find($userId);

        if ($dados['destino_tipo'] === 'grupo') {
            $grupo = isset($dados['grupo_telegram_id'])
                ? GrupoTelegram::find($dados['grupo_telegram_id'])
                : (isset($grupoSessao['grupo_id']) ? GrupoTelegram::find($grupoSessao['grupo_id']) : null);

            if (! $grupo) {
                return back()
                    ->withErrors(['grupo_telegram_id' => 'Selecione um grupo válido.'])
                    ->withInput();
            }

            $destinoId = $grupo->chat_id;
            $destinoNome = $grupo->nome;
            $grupoId = $grupo->id;
        } else {
            $destinoId = $usuario?->telegram_id
                ?? $grupoSessao['destino_id']
                ?? 'individual';
            $destinoNome = $usuario?->name ?? $dados['nome_visitante'];
            $grupoId = null;
        }

        $dataHora = \Carbon\Carbon::parse($dados['data_envio'] . ' ' . $dados['hora_envio']);

        if ($dataHora->isPast()) {
            return back()
                ->withErrors(['data_envio' => 'A data/hora do envio precisa estar no futuro.'])
                ->withInput();
        }

        $agendamento = Agendamento::create([
            'user_id' => $userId,
            'grupo_telegram_id' => $grupoId,
            'nome_visitante' => $dados['nome_visitante'],
            'dados_senha' => $dados['dados_senha'] ?? null,
            'senha_codigo' => AcessoController::gerarSenhaCodigo(),
            'destino_tipo' => $dados['destino_tipo'],
            'destino_id' => (string) $destinoId,
            'destino_nome' => $destinoNome,
            'data_hora_envio' => $dataHora,
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $request->session()->put('agendafacil.ultimo_agendamento_id', $agendamento->id);

        // Etapa 8 — tela de concluído.
        return redirect()->route('agendamento.concluido', $agendamento);
    }

    public function concluido(Agendamento $agendamento)
    {
        return view('agendafacil.concluido', compact('agendamento'));
    }
}
