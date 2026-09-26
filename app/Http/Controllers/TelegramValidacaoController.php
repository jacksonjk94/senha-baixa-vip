<?php

namespace App\Http\Controllers;

use App\Models\TelegramBot;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Validação da pessoa pelo PRÓPRIO Telegram (Login Widget oficial).
 *
 * Perna independente do robô: o bot do sistema (token fixo) continua sendo
 * o único que envia mensagens. Aqui só se CONFIRMA a identidade, criando
 * uma sessão local temporária (2h). Nada da conta da pessoa é guardado
 * ou usado para enviar mensagens.
 */
class TelegramValidacaoController extends Controller
{
    /** Validade da sessão local de validação (segundos). */
    public const VALIDADE_SEGUNDOS = 7200;

    /** Idade máxima do auth_date do widget (segundos, recomendado pelo Telegram). */
    public const AUTH_DATE_MAXIMO = 86400;

    /**
     * Dados do bot para o widget: username (sem @), URL de callback e avisos.
     *
     * @return array{username: ?string, temBot: bool, usandoEnv: bool, dominio: ?string}
     */
    public static function dadosBotValidacao(): array
    {
        $bot = TelegramBot::defaultBot();

        $username = $bot?->username ?: (string) config('services.telegram.bot', '');
        $username = ltrim(trim($username), '@');

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: null;

        return [
            'username' => $username !== '' ? $username : null,
            'temBot' => $username !== '',
            'usandoEnv' => $bot === null,
            'dominio' => $host,
        ];
    }

    /**
     * Token usado para validar o hash do widget: bot padrão ou .env.
     */
    public static function tokenValidacao(): ?string
    {
        $token = TelegramBot::defaultBot()?->token
            ?? config('services.telegram.token');

        return filled($token) ? (string) $token : null;
    }

    /**
     * Validação oficial do Login Widget do Telegram.
     */
    public static function hashValido(array $dados, string $token): bool
    {
        if (! isset($dados['hash']) || ! is_string($dados['hash']) || $dados['hash'] === '') {
            return false;
        }

        $recebido = $dados['hash'];
        unset($dados['hash']);

        ksort($dados);
        $linhas = [];
        foreach ($dados as $chave => $valor) {
            $linhas[] = $chave.'='.$valor;
        }

        $checkString = implode("\n", $linhas);
        $secretKey = hash('sha256', $token, true);
        $calculado = hash_hmac('sha256', $checkString, $secretKey);

        return hash_equals($calculado, $recebido);
    }

    /**
     * Valida o payload do widget (hash oficial + auth_date). Retorna a
     * mensagem de erro ou null quando válido.
     */
    private static function erroPayload(array $dados): ?string
    {
        $token = self::tokenValidacao();

        if ($token === null) {
            return 'Validação indisponível: nenhum bot configurado.';
        }

        if (! isset($dados['id'], $dados['auth_date'])
            || ! is_numeric($dados['id'])
            || ! is_numeric($dados['auth_date'])
            || abs(time() - (int) $dados['auth_date']) > self::AUTH_DATE_MAXIMO
            || ! self::hashValido($dados, $token)
        ) {
            return 'Não foi possível validar pelo Telegram. Tente novamente.';
        }

        return null;
    }

    /**
     * Visitante identificado na etapa 1 com sessão local ainda válida (2h).
     */
    private static function visitanteDaSessao(Request $request): ?User
    {
        $id = $request->session()->get('agendafacil.visitante_id');

        if (! $id) {
            return null;
        }

        $acessoEm = (int) $request->session()->get('agendafacil.acesso_em', 0);
        if ($acessoEm > 0 && (time() - $acessoEm) > self::VALIDADE_SEGUNDOS) {
            $request->session()->forget('agendafacil');

            return null;
        }

        return User::find($id);
    }

    /**
     * Confirmação da etapa 3 (GET /telegram/confirmar-callback): o visitante,
     * já identificado na etapa 1, faz login no próprio Telegram para provar
     * que é ele. A conta precisa ser a vinculada ao cadastro.
     */
    public function confirmar(Request $request)
    {
        $dados = $request->query();

        if ($erro = self::erroPayload($dados)) {
            return redirect()->route('agendafacil.codigo')
                ->withErrors(['telegram' => $erro]);
        }

        $visitante = self::visitanteDaSessao($request);

        if (! $visitante) {
            return redirect()->route('agendafacil.acesso');
        }

        $username = isset($dados['username']) ? ltrim((string) $dados['username'], '@') : '';

        $confere = (string) ($visitante->telegram_id ?? '') !== ''
            ? (string) $visitante->telegram_id === (string) $dados['id']
            : ($username !== '' && $visitante->telegram_username === $username);

        if (! $confere) {
            return redirect()->route('agendafacil.codigo')
                ->withErrors(['telegram' => 'Essa conta do Telegram não é a vinculada ao seu cadastro. Use o código do grupo ou peça a vinculação ao responsável.']);
        }

        // Vincula o telegram_id na confirmação (não guarda sessão do Telegram).
        if ((string) ($visitante->telegram_id ?? '') === '') {
            $visitante->forceFill(['telegram_id' => (string) $dados['id']])->save();
        }

        $request->session()->put('agendafacil.codigo_ok', true);
        $request->session()->put('agendafacil.telegram_ok', true);
        $request->session()->put('agendafacil.acesso_em', time());

        return redirect()->route('grupos.selecionar');
    }

    /**
     * Sai do fluxo: limpa a sessão local (não toca na conta do Telegram).
     */
    public function sair(Request $request)
    {
        // Todo o fluxo usa o namespace `agendafacil.*` da sessão.
        $request->session()->forget('agendafacil');

        return redirect()->route('agendafacil.acesso');
    }
}
