<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramFotoController extends Controller
{
    /**
     * Foto de perfil do visitante (sessão do fluxo). Sem sessão válida,
     * retorna 404 para o Blade cair no fallback de iniciais.
     */
    public function usuario(Request $request, TelegramBotService $telegram): Response
    {
        $telegramUser = $request->session()->get('agendafacil.telegram_user');
        $telegramId = is_array($telegramUser) ? trim((string) ($telegramUser['id'] ?? '')) : '';

        if ($telegramId === '' || $telegramId === '0') {
            abort(404);
        }

        $foto = $telegram->baixarFotoPerfilUsuario($telegramId);

        if (! $foto) {
            abort(404);
        }

        return response($foto['bytes'], 200, [
            'Content-Type' => $foto['content_type'] ?? 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Foto de perfil de um visitante (área admin — rota protegida por
     * auth+admin). Sem telegram_id ou sem foto, 404 para o Blade cair
     * no fallback de iniciais.
     */
    public function visitante(\App\Models\User $visitante, TelegramBotService $telegram): Response
    {
        $telegramId = trim((string) ($visitante->telegram_id ?? ''));

        if ($telegramId === '') {
            abort(404);
        }

        $foto = $telegram->baixarFotoPerfilUsuario($telegramId);

        if (! $foto) {
            abort(404);
        }

        return response($foto['bytes'], 200, [
            'Content-Type' => $foto['content_type'] ?? 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Foto do grupo (somente se o chat_id estiver na sessão do visitante
     * ou for o destino do agendamento concluído).
     */
    public function grupo(Request $request, string $chatId, TelegramBotService $telegram): Response
    {
        $chatId = trim(urldecode($chatId));

        $permitido = collect($request->session()->get('agendafacil.telegram_grupos', []))
            ->filter(fn ($g) => is_array($g) && (string) ($g['chat_id'] ?? '') === $chatId)
            ->isNotEmpty()
            || (string) ($request->session()->get('agendafacil.grupo.chat_id') ?? '') === $chatId;

        if (! $permitido) {
            $agendamentoId = $request->session()->get('agendafacil.agendamento_id');

            if ($agendamentoId) {
                $agendamento = \App\Models\Agendamento::find($agendamentoId);
                $permitido = $agendamento && (string) ($agendamento->destino_id ?? '') === $chatId;
            }
        }

        if ($chatId === '' || ! $permitido) {
            abort(404);
        }

        $foto = $telegram->baixarFotoChat($chatId);

        if (! $foto) {
            abort(404);
        }

        return response($foto['bytes'], 200, [
            'Content-Type' => $foto['content_type'] ?? 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
