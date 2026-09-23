<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Services\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarSenhaTelegram implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $agendamentoId)
    {
    }

    public function handle(TelegramBotService $telegram): void
    {
        $agendamento = Agendamento::find($this->agendamentoId);

        if (! $agendamento || $agendamento->status !== Agendamento::STATUS_PENDENTE) {
            return;
        }

        $resultado = $telegram->enviarMensagem(
            (string) $agendamento->destino_id,
            $agendamento->textoMensagem()
        );

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
