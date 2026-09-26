<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regressão do 302 no login em produção: vars definidas porém VAZIAS ("")
 * no Dashboard da Vercel faziam os defaults de Manager resolverem para "",
 * e Manager::createDriver("") estoura ArgumentCountError (ex.: LOG_CHANNEL=""
 * quebrava no Log::info dentro do authenticate()).
 *
 * Os configs usam `env(...) ?: fallback`, e este teste reexecuta os arquivos
 * de config com envs vazias para travar esse contrato.
 */
class EnvVaziaDefaultsTest extends TestCase
{
    /** @var array<string,string|null> */
    private array $originais = [];

    /** @var string[] */
    private array $chaves = [
        'AUTH_GUARD',
        'AUTH_PASSWORD_BROKER',
        'BCRYPT_ROUNDS',
        'CACHE_STORE',
        'DB_CONNECTION',
        'FILESYSTEM_DISK',
        'HASH_DRIVER',
        'LOG_CHANNEL',
        'MAIL_MAILER',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
        'APP_MAINTENANCE_DRIVER',
        'APP_MAINTENANCE_STORE',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->chaves as $chave) {
            $this->originais[$chave] = getenv($chave) === false ? null : getenv($chave);
            putenv($chave.'=');
            $_ENV[$chave] = '';
            $_SERVER[$chave] = '';
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originais as $chave => $valor) {
            if ($valor === null) {
                putenv($chave);
                unset($_ENV[$chave], $_SERVER[$chave]);
            } else {
                putenv($chave.'='.$valor);
                $_ENV[$chave] = $valor;
                $_SERVER[$chave] = $valor;
            }
        }

        parent::tearDown();
    }

    public function test_defaults_de_manager_nunca_resolvem_para_vazio(): void
    {
        $auth = require config_path('auth.php');
        $this->assertSame('web', $auth['defaults']['guard']);
        $this->assertSame('users', $auth['defaults']['passwords']);

        $hashing = require config_path('hashing.php');
        $this->assertSame('bcrypt', $hashing['default']);
        $this->assertSame(10, $hashing['drivers']['bcrypt']['rounds']);

        $cache = require config_path('cache.php');
        $this->assertNotSame('', $cache['default']);

        $session = require config_path('session.php');
        $this->assertNotSame('', $session['driver']);

        $queue = require config_path('queue.php');
        $this->assertNotSame('', $queue['default']);

        $logging = require config_path('logging.php');
        $this->assertNotSame('', $logging['default']);

        $mail = require config_path('mail.php');
        $this->assertNotSame('', $mail['default']);

        $filesystems = require config_path('filesystems.php');
        $this->assertNotSame('', $filesystems['default']);

        $database = require config_path('database.php');
        $this->assertNotSame('', $database['default']);

        $app = require config_path('app.php');
        $this->assertNotSame('', $app['maintenance']['driver']);
        $this->assertNotSame('', $app['maintenance']['store']);
    }
}
