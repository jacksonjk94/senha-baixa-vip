<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Worker de disparos para ambientes sem cron/fila (Vercel/serverless).
 *
 * O n8n chama `GET /api/n8n/disparos/processar` a cada minuto
 * (cron `* * * * *`, timezone America/Sao_Paulo) com o cabeçalho
 * `X-N8N-Secret: {N8N_WORKER_SECRET}`. A rota busca os agendamentos
 * pendentes com horário atingido, envia via Telegram Bot API e atualiza
 * os status, devolvendo um JSON-resumo para o monitoramento do n8n.
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
    public function processar(Request $request, TelegramBotService $telegram): JsonResponse
    {
        // 🔒 Autorização por chave secreta (comparação em tempo constante;
        // sem segredo configurado, nega tudo — fail closed).
        $esperado = (string) config('services.n8n.secret', '');
        $recebido = (string) $request->header('X-N8N-Secret', '');

        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        $agora = now('America/Sao_Paulo');
        $processados = 0;
        $enviados = 0;
        $erros = 0;
        $ignorados = 0;

        // Ordem = horário, grupo, confirmação (posição); lote de 50.
        $agendamentos = Agendamento::with('grupo')
            ->where('status', Agendamento::STATUS_PENDENTE)
            ->where('data_hora_envio', '<=', $agora)
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

            // ⏰ Grupo fechado: só envia no horário de disparo (bloco).
            if ($grupo->ehFechado() && $grupo->horario_disparo) {
                $horaDisparo = $agendamento->data_hora_envio
                    ->copy()
                    ->setTimeFromTimeString($grupo->horario_disparo->format('H:i'));

                if ($agora->lt($horaDisparo)) {
                    $ignorados++;

                    continue; // mantém pendente para a próxima chamada
                }
            }

            $envio = $telegram->enviarMensagem(
                (string) $grupo->chat_id,
                $agendamento->textoMensagem()
            );

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
                    'enviado_em' => $agora,
                    'posicao' => $posicao,
                    'total_grupo_horario' => $total,
                    'mensagem_erro' => null,
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
        ]);
    }
}
