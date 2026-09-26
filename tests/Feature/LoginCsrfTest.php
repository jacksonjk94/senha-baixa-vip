<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCsrfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simula o cliente corrigido: lê o cookie XSRF-TOKEN da tela de login
     * e o reenvia no header X-XSRF-TOKEN — o contrato que o interceptor
     * do bootstrap.js garante no navegador.
     */
    public function test_login_com_header_xsrf_funciona(): void
    {
        $usuario = User::factory()->create(['role' => 'admin']);

        $tela = $this->get('/login');
        $tela->assertOk();

        $xsrf = null;

        foreach ($tela->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $xsrf = $cookie->getValue();
            }
        }

        $this->assertNotEmpty($xsrf, 'Resposta do login deve definir o cookie XSRF-TOKEN');

        $resposta = $this->withHeaders(['X-XSRF-TOKEN' => $xsrf])
            ->post('/login', [
                'email' => $usuario->email,
                'password' => 'password',
            ]);

        $resposta->assertRedirect(route('admin.inicio'));
        $this->assertAuthenticatedAs($usuario);
    }

    /**
     * Regressão do 419 em produção: os cookies de sessão/XSRF precisam
     * sair com validade futura. Com lifetime zerado eles saíam com
     * Max-Age=0 (descartados na hora) e cada request criava sessão nova.
     */
    public function test_cookies_de_sessao_saem_com_validade_futura(): void
    {
        $resposta = $this->get('/login');
        $resposta->assertOk();

        $cookies = [];

        foreach ($resposta->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }

        $this->assertArrayHasKey(config('session.cookie'), $cookies);
        $this->assertArrayHasKey('XSRF-TOKEN', $cookies);

        foreach (['XSRF-TOKEN', config('session.cookie')] as $nome) {
            $this->assertGreaterThan(
                time() + 3600,
                $cookies[$nome]->getExpiresTime(),
                "Cookie {$nome} deve expirar no futuro"
            );
        }
    }
}
