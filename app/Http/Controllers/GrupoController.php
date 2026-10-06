<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    /**
    * Etapa 4 — Lista os grupos ativos vinculados ao visitante e confirma
    * qual receberá o disparo. Exige validação pelo Login Widget Telegram.
     */
    public function selecionar(Request $request)
    {
        $visitante = $this->visitanteAtual($request);

        if (! $visitante) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $agendamento = $this->agendamentoAtual($request, $visitante);
        $grupo = $agendamento?->grupo;

        $grupos = GrupoTelegram::where('usuario_id', $visitante->id)
            ->ativos()
            ->ordenados()
            ->get();

        if ($grupos->isEmpty()) {
            return redirect()
                ->route('agendafacil.confirmar-dados')
                ->with('aviso', 'Nenhum grupo ativo vinculado ao seu usuário. Verifique os grupos cadastrados.');
        }

        return view('agendafacil.selecionar-grupo', [
            'grupos' => $grupos,
            'grupo' => $grupo,
            'agendamento' => $agendamento,
        ]);
    }

    /**
     * Confirma o grupo exibido e conclui o fluxo (etapa 5).
     */
    public function confirmar(Request $request)
    {
        $visitante = $this->visitanteAtual($request);

        if (! $visitante) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $agendamento = $this->agendamentoAtual($request, $visitante);

        $dados = $request->validate([
            'grupo_id' => ['required', 'exists:grupo_telegrams,id'],
        ], [
            'grupo_id.required' => 'Confirme o grupo para continuar.',
            'grupo_id.exists' => 'Grupo selecionado inválido.',
        ]);

        $grupo = GrupoTelegram::where('id', $dados['grupo_id'])
            ->where('usuario_id', $visitante->id)
            ->ativos()
            ->firstOrFail();

        if ($agendamento) {
            $agendamento->update([
                'grupo_telegram_id' => $grupo->id,
                'destino_tipo' => Agendamento::DESTINO_GRUPO,
                'destino_id' => (string) $grupo->chat_id,
                'destino_nome' => $grupo->nome,
            ]);
        }

        $request->session()->put('agendafacil.grupo', [
            'destino_tipo' => 'grupo',
            'grupo_id' => $grupo->id,
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
        ]);
        $request->session()->put('agendafacil.grupo_ok', true);

        return redirect()->route('agendafacil.concluido');
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
