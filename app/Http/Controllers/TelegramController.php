<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TelegramBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class TelegramController extends Controller
{
    /**
     * Aplica o bot padrão do banco (username + token) sobre o config do
     * Socialite nesta requisição; sem padrão, mantém o fallback do .env.
     */
    protected function aplicarBotPadrao(): ?TelegramBot
    {
        $bot = TelegramBot::defaultBot();

        if ($bot) {
            config()->set('services.telegram.bot', $bot->username);
            config()->set('services.telegram.client_secret', $bot->token);
            config()->set('services.telegram.token', $bot->token);
        }

        return $bot;
    }

    /**
     * Etapa 3 — Exibe o widget de login do Telegram.
     * O provider Telegram não usa redirect OAuth clássico: redirect() devolve
     * o HTML com o botão do widget, que redireciona ao callback após autorizar.
     * Se o provider não estiver configurado, segue em modo degradado
     * (mantém o fluxo navegável e registra o motivo no log).
     */
    public function connect(Request $request): Response|RedirectResponse
    {
        // O widget posta os dados de volta no data-auth-url. Se o
        // TELEGRAM_REDIRECT_URI estiver vazio/desatualizado, o Telegram
        // devolve para a página atual (/connect) e o fluxo trava aqui.
        // Encaminha para o callback preservando a query para concluir.
        if ($request->filled(['id', 'hash'])) {
            Log::warning('Telegram retornou no /connect; encaminhando ao callback.', [
                'redirect_configurado' => (bool) filled(config('services.telegram.redirect')),
            ]);

            return redirect()->route('telegram.callback', $request->query());
        }

        $this->aplicarBotPadrao();

        if (! filled(config('services.telegram.client_secret'))) {
            Log::warning('Telegram não configurado: TELEGRAM_TOKEN ausente. Seguindo fluxo sem OAuth.');

            return redirect()
                ->route('agendafacil.codigo')
                ->with('aviso', 'Telegram ainda não configurado (.env). Fluxo em modo demonstração.');
        }

        try {
            $botao = Socialite::driver('telegram')->getButton();

            return response()->view('agendafacil.conectar-telegram', [
                'botaoWidget' => is_string($botao) ? $botao : (string) $botao,
                'bot' => (string) config('services.telegram.bot', ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao iniciar OAuth Telegram', ['erro' => $e->getMessage()]);

            return redirect()
                ->route('agendafacil.codigo')
                ->with('aviso', 'Não foi possível conectar ao Telegram agora. Continuando em modo demonstração.');
        }
    }

    /**
     * Callback do Socialite: armazena telegram_id/username no usuário.
     */
    public function callback()
    {
        // O hash do callback é validado com o token: precisa ser o do bot padrão.
        $this->aplicarBotPadrao();

        try {
            $telegramUser = Socialite::driver('telegram')->user();
        } catch (\Throwable $e) {
            Log::error('Falha no callback Telegram', ['erro' => $e->getMessage()]);

            return redirect()
                ->route('agendafacil.codigo')
                ->with('aviso', 'Falha na autorização do Telegram. Continuando em modo demonstração.');
        }

        $visitanteId = session('agendafacil.visitante_id');
        $usuario = $visitanteId ? User::find($visitanteId) : Auth::user();

        if ($usuario) {
            $usuario->forceFill([
                'telegram_id' => $telegramUser->getId(),
                'telegram_username' => $telegramUser->getNickname()
                    ?? $telegramUser->getName()
                    ?? $telegramUser->getEmail(),
                'telegram_token' => $telegramUser->token ?? null,
            ])->save();
        }

        session()->put('agendafacil.telegram_ok', true);

        return redirect()
            ->route('agendafacil.codigo')
            ->with('sucesso', 'Conta do Telegram conectada com sucesso!');
    }

    /**
     * Desvincula a conta Telegram do usuário autenticado.
     */
    public function disconnect()
    {
        $usuario = Auth::user();

        if ($usuario) {
            $usuario->forceFill([
                'telegram_id' => null,
                'telegram_username' => null,
                'telegram_token' => null,
            ])->save();
        }

        session()->forget('agendafacil.telegram_ok');

        return back()->with('sucesso', 'Conta do Telegram desconectada.');
    }
}
