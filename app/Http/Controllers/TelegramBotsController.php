<?php

namespace App\Http\Controllers;

use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;

class TelegramBotsController extends Controller
{
    /**
     * Bots gerenciáveis: lista, padrão e ativos.
     */
    public function index()
    {
        return view('admin.telegram-bots', [
            'bots' => TelegramBot::query()->orderByDesc('is_default')->orderBy('name')->paginate(15),
            'envUsername' => (string) config('services.telegram.bot', ''),
            'envHasToken' => filled(config('services.telegram.token')),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'unique:telegram_bots,username'],
            'token' => ['required', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $bot = TelegramBot::create([
            'name' => $dados['name'],
            'username' => $dados['username'],
            'token' => $dados['token'],
            'is_active' => $request->boolean('is_active', true),
            'is_default' => false,
        ]);

        if ($request->boolean('is_default') && $bot->is_active) {
            $bot->markAsDefault();
        }

        return back()->with('sucesso', 'Bot cadastrado!');
    }

    public function update(Request $request, TelegramBot $bot)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'unique:telegram_bots,username,' . $bot->id],
            'token' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bot->fill([
            'name' => $dados['name'],
            'username' => $dados['username'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (filled($dados['token'] ?? null)) {
            $bot->token = $dados['token'];
        }

        // Desativar derruba o padrão.
        if (! $bot->is_active && $bot->is_default) {
            $bot->is_default = false;
        }

        $bot->save();

        return back()->with('sucesso', 'Bot atualizado!');
    }

    public function destroy(TelegramBot $bot)
    {
        $bot->delete();

        return back()->with('sucesso', 'Bot excluído!');
    }

    /**
     * Define o bot padrão (somente ativos).
     */
    public function setDefault(TelegramBot $bot)
    {
        if (! $bot->is_active) {
            return back()->with('erro', 'Ative o bot antes de defini-lo como padrão.');
        }

        $bot->markAsDefault();

        return back()->with('sucesso', "@{$bot->username} definido como padrão!");
    }

    /**
     * Ativa/desativa sem excluir (desativar derruba o padrão).
     */
    public function toggle(TelegramBot $bot)
    {
        $bot->fill(['is_active' => ! $bot->is_active]);

        if (! $bot->is_active) {
            $bot->is_default = false;
        }

        $bot->save();

        return back()->with('sucesso', 'Bot atualizado!');
    }

    /**
     * Importa o bot das vars TELEGRAM_* para o banco (bootstrap rápido).
     */
    public function importEnv(TelegramBotService $telegram)
    {
        $username = (string) config('services.telegram.bot', '');
        $token = (string) config('services.telegram.token', '');

        if (! filled($username) || ! filled($token)) {
            return back()->with('erro', 'TELEGRAM_BOT_NAME/TELEGRAM_TOKEN vazios no ambiente.');
        }

        $bot = TelegramBot::updateOrCreate(
            ['username' => ltrim($username, '@')],
            ['name' => ltrim($username, '@'), 'token' => $token, 'is_active' => true]
        );

        // Valida o token na hora.
        $teste = (new TelegramBotService($bot))->testarConexao();

        if (! $teste['ok']) {
            return back()->with('erro', 'Importado, mas o token foi rejeitado: ' . $teste['erro']);
        }

        $bot->markAsDefault();

        return back()->with('sucesso', "Bot @{$bot->username} importado, validado e definido como padrão!");
    }
}
