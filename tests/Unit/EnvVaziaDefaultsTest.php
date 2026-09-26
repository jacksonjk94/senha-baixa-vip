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
        $this->assertSame('bcrypt', $hashing['driver']);
        $this->assertSame(12, $hashing['bcrypt']['rounds']);

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

    /**
     * Regressão do 302 no login: no Laravel 12 o bootstrap faz
     * array_merge do config base do framework com o da aplicação, e o
     * HashManager lê `hashing.driver`. Com HASH_DRIVER="" no Dashboard, a
     * chave do framework resolvia para "" — só a chave `driver` do app
     * (com fallback) salva o merge.
     */
    public function test_merge_do_hashing_nunca_resolve_driver_vazio(): void
    {
        $this->setEnvVazia(['HASH_DRIVER', 'BCRYPT_ROUNDS']);

        $base = require base_path('vendor/laravel/framework/config/hashing.php');
        $app = require config_path('hashing.php');

        // Prova que o framework sozinho quebra (é o que acontecia em prod).
        $this->assertSame('', $base['driver']);

        $merged = array_merge($base, $app);

        $this->assertSame('bcrypt', $merged['driver']);
        $this->assertSame(12, $merged['bcrypt']['rounds']);
    }

    /** @param string[] $chaves */
    private function setEnvVazia(array $chaves): void
    {
        foreach ($chaves as $chave) {
            if (! array_key_exists($chave, $this->originais)) {
                $this->originais[$chave] = getenv($chave) === false ? null : getenv($chave);
            }
            putenv($chave.'=');
            $_ENV[$chave] = '';
            $_SERVER[$chave] = '';
        }
    }
}
