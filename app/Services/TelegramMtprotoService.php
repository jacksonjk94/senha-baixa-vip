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
