<?php

namespace App\Http\Controllers;

use App\Models\GrupoTelegram;
use App\Models\TelegramBot;
use App\Models\User;
use App\Services\TelegramMtprotoService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Validação da pessoa pelo PRÓPRIO Telegram (Login Widget oficial).
 *
 * Perna independente do robô: o bot do sistema (token fixo) continua sendo
 * o único que envia mensagens. O widget confirma a identidade; a etapa
 * MTProto, quando configurada e consentida, consulta grupos e encerra a
 * autorização sem usá-la para enviar mensagens.
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
    public function confirmar(
        Request $request,
        TelegramBotService $telegram,
        TelegramMtprotoService $telegramMtproto
    )
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

        if ($telegramMtproto->configurado()) {
            $request->session()->put('agendafacil.mtproto', [
                'modo' => 'fluxo',
                'usuario_id' => $visitante->id,
                'telegram_id' => (string) $dados['id'],
                'iniciado_em' => time(),
                'expira_em' => time() + 600,
            ]);

            return redirect()->route('telegram.mtproto.autorizacao');
        }

        $this->sincronizarGruposDaConta($visitante, (string) $dados['id'], $telegram);

        $request->session()->put('agendafacil.codigo_ok', true);
        $request->session()->put('agendafacil.telegram_ok', true);
        $request->session()->put('agendafacil.acesso_em', time());

        return redirect()->route('agendafacil.confirmar-dados');
    }

    /**
     * Tela de diagnóstico para validar o Login Widget e o vínculo dos grupos.
     */
    public function testeGrupos(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $request->session()->put('agendafacil.teste_grupos_iniciado_em', time());

        return view('agendafacil.teste-grupos-telegram', [
            ...self::dadosBotValidacao(),
            'usuario' => null,
            'grupos' => collect(),
            'mtprotoConfigurado' => $telegramMtproto->configurado(),
        ]);
    }

    /**
     * Callback do teste: identifica a conta sem abrir uma sessão do fluxo.
     */
    public function confirmarTesteGrupos(
        Request $request,
        TelegramBotService $telegram,
        TelegramMtprotoService $telegramMtproto
    )
    {
        $iniciadoEm = (int) $request->session()->pull('agendafacil.teste_grupos_iniciado_em', 0);

        if ($iniciadoEm === 0 || time() - $iniciadoEm > 600) {
            return redirect()->route('telegram.grupos-teste')
                ->withErrors(['telegram' => 'Inicie novamente o teste de grupos.']);
        }

        $dados = $request->query();

        if ($erro = self::erroPayload($dados)) {
            return redirect()->route('telegram.grupos-teste')
                ->withErrors(['telegram' => $erro]);
        }

        $username = isset($dados['username']) ? ltrim((string) $dados['username'], '@') : '';
        $usuario = User::where('telegram_id', (string) $dados['id'])->first();

        if (! $usuario && $username !== '') {
            $usuario = User::where('telegram_username', $username)->first();
        }

        if (! $usuario
            || (filled($usuario->telegram_id) && (string) $usuario->telegram_id !== (string) $dados['id'])) {
            return redirect()->route('telegram.grupos-teste')
                ->withErrors(['telegram' => 'Conta Telegram sem vínculo compatível com um cadastro de visitante.']);
        }

        if (! filled($usuario->telegram_id)) {
            $usuario->forceFill(['telegram_id' => (string) $dados['id']])->save();
        }

        if ($telegramMtproto->configurado()) {
            $request->session()->put('agendafacil.mtproto', [
                'modo' => 'teste',
                'usuario_id' => $usuario->id,
                'telegram_id' => (string) $dados['id'],
                'iniciado_em' => time(),
                'expira_em' => time() + 600,
            ]);

            return redirect()->route('telegram.mtproto.autorizacao');
        }

        $this->sincronizarGruposDaConta($usuario, (string) $dados['id'], $telegram);

        return view('agendafacil.teste-grupos-telegram', [
            ...self::dadosBotValidacao(),
            'usuario' => $usuario,
            'grupos' => $usuario->gruposTelegram()->ativos()->ordenados()->get(),
            'mtprotoConfigurado' => $telegramMtproto->configurado(),
        ]);
    }

    /** Tela de consentimento e autorização da conta pessoal via MTProto. */
    public function autorizacaoMtproto(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $estado = $this->estadoMtproto($request);

        if (! $estado) {
            return redirect()->route('agendafacil.acesso')
                ->withErrors(['telegram' => 'A verificação expirou. Inicie o login novamente.']);
        }

        if (! $telegramMtproto->configurado()) {
            return redirect()->route($estado['modo'] === 'teste' ? 'telegram.grupos-teste' : 'agendafacil.codigo')
                ->withErrors(['telegram' => 'Configure as variáveis da integração MTProto neste ambiente.']);
        }

        $usuario = User::find($estado['usuario_id']);
        if (! $usuario || (string) $usuario->telegram_id !== (string) $estado['telegram_id']) {
            $request->session()->forget('agendafacil.mtproto');

            return redirect()->route('agendafacil.acesso');
        }

        return view('agendafacil.telegram-mtproto', [
            'usuario' => $usuario,
            'estado' => $estado,
        ]);
    }

    /** Solicita o código de login após consentimento explícito. */
    public function enviarCodigoMtproto(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $estado = $this->estadoMtproto($request);
        abort_unless($estado, 403);

        $dados = $request->validate([
            'consentimento' => ['required', 'accepted'],
            'telefone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
        ], [
            'consentimento.accepted' => 'Confirme que autoriza a leitura da lista de grupos da sua conta.',
            'telefone.regex' => 'Informe o telefone com DDI, por exemplo +5511999999999.',
        ]);

        $codigo = $telegramMtproto->enviarCodigo($dados['telefone']);
        $estado['operation_id'] = $codigo['operationId'];
        $estado['is_code_via_app'] = (bool) ($codigo['isCodeViaApp'] ?? false);
        $estado['needs_password'] = false;
        $estado['expira_em'] = time() + 600;
        $request->session()->put('agendafacil.mtproto', $estado);

        return redirect()->route('telegram.mtproto.autorizacao')
            ->with('telegram_code_enviado', true);
    }

    /** Confirma código (e senha 2FA, quando exigida), lista diálogos e encerra a sessão MTProto. */
    public function verificarCodigoMtproto(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $estado = $this->estadoMtproto($request);
        abort_unless($estado && filled($estado['operation_id'] ?? null), 403);

        $dados = $request->validate($estado['needs_password'] ?? false ? [
            'senha_2fa' => ['required', 'string', 'max:256'],
        ] : [
            'codigo' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ], [
            'codigo.regex' => 'Informe o código numérico recebido do Telegram.',
        ]);

        $resultado = $telegramMtproto->verificarCodigo(
            $estado['operation_id'],
            (string) ($dados['codigo'] ?? ''),
            isset($dados['senha_2fa']) ? (string) $dados['senha_2fa'] : null
        );

        if (! empty($resultado['falha'])) {
            return back()->withErrors(['telegram' => $resultado['message'] ?? 'Não foi possível verificar o código.']);
        }

        if (! empty($resultado['needsPassword'])) {
            $estado['needs_password'] = true;
            $estado['expira_em'] = time() + 600;
            $request->session()->put('agendafacil.mtproto', $estado);

            return redirect()->route('telegram.mtproto.autorizacao')
                ->with('telegram_2fa_required', true);
        }

        $usuario = User::find($estado['usuario_id']);
        $telegramId = (string) ($resultado['user']['id'] ?? '');

        if (! $usuario || $telegramId === '' || $telegramId !== (string) $estado['telegram_id']) {
            $request->session()->forget('agendafacil.mtproto');

            return redirect()->route($estado['modo'] === 'teste' ? 'telegram.grupos-teste' : 'agendafacil.codigo')
                ->withErrors(['telegram' => 'A conta autorizada não corresponde à conta confirmada no Login Widget.']);
        }

        $this->sincronizarGruposMtproto($usuario, $resultado['groups'] ?? []);
        $grupos = $usuario->gruposTelegram()->ordenados()->get();
        $modo = $estado['modo'];
        $request->session()->forget('agendafacil.mtproto');

        if ($modo === 'teste') {
            return view('agendafacil.teste-grupos-telegram', [
                ...self::dadosBotValidacao(),
                'usuario' => $usuario,
                'grupos' => $grupos,
                'origemMtproto' => true,
                'mtprotoConfigurado' => true,
            ]);
        }

        $request->session()->put('agendafacil.codigo_ok', true);
        $request->session()->put('agendafacil.telegram_ok', true);
        $request->session()->put('agendafacil.acesso_em', time());

        return redirect()->route('agendafacil.confirmar-dados');
    }

    public function cancelarMtproto(Request $request)
    {
        $estado = $this->estadoMtproto($request);
        $request->session()->forget('agendafacil.mtproto');

        return redirect()->route(($estado['modo'] ?? 'fluxo') === 'teste'
            ? 'telegram.grupos-teste'
            : 'agendafacil.codigo');
    }

    private function estadoMtproto(Request $request): ?array
    {
        $estado = $request->session()->get('agendafacil.mtproto');

        if (! is_array($estado)
            || empty($estado['usuario_id'])
            || empty($estado['telegram_id'])
            || (int) ($estado['expira_em'] ?? 0) < time()) {
            $request->session()->forget('agendafacil.mtproto');

            return null;
        }

        return $estado;
    }

    /** Persiste somente grupos e supergrupos; nenhuma sessão ou mensagem é armazenada. */
    private function sincronizarGruposMtproto(User $usuario, array $grupos): void
    {
        DB::transaction(function () use ($usuario, $grupos) {
            $gruposValidos = collect($grupos)
                ->filter(fn ($grupo) => is_array($grupo)
                    && in_array($grupo['tipo'] ?? null, ['group', 'supergroup'], true)
                    && filled($grupo['chat_id'] ?? null)
                    && filled($grupo['nome'] ?? null))
                ->keyBy(fn ($grupo) => (string) $grupo['chat_id']);

            $usuario->gruposTelegram()
                ->whereNotIn('chat_id', $gruposValidos->keys()->all())
                ->update(['ativo' => false]);

            foreach ($gruposValidos as $grupo) {

                $chatId = (string) $grupo['chat_id'];
                $existente = $usuario->gruposTelegram()->where('chat_id', $chatId)->first();

                if ($existente) {
                    $existente->update([
                        'nome' => mb_substr((string) $grupo['nome'], 0, 150),
                        'tipo' => $grupo['tipo'],
                        'ativo' => true,
                    ]);

                    continue;
                }

                $configuracao = GrupoTelegram::where('chat_id', $chatId)->orderBy('id')->first();

                $usuario->gruposTelegram()->create([
                    'nome' => mb_substr((string) $grupo['nome'], 0, 150),
                    'chat_id' => mb_substr($chatId, 0, 60),
                    'tipo' => $grupo['tipo'],
                    'modo' => $configuracao?->modo ?? GrupoTelegram::MODO_ABERTO,
                    'horario_abertura' => $configuracao?->horario_abertura?->format('H:i'),
                    'horario_fechamento' => $configuracao?->horario_fechamento?->format('H:i'),
                    'horario_disparo' => $configuracao?->horario_disparo?->format('H:i'),
                    'dias_semana' => $configuracao?->dias_semana,
                    'ordem' => $configuracao?->ordem ?? 0,
                    'ativo' => true,
                ]);
            }
        });
    }

    /**
     * Associa ao visitante os grupos conhecidos pelo bot em que a conta entrou.
     */
    private function sincronizarGruposDaConta(User $usuario, string $telegramUserId, TelegramBotService $telegram): void
    {
        foreach ($telegram->listarGruposDoUsuario($telegramUserId) as $grupoEncontrado) {
            if ($usuario->gruposTelegram()->where('chat_id', $grupoEncontrado['chat_id'])->exists()) {
                continue;
            }

            $configuracao = GrupoTelegram::where('chat_id', $grupoEncontrado['chat_id'])
                ->orderBy('id')
                ->first();

            $usuario->gruposTelegram()->create([
                'nome' => $grupoEncontrado['nome'],
                'chat_id' => $grupoEncontrado['chat_id'],
                'tipo' => $grupoEncontrado['tipo'],
                'modo' => $configuracao?->modo ?? GrupoTelegram::MODO_ABERTO,
                'horario_abertura' => $configuracao?->horario_abertura?->format('H:i'),
                'horario_fechamento' => $configuracao?->horario_fechamento?->format('H:i'),
                'horario_disparo' => $configuracao?->horario_disparo?->format('H:i'),
                'dias_semana' => $configuracao?->dias_semana,
                'ordem' => $configuracao?->ordem ?? 0,
                'ativo' => true,
            ]);
        }
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
