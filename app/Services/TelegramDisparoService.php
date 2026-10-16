<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Configuracao;

/**
 * Disparo de mensagens do sistema.
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
     * Envia o texto do agendamento e captura o entorno no grupo.
     *
     * @return array{ok: bool, via: ?string, resposta: mixed, message_id: ?string, posicao_grupo: ?int, contexto: ?array, latencia_ms: int}
     */
    public function enviar(Agendamento $agendamento): array
    {
        $chatId = $this->chatAlvo($agendamento);
        $latenciaMs = Configuracao::latenciaAtiva() ? Configuracao::latenciaMs() : 0;

        if ($chatId === '') {
            return ['ok' => false, 'via' => null, 'resposta' => 'chat_id vazio — grupo sem chat_id cadastrado', 'message_id' => null, 'posicao_grupo' => null, 'contexto' => null, 'latencia_ms' => $latenciaMs];
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
                    $messageId = $this->extrairMessageId('mtproto', $envio['resposta']);
                    $captura = $this->capturarContexto($agendamento, $chatId, $messageId);

                    return ['ok' => true, 'via' => 'mtproto', 'resposta' => $envio['resposta'], 'message_id' => $messageId, 'posicao_grupo' => $captura['posicao_grupo'], 'contexto' => $captura['contexto'], 'latencia_ms' => $latenciaMs];
                }

                // Sessão revogada/expirada: limpa e tenta a próxima conta/bot.
                if ($envio['sessao_invalida']) {
                    $conta->limparSessaoMtproto();

                    continue;
                }

                // Serviço MTProto fora do ar (ex.: serverless sem o Node):
                // mantém a sessão e recorre ao bot.
                if ($envio['falha_conexao'] ?? false) {
                    continue;
                }

                return ['ok' => false, 'via' => 'mtproto', 'resposta' => $this->prefixarChat($chatId, $envio['resposta']), 'message_id' => null, 'posicao_grupo' => null, 'contexto' => null, 'latencia_ms' => $latenciaMs];
            }
        }

        $envio = $this->bot->enviarMensagem($chatId, $texto);

        if ($envio['ok']) {
            return ['ok' => true, 'via' => 'bot', 'resposta' => $envio['resposta'], 'message_id' => $this->extrairMessageId('bot', $envio['resposta']), 'posicao_grupo' => null, 'contexto' => null, 'latencia_ms' => $latenciaMs];
        }

        return ['ok' => false, 'via' => 'bot', 'resposta' => $this->prefixarChat($chatId, $envio['resposta']), 'message_id' => null, 'posicao_grupo' => null, 'contexto' => null, 'latencia_ms' => $latenciaMs];
    }

    /**
     * Id da mensagem recém-enviada em cada canal.
     */
    protected function extrairMessageId(string $via, mixed $resposta): ?string
    {
        if (! is_array($resposta)) {
            return null;
        }

        $id = $via === 'mtproto'
            ? ($resposta['messageId'] ?? null)
            : ($resposta['result']['message_id'] ?? null);

        return $id === null ? null : (string) $id;
    }

    /**
     * Lê as recentes do grupo e localiza a mensagem enviada (1 = a mais
     * recente). Retorna ['posicao_grupo'=>?int, 'contexto'=>?array].
     */
    protected function capturarContexto(Agendamento $agendamento, string $chatId, ?string $messageId, int $limite = 10): array
    {
        $live = $this->contextoAtual($agendamento, $chatId, $messageId, $limite);

        if (! $live['ok']) {
            return ['posicao_grupo' => null, 'contexto' => null];
        }

        return [
            'posicao_grupo' => $live['posicao'],
            'contexto' => [
                'capturado_em' => now('America/Sao_Paulo')->toDateTimeString(),
                'mensagens' => $live['mensagens'],
                'posicao' => $live['posicao'],
            ],
        ];
    }

    /**
     * Histórico ao vivo do grupo via sessão da conta + posição da mensagem.
     * Com $offsetId, continua a paginação; $jaLidas desloca a posição global.
     *
     * @return array{ok: bool, mensagens: array, posicao: ?int, tem_mais: bool, next_offset_id: ?string}
     */
    public function contextoAtual(Agendamento $agendamento, ?string $chatId = null, ?string $messageId = null, int $limite = 15, ?int $offsetId = null, int $jaLidas = 0): array
    {
        $vazio = ['ok' => false, 'mensagens' => [], 'posicao' => null, 'tem_mais' => false, 'next_offset_id' => null];

        if (! $this->mtproto->configurado()) {
            return $vazio;
        }

        $chatId ??= $this->chatAlvo($agendamento);
        $messageId ??= $agendamento->telegram_message_id;

        if ($chatId === '') {
            return $vazio;
        }

        foreach ($this->candidatosSessao($agendamento) as $conta) {
            if (! $conta->mtprotoSessaoValida()) {
                continue;
            }

            $historico = $this->mtproto->obterHistorico(
                (string) $conta->telegram_mtproto_session,
                $chatId,
                $limite,
                $offsetId
            );

            if (! $historico['ok']) {
                if ($historico['sessao_invalida']) {
                    $conta->limparSessaoMtproto();

                    continue;
                }

                continue;
            }

            $mensagens = array_values($historico['mensagens']);
            $posicao = null;

            if (filled($messageId)) {
                foreach ($mensagens as $i => $mensagem) {
                    if ((string) ($mensagem['id'] ?? '') === (string) $messageId) {
                        $posicao = $jaLidas + $i + 1;

                        break;
                    }
                }
            }

            $ultima = end($mensagens);

            return [
                'ok' => true,
                'mensagens' => $mensagens,
                'posicao' => $posicao,
                'tem_mais' => count($mensagens) >= max(1, min(30, $limite)),
                'next_offset_id' => $ultima ? (string) ($ultima['id'] ?? '') : null,
            ];
        }

        return $vazio;
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
