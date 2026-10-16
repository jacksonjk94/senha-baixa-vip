<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TelegramMtprotoService
{
    public function configurado(): bool
    {
        return filled(config('services.telegram_mtproto.api_id'))
            && filled(config('services.telegram_mtproto.api_hash'))
            && filled(config('services.telegram_mtproto.url'))
            && filled(config('services.telegram_mtproto.service_token'));
    }

    public function enviarCodigo(string $telefone): array
    {
        return $this->post('/v1/auth/send-code', ['phone' => $telefone]);
    }

    public function verificarCodigo(string $operationId, string $codigo, ?string $senha = null): array
    {
        return $this->post('/v1/auth/verify-code', array_filter([
            'operationId' => $operationId,
            'code' => $codigo,
            'password' => $senha,
        ], fn ($value) => $value !== null));
    }

    /**
     * Envia mensagem como a conta do usuário (sessão guardada).
     * Não lança exceção: retorna ['ok'=>bool, 'resposta'=>mixed,
     * 'sessao_invalida'=>bool, 'falha_conexao'=>bool]. Com sessão revogada,
     * o chamador deve limpar a sessão guardada e orientar nova autorização;
     * com falha de conexão (serviço fora, ex.: serverless), o chamador pode
     * recorrer ao bot.
     */
    public function enviarMensagemPorSessao(string $session, string $chatId, string $texto): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'resposta' => 'A integração MTProto não está configurada neste ambiente.', 'sessao_invalida' => false, 'falha_conexao' => true];
        }

        if (trim($session) === '' || trim($chatId) === '' || trim($texto) === '') {
            return ['ok' => false, 'resposta' => 'Sessão, destino ou texto ausente para o envio via MTProto.', 'sessao_invalida' => trim($session) === '', 'falha_conexao' => false];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('services.telegram_mtproto.service_token'))
                ->timeout(45)
                ->post(rtrim((string) config('services.telegram_mtproto.url'), '/') . '/v1/messages/send', [
                    'session' => $session,
                    'chatId' => trim($chatId),
                    'message' => $texto,
                ]);
        } catch (ConnectionException) {
            return ['ok' => false, 'resposta' => 'O serviço local de conexão Telegram não está disponível.', 'sessao_invalida' => false, 'falha_conexao' => true];
        }

        $dados = $response->json();

        if ($response->successful() && ($dados['ok'] ?? false)) {
            return ['ok' => true, 'resposta' => $dados, 'sessao_invalida' => false, 'falha_conexao' => false];
        }

        $sessaoInvalida = $response->status() === 401
            || (is_array($dados) && ($dados['sessionInvalid'] ?? false) === true);

        $mensagem = is_array($dados) && filled($dados['message'] ?? null)
            ? (string) $dados['message']
            : 'Falha no envio via MTProto (HTTP ' . $response->status() . ')';

        \Illuminate\Support\Facades\Log::warning('MTProto envio falhou', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['ok' => false, 'resposta' => $mensagem, 'sessao_invalida' => $sessaoInvalida, 'falha_conexao' => false];
    }

    /**
     * Lê as mensagens recentes de um chat (mais recentes primeiro).
     * Retorna ['ok'=>bool, 'mensagens'=>array|resposta, 'sessao_invalida'=>bool,
     * 'falha_conexao'=>bool]. Cada mensagem: ['id','texto','data','saida'].
     */
    public function obterHistorico(string $session, string $chatId, int $limite = 10, ?int $offsetId = null): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'resposta' => 'A integração MTProto não está configurada neste ambiente.', 'sessao_invalida' => false, 'falha_conexao' => true];
        }

        if (trim($session) === '' || trim($chatId) === '') {
            return ['ok' => false, 'resposta' => 'Sessão ou destino ausente para leitura do histórico.', 'sessao_invalida' => trim($session) === '', 'falha_conexao' => false];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('services.telegram_mtproto.service_token'))
                ->timeout(30)
                ->post(rtrim((string) config('services.telegram_mtproto.url'), '/') . '/v1/messages/history', array_filter([
                    'session' => $session,
                    'chatId' => trim($chatId),
                    'limit' => max(1, min(30, $limite)),
                    'offsetId' => $offsetId,
                ], fn ($value) => $value !== null));
        } catch (ConnectionException) {
            return ['ok' => false, 'resposta' => 'O serviço local de conexão Telegram não está disponível.', 'sessao_invalida' => false, 'falha_conexao' => true];
        }

        $dados = $response->json();

        if ($response->successful() && is_array($dados['mensagens'] ?? null)) {
            return ['ok' => true, 'mensagens' => array_values($dados['mensagens']), 'sessao_invalida' => false, 'falha_conexao' => false];
        }

        $sessaoInvalida = $response->status() === 401
            || (is_array($dados) && ($dados['sessionInvalid'] ?? false) === true);

        $mensagem = is_array($dados) && filled($dados['message'] ?? null)
            ? (string) $dados['message']
            : 'Falha ao ler histórico (HTTP ' . $response->status() . ')';

        return ['ok' => false, 'resposta' => $mensagem, 'sessao_invalida' => $sessaoInvalida, 'falha_conexao' => false];
    }

    /**
     * Encerra a sessão guardada também no Telegram (logout remoto).
     */
    public function encerrarSessao(string $session): bool
    {
        if (! $this->configurado() || trim($session) === '') {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('services.telegram_mtproto.service_token'))
                ->timeout(30)
                ->post(rtrim((string) config('services.telegram_mtproto.url'), '/') . '/v1/auth/logout', [
                    'session' => $session,
                ]);

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    private function post(string $path, array $payload): array
    {
        if (! $this->configurado()) {
            throw ValidationException::withMessages([
                'telegram' => 'A integração MTProto não está configurada neste ambiente.',
            ]);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('services.telegram_mtproto.service_token'))
                ->timeout(45)
                ->post(rtrim((string) config('services.telegram_mtproto.url'), '/') . $path, $payload);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'telegram' => 'O serviço local de conexão Telegram não está disponível.',
            ]);
        }

        $dados = $response->json();

        if (! $response->successful()) {
            if ($response->status() === 422 && is_array($dados) && array_key_exists('needsPassword', $dados)) {
                return $dados + ['falha' => true];
            }

            \Illuminate\Support\Facades\Log::warning('MTProto retornou erro', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'telegram' => is_array($dados) && filled($dados['message'] ?? null)
                    ? (string) $dados['message']
                    : 'Não foi possível autenticar no Telegram (HTTP ' . $response->status() . '). Tente novamente.',
            ]);
        }

        if (! is_array($dados)) {
            throw ValidationException::withMessages([
                'telegram' => 'Resposta inválida do serviço de conexão Telegram.',
            ]);
        }

        return $dados;
    }
}
