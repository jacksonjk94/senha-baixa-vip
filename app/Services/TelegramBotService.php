<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected ?string $token;
    protected string $apiBase;

    public function __construct()
    {
        $this->token = config('services.telegram.token');
        $this->apiBase = 'https://api.telegram.org/bot' . $this->token;
    }

    public function configurado(): bool
    {
        return filled($this->token);
    }

    /**
     * Envia mensagem de texto para um chat (grupo ou pessoa).
     *
     * @return array{ok: bool, resposta: mixed}
     */
    public function enviarMensagem(string $chatId, string $texto): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'resposta' => 'TELEGRAM_TOKEN não configurado no .env'];
        }

        try {
            $response = Http::timeout(15)->post("{$this->apiBase}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $texto,
            ]);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                return ['ok' => true, 'resposta' => $response->json()];
            }

            Log::warning('Telegram sendMessage falhou', ['resposta' => $response->body()]);

            return ['ok' => false, 'resposta' => $response->body() ?: 'Falha ao enviar via Telegram'];
        } catch (\Throwable $e) {
            Log::error('Erro Telegram sendMessage', ['erro' => $e->getMessage()]);

            return ['ok' => false, 'resposta' => $e->getMessage()];
        }
    }

    /**
     * Valida o token via getMe. Retorna ['ok'=>bool,'nome'=>?string,'erro'=>?string].
     */
    public function testarConexao(): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'nome' => null, 'erro' => 'TELEGRAM_TOKEN não configurado no .env'];
        }

        try {
            $response = Http::timeout(15)->get("{$this->apiBase}/getMe");

            if ($response->successful() && ($response->json('ok') ?? false)) {
                return ['ok' => true, 'nome' => $response->json('result.username'), 'erro' => null];
            }

            return ['ok' => false, 'nome' => null, 'erro' => $response->body() ?: 'Token rejeitado pelo Telegram'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'nome' => null, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Lista grupos/canais visíveis via getUpdates (onde o bot teve atividade).
     * Retorna lista normalizada: [['chat_id','nome','tipo'], ...]
     */
    public function listarGrupos(): array
    {
        if (! $this->configurado()) {
            return [];
        }

        try {
            $response = Http::timeout(15)->get("{$this->apiBase}/getUpdates", [
                'allowed_updates' => json_encode(['message', 'my_chat_member', 'channel_post']),
                'limit' => 100,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram getUpdates falhou', ['resposta' => $response->body()]);

                return [];
            }

            $vistos = [];

            foreach ($response->json('result', []) as $update) {
                $chat = $update['message']['chat']
                    ?? $update['channel_post']['chat']
                    ?? $update['my_chat_member']['chat']
                    ?? null;

                if (! $chat || ! isset($chat['id'])) {
                    continue;
                }

                $tipo = $chat['type'] ?? 'desconhecido';

                // Mantém grupos, supergrupos e canais; ignora chat privado aqui
                // (o envio individual usa o telegram_id do usuário conectado).
                if (! in_array($tipo, ['group', 'supergroup', 'channel'], true)) {
                    continue;
                }

                $id = (string) $chat['id'];

                $vistos[$id] = [
                    'chat_id' => $id,
                    'nome' => $chat['title'] ?? ('Chat ' . $id),
                    'tipo' => $tipo,
                ];
            }

            return array_values($vistos);
        } catch (\Throwable $e) {
            Log::error('Erro Telegram getUpdates', ['erro' => $e->getMessage()]);

            return [];
        }
    }
}
