<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    /**
     * Etapa 4 — Confirmar o grupo (prints 06/07.jpeg).
     * Exibe SOMENTE o grupo do agendamento confirmado na etapa 2,
     * com o botão "Eu confirmo que esse grupo é o correto".
     * Exige etapas 1–3 concluídas (visitante + código ok).
     */
    public function selecionar(Request $request)
    {
        $visitante = $this->visitanteAtual($request);

        if (! $visitante) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.codigo_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $agendamento = $this->agendamentoAtual($request, $visitante);
        $grupo = $agendamento?->grupo;

        if (! $grupo) {
            return redirect()
                ->route('agendafacil.confirmar-dados')
                ->with('aviso', 'Nenhum grupo vinculado ao seu agendamento. Confira os dados.');
        }

        return view('agendafacil.selecionar-grupo', [
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

        if (! $request->session()->get('agendafacil.codigo_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $agendamento = $this->agendamentoAtual($request, $visitante);

        $dados = $request->validate([
            'grupo_id' => ['required', 'exists:grupo_telegrams,id'],
        ], [
            'grupo_id.required' => 'Confirme o grupo para continuar.',
            'grupo_id.exists' => 'Grupo selecionado inválido.',
        ]);

        $grupo = GrupoTelegram::find($dados['grupo_id']);

        if ($agendamento?->grupo_telegram_id && (int) $grupo->id !== (int) $agendamento->grupo_telegram_id) {
            return back()
                ->withErrors(['grupo_id' => 'Este não é o grupo do seu agendamento.'])
                ->withInput();
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

        return $id ? User::find($id) : null;
    }

    protected function agendamentoAtual(Request $request, User $usuario): ?Agendamento
    {
        $idSessao = $request->session()->get('agendafacil.agendamento_id');

        if ($idSessao) {
            $daSessao = Agendamento::with('grupo')->find($idSessao);

            if ($daSessao && (int) $daSessao->user_id === (int) $usuario->id) {
                return $daSessao;
            }
        }

        return Agendamento::with('grupo')
            ->where('user_id', $usuario->id)
            ->orderByRaw("CASE WHEN status = 'pendente' THEN 0 ELSE 1 END")
            ->latest('data_hora_envio')
            ->first();
    }
}
