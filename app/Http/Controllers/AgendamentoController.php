<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AcessoController;
use App\Jobs\EnviarSenhaTelegram;
use App\Models\Agendamento;
use App\Models\Configuracao;
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
            'modoDisparo' => Configuracao::modoDisparo(),
            'horarioPadrao' => Configuracao::horarioDisparo(),
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

        $disparoImediato = Configuracao::modoDisparo() === 'imediato';

        $regras = [
            'nome_visitante' => ['required', 'string', 'max:120'],
            'destino_tipo' => ['required', 'in:grupo,pessoa'],
            'grupo_telegram_id' => ['nullable', 'exists:grupo_telegrams,id'],
            'dados_senha' => ['nullable', 'string', 'max:500'],
        ];

        if ($disparoImediato) {
            $regras['data_envio'] = ['nullable', 'date'];
            $regras['hora_envio'] = ['nullable', 'date_format:H:i'];
        } else {
            $regras['data_envio'] = ['required', 'date'];
            $regras['hora_envio'] = ['required', 'date_format:H:i'];
        }

        $dados = $request->validate($regras);

        if ($disparoImediato) {
            $dataHora = now()->addMinute();
        } else {
            $dataHora = \Carbon\Carbon::parse($dados['data_envio'] . ' ' . $dados['hora_envio']);
        }

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

        if ($disparoImediato) {
            EnviarSenhaTelegram::dispatchSync($agendamento->id);
            $agendamento->refresh();
        }

        // Etapa 8 — tela de concluído.
        return redirect()->route('agendamento.concluido', $agendamento);
    }

    public function concluido(Agendamento $agendamento)
    {
        return view('agendafacil.concluido', compact('agendamento'));
    }

    // ── SenhaBaixa v2 · fluxo público simplificado (sem login, sem Telegram) ──

    /**
     * Tela inicial: apresentação + botão "Agendar agora".
     */
    public function inicio()
    {
        return view('senhabaixa.inicio');
    }

    /**
     * Formulário público: grupo + data/hora + texto livre.
     */
    public function agendar()
    {
        return view('senhabaixa.agendar', [
            'grupos' => GrupoTelegram::ativos()->ordenados()->get(),
            'modoDisparo' => Configuracao::modoDisparo(),
            'horarioPadrao' => Configuracao::horarioDisparo(),
        ]);
    }

    /**
     * Salva o agendamento (pendente) com posição por grupo+horário.
     */
    public function salvar(Request $request)
    {
        $disparoImediato = Configuracao::modoDisparo() === 'imediato';

        $regras = [
            'grupo_telegram_id' => ['required', 'exists:grupo_telegrams,id'],
            'texto' => ['required', 'string', 'max:500'],
        ];

        if ($disparoImediato) {
            $regras['data_envio'] = ['nullable', 'date'];
            $regras['hora_envio'] = ['nullable', 'date_format:H:i'];
        } else {
            $regras['data_envio'] = ['required', 'date'];
            $regras['hora_envio'] = ['required', 'date_format:H:i'];
        }

        $dados = $request->validate($regras, [
            'grupo_telegram_id.required' => 'Escolha o grupo de destino.',
            'grupo_telegram_id.exists' => 'Grupo inválido.',
            'texto.required' => 'Digite o texto que vai aparecer no grupo.',
        ]);

        $grupo = GrupoTelegram::ativos()->find($dados['grupo_telegram_id']);

        if (! $grupo) {
            return back()
                ->withErrors(['grupo_telegram_id' => 'Grupo indisponível no momento.'])
                ->withInput();
        }

        $dataHora = $disparoImediato
            ? now()->addMinute()
            : \Carbon\Carbon::parse($dados['data_envio'] . ' ' . $dados['hora_envio']);

        if (! $disparoImediato && $dataHora->isPast()) {
            return back()
                ->withErrors(['data_envio' => 'A data/hora do envio precisa estar no futuro.'])
                ->withInput();
        }

        $agendamento = Agendamento::create([
            'user_id' => Auth::id(),
            'grupo_telegram_id' => $grupo->id,
            'texto' => $dados['texto'],
            'posicao' => Agendamento::proximaPosicao($grupo->id, $dataHora),
            'destino_tipo' => Agendamento::DESTINO_GRUPO,
            'destino_id' => (string) $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => $dataHora,
            'status' => Agendamento::STATUS_PENDENTE,
            'ip_origem' => $request->ip(),
        ]);

        // Histórico do visitante (sem login): guarda os ids na sessão.
        $meus = $request->session()->get('senhabaixa.meus_ids', []);
        array_unshift($meus, $agendamento->id);
        $request->session()->put('senhabaixa.meus_ids', array_slice(array_unique($meus), 0, 50));

        if ($disparoImediato) {
            EnviarSenhaTelegram::dispatchSync($agendamento->id);
            $agendamento->refresh();
        }

        return redirect()->route('agendamento.confirmacao', $agendamento);
    }

    /**
     * Confirmação: "Agendado ✅ — Envia dia XX às HHh — Posição: X de Y".
     */
    public function confirmacao(Agendamento $agendamento)
    {
        $total = $agendamento->grupo_telegram_id
            ? Agendamento::totalGrupoHorario($agendamento->grupo_telegram_id, $agendamento->data_hora_envio)
            : 1;

        return view('senhabaixa.confirmacao', compact('agendamento', 'total'));
    }

    /**
     * Meus agendamentos (histórico da sessão, sem login).
     */
    public function meus(Request $request)
    {
        $ids = $request->session()->get('senhabaixa.meus_ids', []);

        $agendamentos = Agendamento::query()
            ->whereIn('id', $ids)
            ->with('grupo')
            ->latest()
            ->get();

        return view('senhabaixa.meus', compact('agendamentos'));
    }
}
