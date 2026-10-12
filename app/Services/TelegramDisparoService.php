<?php

namespace App\Services;

use App\Models\Agendamento;

/**
 * Disparo de mensagens do AgendaFácil.
 *
 * Canal preferencial: sessão MTProto guardada da conta (envia como a própria
 * pessoa — funciona em grupos onde o bot do sistema não está). Sem sessão
 * válida, recorre ao bot do sistema via Bot API (comportamento anterior).
 */
class TelegramDisparoService
{
    public function __construct(
        protected TelegramMtprotoService $mtproto,
        protected TelegramBotService $bot,
    ) {
    }

    /**
     * Chat alvo do agendamento (chat_id vivo do grupo, sincronizando a cópia
     * do agendamento; ou destino_id para envios individuais).
     */
    public function chatAlvo(Agendamento $agendamento): string
    {
        $agendamento->loadMissing('grupo');

        $grupo = $agendamento->grupo;

        if ($grupo && filled($grupo->chat_id)) {
            $chatId = (string) $grupo->chat_id;

            if ((string) $agendamento->destino_id !== $chatId) {
                $agendamento->forceFill([
                    'destino_id' => $chatId,
                    'destino_nome' => $grupo->nome,
                ])->save();
            }

            return $chatId;
        }

        return (string) $agendamento->destino_id;
    }

    /**
     * Contas candidatas à sessão de envio: dono do agendamento e dono do grupo.
     *
     * @return iterable<\App\Models\User>
     */
    protected function candidatosSessao(Agendamento $agendamento): iterable
    {
        $agendamento->loadMissing('user', 'grupo.usuario');

        $vistos = [];

        foreach ([$agendamento->user, $agendamento->grupo?->usuario] as $candidato) {
            if (! $candidato || isset($vistos[$candidato->id])) {
                continue;
            }

            $vistos[$candidato->id] = true;

            yield $candidato->fresh() ?? $candidato;
        }
    }

    /**
     * Envia o texto do agendamento.
     *
     * @return array{ok: bool, via: ?string, resposta: mixed}
     */
    public function enviar(Agendamento $agendamento): array
    {
        $chatId = $this->chatAlvo($agendamento);

        if ($chatId === '') {
            return ['ok' => false, 'via' => null, 'resposta' => 'chat_id vazio — grupo sem chat_id cadastrado'];
        }

        $texto = $agendamento->textoMensagem();

        if ($this->mtproto->configurado()) {
            foreach ($this->candidatosSessao($agendamento) as $conta) {
                if (! $conta->mtprotoSessaoValida()) {
                    continue;
                }

                $envio = $this->mtproto->enviarMensagemPorSessao(
                    (string) $conta->telegram_mtproto_session,
                    $chatId,
                    $texto
                );

                if ($envio['ok']) {
                    return ['ok' => true, 'via' => 'mtproto', 'resposta' => $envio['resposta']];
                }

                // Sessão revogada/expirada: limpa e tenta a próxima conta/bot.
                if ($envio['sessao_invalida']) {
                    $conta->limparSessaoMtproto();

                    continue;
                }

                return ['ok' => false, 'via' => 'mtproto', 'resposta' => $this->prefixarChat($chatId, $envio['resposta'])];
            }
        }

        $envio = $this->bot->enviarMensagem($chatId, $texto);

        if ($envio['ok']) {
            return ['ok' => true, 'via' => 'bot', 'resposta' => $envio['resposta']];
        }

        return ['ok' => false, 'via' => 'bot', 'resposta' => $this->prefixarChat($chatId, $envio['resposta'])];
    }

    protected function prefixarChat(string $chatId, mixed $resposta): string
    {
        $mensagem = is_string($resposta) ? $resposta : (json_encode($resposta) ?: 'Falha desconhecida no disparo');

        if (! str_contains($mensagem, $chatId)) {
            $mensagem = "[chat {$chatId}] {$mensagem}";
        }

        return $mensagem;
    }
}
