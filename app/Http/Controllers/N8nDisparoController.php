<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Configuracao;
use App\Services\TelegramDisparoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Worker de disparos para ambientes sem cron/fila (Vercel/serverless).
 *
 * O n8n chama `GET /api/n8n/disparos/processar` a cada minuto
 * (cron `* * * * *`, timezone America/Sao_Paulo) com o cabeçalho
 * `X-N8N-Secret: {N8N_WORKER_SECRET}`. A rota busca os agendamentos
 * pendentes com horário atingido, envia (sessão MTProto da conta ou,
 * sem sessão, bot do sistema) e atualiza os status, devolvendo um
 * JSON-resumo para o monitoramento do n8n.
 *
 * Instruções completas de configuração do n8n em:
 * `.docs/INSTRUCOES/n8n-disparos-cron.md`
 *
 * Mapeamento prompt → projeto: `mensagem` = `texto` (+ `textoMensagem()`),
 * `grupo_id` = `grupo_telegram_id`, `total` = `total_grupo_horario`,
 * `tipo` (aberto/fechado) = `modo` (`tipo` é o tipo de chat do Telegram).
 */
class N8nDisparoController extends Controller
{
    /**
     * Autorização do worker (fail closed): chave `X-N8N-Secret` (n8n ou
     * disparos-cron local) OU Bearer `CRON_SECRET` (cron da Vercel, que
     * não envia headers custom).
     */
    protected function autorizadoWorker(Request $request): bool
    {
        $esperado = (string) config('services.n8n.secret', '');

        if ($esperado !== '' && hash_equals($esperado, (string) $request->header('X-N8N-Secret', ''))) {
            return true;
        }

        $cron = (string) config('services.cron.secret', '');
        $bearer = (string) ($request->bearerToken() ?? '');

        return $cron !== '' && $bearer !== '' && hash_equals($cron, $bearer);
    }

