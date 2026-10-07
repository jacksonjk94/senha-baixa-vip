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

        return view('agendafacil.selecionar-grupo', [
            'grupos' => $grupos,
            'grupo' => $request->session()->get('agendafacil.grupo'),
        ]);
    }

    /**
     * Confirma o grupo escolhido e segue para a confirmação dos dados.
     */
    public function confirmar(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $dados = $request->validate([
            'chat_id' => ['required', 'string'],
        ], [
            'chat_id.required' => 'Confirme o grupo para continuar.',
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
