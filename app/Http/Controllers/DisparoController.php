<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarSenhaTelegram;
use App\Models\Agendamento;
use Illuminate\Http\Request;

class DisparoController extends Controller
{
    /**
     * Dispara imediatamente um agendamento pendente ou com erro.
     */
    public function dispararAgora(Agendamento $agendamento)
    {
        if (! in_array($agendamento->status, [Agendamento::STATUS_PENDENTE, Agendamento::STATUS_ERRO], true)) {
            return back()->with('aviso', 'Somente agendamentos pendentes ou com erro podem ser disparados.');
        }

        EnviarSenhaTelegram::dispatchSync($agendamento->id);

        $agendamento->refresh();

        if ($agendamento->status === Agendamento::STATUS_ENVIADO) {
            return back()->with('sucesso', "Senha {$agendamento->senha_codigo} enviada com sucesso!");
        }

        return back()->with('erro', 'Falha no disparo: ' . ($agendamento->mensagem_erro ?? 'erro desconhecido'));
    }

    /**
     * Reenfileira um disparo que falhou (ação manual do admin).
     */
    public function reenviar(Agendamento $agendamento)
    {
        $agendamento->update([
            'status' => Agendamento::STATUS_PENDENTE,
            'mensagem_erro' => null,
        ]);

        EnviarSenhaTelegram::dispatch($agendamento->id);

        return back()->with('sucesso', 'Disparo reenfileirado para envio.');
    }

    /**
     * Endpoint do scheduler: enfileira todos os vencidos.
     */
    public function processarFila(Request $request)
    {
        $limite = (int) $request->get('limite', 50);

        $ids = Agendamento::pendentes()->vencidos()
            ->orderBy('data_hora_envio')
            ->limit($limite)
            ->pluck('id');

        foreach ($ids as $id) {
            EnviarSenhaTelegram::dispatch($id);
        }

        return response()->json([
            'enfileirados' => $ids->count(),
            'ids' => $ids->values(),
        ]);
    }
}