    public function processar(Request $request, TelegramDisparoService $disparo): JsonResponse
    {
        if (! $this->autorizadoWorker($request)) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        $agora = now('America/Sao_Paulo');
        $processados = 0;
        $enviados = 0;
        $erros = 0;
        $ignorados = 0;

        // Precisão: com antecipação configurada (só local), busca disparos
        // até N segundos no futuro e segura cada envio até o instante de
        // disparo — o :00.000 menos a latência medida — para a mensagem
        // chegar no Telegram no segundo 00 (meta 00s:00x).
        $antecipacao = max(0, (int) config('services.disparo.antecipacao_segundos', 0));
        $latenciaAtiva = Configuracao::latenciaAtiva();
        $latenciaMs = $latenciaAtiva ? Configuracao::latenciaMs() : 0;

        if ($antecipacao > 0) {
            @set_time_limit(0);
            ignore_user_abort(true);
        }

        $horizonte = $agora->copy()->addSeconds($antecipacao);

        // Ordem = horário, grupo, confirmação (posição); lote de 50.
        $agendamentos = Agendamento::with('grupo')
            ->where('status', Agendamento::STATUS_PENDENTE)
            ->where('data_hora_envio', '<=', $horizonte)
            ->orderBy('data_hora_envio')
            ->orderBy('grupo_telegram_id')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($agendamentos as $agendamento) {
            $processados++;
            $grupo = $agendamento->grupo;

            if (! $grupo || ! filled($grupo->chat_id)) {
                $agendamento->marcarComoErro('Grupo/chat_id inválido');
                $erros++;

                continue;
            }

            // Instante exato do envio (:00.000 do minuto, ou horário de
            // disparo em bloco) menos a latência medida, para a chegada
            // ocorrer no segundo 00.
            $alvo = $this->alvoEnvio($agendamento, $grupo);
            $disparoEmTs = $alvo->valueOf() / 1000 - $latenciaMs / 1000;
            $espera = $disparoEmTs - microtime(true);

            if ($espera > 0.05) {
                if ($antecipacao <= 0 || $espera > $antecipacao + 2 + $latenciaMs / 1000) {
                    $ignorados++;

                    continue; // ainda não é hora — mantém pendente
                }

                time_sleep_until($disparoEmTs);
                $agoraItem = now('America/Sao_Paulo');
            } else {
                $agoraItem = now('America/Sao_Paulo');
            }

            // ⏰ Grupo fechado: só envia no horário de disparo (bloco),
            // com tolerância da latência de antecipação (o envio legítimo
            // ocorre latencia_ms antes do horário).
            if ($grupo->ehFechado() && $grupo->horario_disparo) {
                $horaDisparo = $agendamento->data_hora_envio
                    ->copy()
                    ->setTimeFromTimeString($grupo->horario_disparo->format('H:i'));

                if ($agoraItem->valueOf() < $horaDisparo->valueOf() - $latenciaMs) {
                    $ignorados++;

                    continue; // mantém pendente para a próxima chamada
                }
            }

            // Evita duplo envio se outra execução pegou o item durante a espera.
            $fresco = Agendamento::select('id', 'status')->find($agendamento->id);

            if (! $fresco || $fresco->status !== Agendamento::STATUS_PENDENTE) {
                $ignorados++;

                continue;
            }

            // Envia pela sessão MTProto da conta (ou bot, sem sessão).
            // O serviço resolve o chat_id vivo e o prefixa no erro.
            $envio = $disparo->enviar($agendamento);

            if ($envio['ok']) {
                // Posição recalculada no envio (exclui o próprio registro).
                if ($grupo->ehFechado()) {
                    $posicao = Agendamento::proximaPosicaoRodada(
                        $grupo->id, $agendamento->data_hora_envio, $agendamento->id
                    );
                    $total = Agendamento::totalRodada(
                        $grupo->id, $agendamento->data_hora_envio, $agendamento->id
                    ) + 1;
                } else {
                    $posicao = Agendamento::proximaPosicao(
                        $grupo->id, $agendamento->data_hora_envio, $agendamento->id
                    );
                    $total = Agendamento::totalGrupoHorario(
                        $grupo->id, $agendamento->data_hora_envio, $agendamento->id
                    ) + 1;
                }

                $agendamento->update([
                    'status' => Agendamento::STATUS_ENVIADO,
                    'enviado_em' => now('America/Sao_Paulo'),
                    'posicao' => $posicao,
                    'total_grupo_horario' => $total,
                    'mensagem_erro' => null,
                    'telegram_message_id' => $envio['message_id'] ?? null,
                    'posicao_grupo' => $envio['posicao_grupo'] ?? null,
                    'contexto_grupo' => $envio['contexto'] ?? null,
                ]);
                $enviados++;
            } else {
                $resposta = $envio['resposta'] ?? null;
                $agendamento->marcarComoErro(
                    is_string($resposta) ? $resposta : (json_encode($resposta) ?: 'Falha desconhecida no disparo')
                );
                $erros++;
            }
        }

        return response()->json([
            'executado_em' => $agora->toDateTimeString(),
            'processados' => $processados,
            'enviados' => $enviados,
            'erros' => $erros,
            'ignorados' => $ignorados,
            'antecipacao_segundos' => $antecipacao,
            'latencia_ativa' => $latenciaAtiva,
            'latencia_ms' => $latenciaMs,
        ]);
    }

    /**
     * Parâmetros de precisão para o serviço disparos-cron (mesma chave
     * secreta do processar). O cron dispara adiantado pela latência.
     */
    public function precisao(Request $request): JsonResponse
    {
        if (! $this->autorizadoWorker($request)) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        return response()->json([
            'latencia_ativa' => Configuracao::latenciaAtiva(),
            'latencia_ms' => Configuracao::latenciaMs(),
            'antecipacao_segundos' => max(0, (int) config('services.disparo.antecipacao_segundos', 0)),
        ]);
    }

    /**
     * Instante exato do envio (:00.000 do minuto). Grupo fechado usa o
     * horário de disparo em bloco da rodada.
     */
    public function alvoEnvio(Agendamento $agendamento, \App\Models\GrupoTelegram $grupo): \Carbon\Carbon
    {
        if ($grupo->ehFechado() && $grupo->horario_disparo) {
            return $agendamento->data_hora_envio
                ->copy()
                ->setTimeFromTimeString($grupo->horario_disparo->format('H:i'))
                ->startOfMinute();
        }

        return $agendamento->data_hora_envio->copy()->startOfMinute();
    }
}
