<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Services\TelegramDisparoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarSenhaTelegram implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $agendamentoId)
    {
    }

    public function handle(TelegramDisparoService $disparo): void
    {
        $agendamento = Agendamento::find($this->agendamentoId);

        if (! $agendamento || $agendamento->status !== Agendamento::STATUS_PENDENTE) {
            return;
        }

        $resultado = $disparo->enviar($agendamento);

        if ($resultado['ok']) {
            $agendamento->marcarComoEnviado();
        } else {
            $mensagem = is_string($resultado['resposta'])
                ? $resultado['resposta']
                : json_encode($resultado['resposta']);
            $agendamento->marcarComoErro($mensagem ?: 'Falha desconhecida no disparo');
        }
    }
}
