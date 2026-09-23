<?php

/*
 * Bootstrap do Laravel na Vercel (serverless).
 *
 * O filesystem da Vercel é read-only — só /tmp permite escrita.
 * Este arquivo, carregado antes do public/index.php:
 *  1. Garante diretórios de escrita em /tmp.
 *  2. Troca drivers que exigiriam disco/banco persistente (database/file)
 *     por equivalentes em memória/cookie quando estiver na Vercel.
 *     (As vars do Dashboard continuam valendo para drivers externos
 *     como redis, memcached, sqs etc.)
 *  3. Garante o arquivo SQLite em /tmp a partir do snapshot versionado
 *     database/vercel-seed.sqlite (schema + seeds base), criando as
 *     tabelas do framework caso o arquivo precise ser criado do zero.
 *
 * Idempotente e seguro para rodar a cada cold start.
 */

if (! getenv('VERCEL') && ! isset($_SERVER['VERCEL'], $_ENV['VERCEL'])) {
    return;
}

$readEnv = static function (string $key, $default = null) {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    return $default;
};

$writeEnv = static function (string $key, string $value): void {
    putenv("{$key}={$value}");
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
};

// 0. Vars vazias ("") no Dashboard chegam como string vazia — e drivers com
//    nome "" quebram o Manager (ex.: AUTH_GUARD="" → Auth::attempt() 500 com
//    "Too few arguments ... createDriver(), 0 passed").
//    Preenche ausentes/vazias com padrões seguros para serverless.
$rawEnv = static function (string $key) {
    foreach ([getenv($key), $_SERVER[$key] ?? null, $_ENV[$key] ?? null] as $value) {
        if ($value !== false && $value !== null) {
            return $value;
        }
    }

    return null;
};

foreach ([
    'APP_NAME' => 'AgendaFácil',
    'APP_ENV' => 'production',
    'AUTH_GUARD' => 'web',
    'AUTH_PASSWORD_BROKER' => 'users',
    'BROADCAST_CONNECTION' => 'log',
    'DB_CONNECTION' => 'sqlite',
    'FILESYSTEM_DISK' => 'local',
    'LOG_CHANNEL' => 'stderr',
    'MAIL_MAILER' => 'log',
] as $key => $fallback) {
    $raw = $rawEnv($key);
    if ($raw === null || $raw === '') {
        $writeEnv($key, $fallback);
    }
}

// 1. Diretórios de escrita.
foreach ([
    '/tmp/framework/sessions',
    '/tmp/framework/views',
    '/tmp/framework/cache/data',
    '/tmp/logs',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

$writeEnv('VIEW_COMPILED_PATH', '/tmp');

// 2. Drivers compatíveis com serverless.
// Só substitui valores que quebrariam na Vercel (database/file).
$forceDriver = static function (string $key, string $fallback, array $broken) use ($readEnv, $writeEnv): void {
    $current = strtolower((string) $readEnv($key, ''));
    if ($current === '' || in_array($current, $broken, true)) {
        $writeEnv($key, $fallback);
    }
};

$forceDriver('CACHE_STORE', 'array', ['database', 'file']);
$forceDriver('SESSION_DRIVER', 'cookie', ['database', 'file']);
$forceDriver('QUEUE_CONNECTION', 'sync', ['database']);
$forceDriver('APP_MAINTENANCE_STORE', 'array', ['database', 'file']);

if (strtolower((string) $readEnv('APP_MAINTENANCE_DRIVER', '')) === 'file') {
    $writeEnv('APP_MAINTENANCE_DRIVER', 'cache');
}

// Lifetime <= 0 faz o cookie fixo de sessão (agendafacil-session) expirar
// no ato: o navegador o descarta, cada request gera nova sessão e todo POST
// dá 419 — enquanto os cookies de dados (nome = session id) se acumulam.
// Isso acontece quando SESSION_LIFETIME existe mas está vazia ("") no Dashboard.
$lifetime = (int) $readEnv('SESSION_LIFETIME', 0);
if ($lifetime < 1) {
    $writeEnv('SESSION_LIFETIME', '120');
}

// 3. SQLite em /tmp.
$dbConnection = strtolower((string) $readEnv('DB_CONNECTION', 'sqlite'));
$dbPath = (string) $readEnv('DB_DATABASE', '/tmp/database.sqlite');

if ($dbConnection === 'sqlite' && $dbPath !== '' && $dbPath !== ':memory:') {
    if (! is_file($dbPath)) {
        @mkdir(dirname($dbPath), 0777, true);

        $seed = __DIR__.'/../database/vercel-seed.sqlite';
        if (is_file($seed)) {
            @copy($seed, $dbPath);
        }
        if (! is_file($dbPath)) {
            @touch($dbPath);
        }
        @chmod($dbPath, 0666);
    }

    // Garante as tabelas usadas pelo framework mesmo se o arquivo
    // tiver sido criado vazio (sem o snapshot).
    try {
        $pdo = new PDO('sqlite:'.$dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE TABLE IF NOT EXISTS cache (key VARCHAR NOT NULL PRIMARY KEY, value MEDIUMTEXT NOT NULL, expiration INTEGER NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cache_locks (key VARCHAR NOT NULL PRIMARY KEY, owner VARCHAR NOT NULL, expiration INTEGER NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (id VARCHAR NOT NULL PRIMARY KEY, user_id BIGINT, ip_address VARCHAR(45), user_agent TEXT, payload LONGTEXT NOT NULL, last_activity INTEGER NOT NULL)");
        $pdo->exec('CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue VARCHAR NOT NULL, payload LONGTEXT NOT NULL, attempts TINYINT NOT NULL, reserved_at INTEGER, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)");
        $pdo->exec('CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS failed_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid VARCHAR NOT NULL UNIQUE, connection TEXT NOT NULL, queue TEXT NOT NULL, payload LONGTEXT NOT NULL, exception LONGTEXT NOT NULL, failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    } catch (Throwable $e) {
        // Deixa o Laravel reportar o erro original com contexto.
    }
}
