<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    /**
    * Etapa 4 — Lista os grupos e supergrupos da conta Telegram do
    * visitante (vindos da autorização MTProto) para seleção.
     */
    public function selecionar(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        if (! is_array($request->session()->get('agendafacil.rascunho'))) {
            return redirect()->route('agendafacil.horario');
        }

        $grupos = collect($request->session()->get('agendafacil.telegram_grupos', []))
            ->filter(fn ($grupo) => is_array($grupo)
                && in_array($grupo['tipo'] ?? null, ['group', 'supergroup'], true))
            ->values();

        if ($grupos->isEmpty()) {
            return redirect()
                ->route('agendafacil.horario')
                ->with('aviso', 'Nenhum grupo ou supergrupo encontrado nesta conta do Telegram.');
        }

        // Modo atual de cada grupo (para pré-selecionar aberto/fechado).
        $modos = [];
        if ($visitanteId = $request->session()->get('agendafacil.visitante_id')) {
            $modos = GrupoTelegram::where('usuario_id', $visitanteId)
                ->get(['chat_id', 'modo', 'horario_disparo'])
                ->keyBy(fn ($g) => (string) $g->chat_id)
                ->map(fn ($g) => [
                    'modo' => $g->modo ?? GrupoTelegram::MODO_ABERTO,
                    'horario_disparo' => $g->horario_disparo?->format('H:i'),
                ])
                ->all();
        }

        return view('agendafacil.selecionar-grupo', [
            'grupos' => $grupos,
            'grupo' => $request->session()->get('agendafacil.grupo'),
            'modos' => $modos,
        ]);
    }

    /**
     * Confirma o grupo escolhido (+ se é aberto ou fechado) e segue para
     * a confirmação dos dados.
     */
    public function confirmar(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $dados = $request->validate([
            'chat_id' => ['required', 'string'],
            'modo' => ['required', 'in:aberto,fechado'],
            'horario_disparo' => ['nullable', 'required_if:modo,fechado', 'date_format:H:i'],
        ], [
            'chat_id.required' => 'Confirme o grupo para continuar.',
            'modo.required' => 'Escolha se o grupo é aberto ou fechado.',
            'modo.in' => 'Tipo de grupo inválido.',
            'horario_disparo.required_if' => 'Informe o horário do disparo em bloco.',
            'horario_disparo.date_format' => 'Horário inválido (use HH:MM).',
        ]);

        $grupo = collect($request->session()->get('agendafacil.telegram_grupos', []))
            ->first(fn ($g) => is_array($g) && (string) ($g['chat_id'] ?? '') === $dados['chat_id']);

        if (! $grupo) {
            return back()->withErrors(['chat_id' => 'Grupo selecionado inválido.']);
        }

        $request->session()->put('agendafacil.grupo', [
            'chat_id' => (string) $grupo['chat_id'],
            'nome' => (string) $grupo['nome'],
            'tipo' => (string) ($grupo['tipo'] ?? 'group'),
            'modo' => $dados['modo'],
            'horario_disparo' => $dados['modo'] === GrupoTelegram::MODO_FECHADO
                ? $dados['horario_disparo']
                : null,
        ]);

        return redirect()->route('agendafacil.confirmar-dados');
    }

    /**
     * Legado (fluxo antigo com agendamento manual): mantido oculto,
     * fora do fluxo único — apenas redireciona ao concluído.
     */
    public function confirmarGrupoFinal(Request $request)
    {
        if (! $request->session()->get('agendafacil.grupo')) {
            return redirect()->route('grupos.selecionar');
        }

        return redirect()->route('agendafacil.concluido');
    }

    protected function visitanteAtual(Request $request): ?User
    {
        $id = $request->session()->get('agendafacil.visitante_id');

        if (! $id) {
            return null;
        }

        $acessoEm = (int) $request->session()->get('agendafacil.acesso_em', 0);
        if ($acessoEm > 0 && (time() - $acessoEm) > TelegramValidacaoController::VALIDADE_SEGUNDOS) {
            $request->session()->forget('agendafacil');

            return null;
        }

        return User::find($id);
    }

    protected function agendamentoAtual(Request $request, User $usuario): ?Agendamento
    {
        $idSessao = $request->session()->get('agendafacil.agendamento_id');

        if ($idSessao) {
            $daSessao = Agendamento::with('grupo')->find($idSessao);

            if ($daSessao
                && (int) $daSessao->user_id === (int) $usuario->id
                && $daSessao->status === Agendamento::STATUS_PENDENTE) {
                return $daSessao;
            }
        }

        return Agendamento::with('grupo')
            ->where('user_id', $usuario->id)
            ->where('status', Agendamento::STATUS_PENDENTE)
            ->latest('data_hora_envio')
            ->first();
    }
}
