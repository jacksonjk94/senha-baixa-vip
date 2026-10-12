<?php

namespace App\Services;

use App\Models\GrupoTelegram;
use App\Models\TelegramBot;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected ?string $token;
    protected ?string $username;
    protected string $apiBase;

    public function __construct(?TelegramBot $bot = null, ?string $token = null, ?string $username = null)
    {
        $bot ??= TelegramBot::defaultBot();

        $this->token = $token ?? $bot?->token ?? config('services.telegram.token');
        $this->username = $username ?? $bot?->username ?? config('services.telegram.bot');
        $this->apiBase = 'https://api.telegram.org/bot' . $this->token;
    }

    /**
     * Nova instância vinculada a um bot específico (para testes por bot).
     */
    public function forBot(TelegramBot $bot): self
    {
        return new self($bot);
    }

    /**
     * Bot em uso (registro padrão do banco ou fallback do .env).
     */
    public function bot(): ?TelegramBot
    {
        return TelegramBot::defaultBot();
    }

    public function botUsername(): string
    {
        return (string) ($this->username ?? '');
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
     * Verifica se o bot enxerga o chat (getChat). Útil para diagnosticar
     * "chat not found": bot fora do grupo, chat_id antigo (grupo migrado
     * para supergrupo muda o id) ou token/bot trocado.
     *
     * @return array{ok: bool, nome: ?string, tipo: ?string, erro: ?string}
     */
    public function verificarChat(string $chatId): array
    {
        $chatId = trim($chatId);

        if (! $this->configurado()) {
            return ['ok' => false, 'nome' => null, 'tipo' => null, 'erro' => 'TELEGRAM_TOKEN não configurado'];
        }

        if ($chatId === '') {
            return ['ok' => false, 'nome' => null, 'tipo' => null, 'erro' => 'chat_id vazio'];
        }

        try {
            $response = Http::timeout(10)->get("{$this->apiBase}/getChat", ['chat_id' => $chatId]);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                $result = $response->json('result', []);

                return [
                    'ok' => true,
                    'nome' => $result['title'] ?? ($result['username'] ?? null),
                    'tipo' => $result['type'] ?? null,
                    'erro' => null,
                ];
            }

            return [
                'ok' => false,
                'nome' => null,
                'tipo' => null,
                'erro' => $response->body() ?: 'Chat não encontrado pelo bot',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'nome' => null, 'tipo' => null, 'erro' => $e->getMessage()];
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
     * Tenta descobrir o chat_id de um usuário pelo @username, varrendo as
     * atualizações recentes (mensagens e eventos de membro). Permite vincular
     * o visitante automaticamente, sem passar pelo widget, desde que ele já
     * tenha iniciado conversa com o bot (/start).
     */
    public function resolverChatIdPorUsername(string $username): ?string
    {
        $alvo = strtolower(ltrim(trim($username), '@'));

        if ($alvo === '' || ! $this->configurado()) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get("{$this->apiBase}/getUpdates", [
                'allowed_updates' => json_encode(['message', 'my_chat_member', 'channel_post', 'callback_query']),
                'limit' => 100,
            ]);

            if (! $response->successful()) {
                return null;
            }

            foreach ($response->json('result', []) as $update) {
                $candidatos = [
                    $update['message']['from'] ?? null,
                    $update['message']['chat'] ?? null,
                    $update['callback_query']['from'] ?? null,
                    $update['my_chat_member']['from'] ?? null,
                ];

                foreach ($candidatos as $chat) {
                    if (! is_array($chat) || ! isset($chat['id'])) {
                        continue;
                    }

                    $nome = strtolower((string) ($chat['username'] ?? ''));

                    if ($nome !== '' && $nome === $alvo) {
                        return (string) $chat['id'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao resolver chat_id por username', ['erro' => $e->getMessage()]);
        }

        return null;
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

    /**
     * File_id da maior foto de perfil do usuário (ou null se não houver /
     * bot não tiver acesso). Usa getUserProfilePhotos da Bot API.
     */
    public function fotoPerfilFileId(string $telegramUserId): ?string
    {
        if (! $this->configurado() || trim($telegramUserId) === '') {
            return null;
        }

        $cacheKey = 'tg:foto_perfil_file_id:' . trim($telegramUserId);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($telegramUserId) {
            try {
                $response = Http::timeout(10)->get("{$this->apiBase}/getUserProfilePhotos", [
                    'user_id' => trim($telegramUserId),
                    'limit' => 1,
                ]);

                if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                    return null;
                }

                $fotos = $response->json('result.photos', []);
                $maior = $fotos[0] ?? null;

                if (! is_array($maior) || empty($maior)) {
                    return null;
                }

                $ultimo = end($maior);

                return is_array($ultimo) ? ($ultimo['file_id'] ?? null) : null;
            } catch (\Throwable $e) {
                Log::warning('Falha ao buscar foto de perfil Telegram', ['erro' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * File_id da foto do grupo/canal (ou null se o bot não for membro ou
     * não houver foto). Usa getChat da Bot API.
     */
    public function fotoChatFileId(string $chatId): ?string
    {
        if (! $this->configurado() || trim($chatId) === '') {
            return null;
        }

        $cacheKey = 'tg:foto_chat_file_id:' . md5(trim($chatId));

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($chatId) {
            try {
                $response = Http::timeout(10)->get("{$this->apiBase}/getChat", [
                    'chat_id' => trim($chatId),
                ]);

                if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                    return null;
                }

                $foto = $response->json('result.photo', []);

                if (! is_array($foto)) {
                    return null;
                }

                return $foto['big_file_id'] ?? $foto['small_file_id'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Falha ao buscar foto do chat Telegram', ['erro' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Baixa os bytes de um file_id da Bot API (via getFile + download).
     *
     * @return array{bytes: string, content_type: string}|null
     */
    public function baixarArquivoPorFileId(string $fileId): ?array
    {
        if (! $this->configurado() || trim($fileId) === '') {
            return null;
        }

        try {
            $resposta = Http::timeout(10)->get("{$this->apiBase}/getFile", [
                'file_id' => trim($fileId),
            ]);

            if (! $resposta->successful() || ! ($resposta->json('ok') ?? false)) {
                return null;
            }

            $filePath = (string) ($resposta->json('result.file_path') ?? '');

            if ($filePath === '') {
                return null;
            }

            return $this->baixarArquivoPorPath($filePath);
        } catch (\Throwable $e) {
            Log::warning('Falha ao baixar arquivo do Telegram', ['erro' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{bytes: string, content_type: string}|null
     */
    protected function baixarArquivoPorPath(string $filePath): ?array
    {
        $url = 'https://api.telegram.org/file/bot' . $this->token . '/' . ltrim($filePath, '/');

        try {
            $resposta = Http::timeout(15)->get($url);

            if (! $resposta->successful() || $resposta->body() === '') {
                return null;
            }

            $contentType = (string) ($resposta->header('Content-Type') ?: '');

            if (! str_starts_with($contentType, 'image/')) {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $contentType = match ($ext) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };
            }

            return ['bytes' => $resposta->body(), 'content_type' => $contentType];
        } catch (\Throwable $e) {
            Log::warning('Falha ao baixar foto do Telegram', ['erro' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Bytes da foto de perfil do usuário, com cache em disco (24h).
     *
     * @return array{bytes: string, content_type: string}|null
     */
    public function baixarFotoPerfilUsuario(string $telegramUserId): ?array
    {
        $telegramUserId = trim($telegramUserId);

        if ($telegramUserId === '') {
            return null;
        }

        return $this->baixarComCacheDisco(
            'usuario_' . preg_replace('/[^0-9A-Za-z_-]/', '', $telegramUserId),
            fn () => ($fileId = $this->fotoPerfilFileId($telegramUserId))
                ? $this->baixarArquivoPorFileId($fileId)
                : null
        );
    }

    /**
     * Bytes da foto do grupo, com cache em disco (24h).
     *
     * @return array{bytes: string, content_type: string}|null
     */
    public function baixarFotoChat(string $chatId): ?array
    {
        $chatId = trim($chatId);

        if ($chatId === '') {
            return null;
        }

        return $this->baixarComCacheDisco(
            'grupo_' . md5($chatId),
            fn () => ($fileId = $this->fotoChatFileId($chatId))
                ? $this->baixarArquivoPorFileId($fileId)
                : null
        );
    }

    /**
     * Cache simples em storage/app/telegram-fotos para não hitar a Bot API
     * a cada carregamento de página.
     *
     * @param callable(): ?array{bytes: string, content_type: string} $buscar
     * @return array{bytes: string, content_type: string}|null
     */
    protected function baixarComCacheDisco(string $chave, callable $buscar): ?array
    {
        $dir = storage_path('app/telegram-fotos');

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $base = $dir . DIRECTORY_SEPARATOR . $chave;
        $ttl = 86400;

        if (is_file($base . '.bin') && (time() - (int) @filemtime($base . '.bin')) < $ttl) {
            $bytes = @file_get_contents($base . '.bin');

            if (is_string($bytes) && $bytes !== '') {
                $meta = is_file($base . '.json')
                    ? json_decode((string) @file_get_contents($base . '.json'), true)
                    : null;

                return [
                    'bytes' => $bytes,
                    'content_type' => is_array($meta) && filled($meta['content_type'] ?? null)
                        ? (string) $meta['content_type']
                        : 'image/jpeg',
                ];
            }
        }

        $resultado = $buscar();

        if (! is_array($resultado) || empty($resultado['bytes'])) {
            return null;
        }

        @file_put_contents($base . '.bin', $resultado['bytes']);
        @file_put_contents($base . '.json', json_encode(['content_type' => $resultado['content_type'] ?? 'image/jpeg']));

        return $resultado;
    }

    /**
     * Filtra os chats conhecidos pelo bot para os quais o usuário é membro.
     * O Bot API não enumera grupos pessoais; o bot precisa ser administrador
     * para verificar a participação de outros usuários com getChatMember.
     *
     * @return array<int, array{chat_id: string, nome: string, tipo: string}>
     */
    public function listarGruposDoUsuario(string $telegramUserId): array
    {
        if (! $this->configurado() || $telegramUserId === '') {
            return [];
        }

        $candidatos = collect($this->listarGrupos())
            ->concat(GrupoTelegram::ativos()->get(['nome', 'chat_id', 'tipo'])->map(fn (GrupoTelegram $grupo) => [
                'chat_id' => (string) $grupo->chat_id,
                'nome' => $grupo->nome,
                'tipo' => $grupo->tipo,
            ]))
            ->unique('chat_id')
            ->take(100)
            ->values();

        $respostas = Http::pool(function (Pool $pool) use ($candidatos, $telegramUserId) {
            return $candidatos->mapWithKeys(fn (array $grupo, int $indice) => [
                (string) $indice => $pool->as((string) $indice)
                    ->timeout(8)
                    ->get("{$this->apiBase}/getChatMember", [
                        'chat_id' => $grupo['chat_id'],
                        'user_id' => $telegramUserId,
                    ]),
            ])->all();
        }, 10);

        $gruposDoUsuario = [];

        foreach ($candidatos as $indice => $grupo) {
            $response = $respostas[(string) $indice] ?? null;

            if ($response instanceof \Throwable) {
                Log::warning('Falha ao verificar membro do grupo Telegram', [
                    'chat_id' => $grupo['chat_id'],
                    'erro' => $response->getMessage(),
                ]);

                continue;
            }

            if (! $response || ! $response->successful() || ! $response->json('ok')) {
                continue;
            }

            $membro = $response->json('result');
            $status = $membro['status'] ?? '';
            $estaNoGrupo = in_array($status, ['creator', 'administrator', 'member'], true)
                || ($status === 'restricted' && (bool) ($membro['is_member'] ?? false));

            if ($estaNoGrupo) {
                $gruposDoUsuario[] = $grupo;
            }
        }

        return $gruposDoUsuario;
    }
}
