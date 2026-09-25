<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLogoutRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) \Inertia\Inertia::getVersion(),
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    public function test_login_via_inertia_forca_recarregamento_total_para_o_admin(): void
    {
        User::factory()->create([
            'email' => 'regressao@login.com',
            'role' => 'admin',
        ]);

        $resposta = $this->withHeaders($this->inertiaHeaders())->post('/login', [
            'email' => 'regressao@login.com',
            'password' => 'password',
        ]);

        // 409 + X-Inertia-Location: o cliente recarrega a página (destino
        // Blade). Sem isso, o POST terminava em HTML e abria o modal de erro.
        $resposta->assertStatus(409);
        $this->assertStringEndsWith('/admin', (string) $resposta->headers->get('X-Inertia-Location'));
        $this->assertAuthenticated();
    }

    public function test_logout_via_inertia_forca_recarregamento_total_para_o_acesso(): void
    {
        $usuario = User::factory()->create(['role' => 'admin']);

        $resposta = $this->actingAs($usuario)
            ->withHeaders($this->inertiaHeaders())
            ->post('/logout');

        $resposta->assertStatus(409);
        $this->assertStringEndsWith('/acesso', (string) $resposta->headers->get('X-Inertia-Location'));
        $this->assertGuest();
    }

    public function test_logout_via_form_html_redireciona_para_o_acesso(): void
    {
        $usuario = User::factory()->create(['role' => 'admin']);

        // Sem cabeçalho X-Inertia (ex: menu de perfil da sidebar Blade).
        $resposta = $this->actingAs($usuario)->post('/logout');

        $resposta->assertRedirect(route('agendafacil.acesso'));
        $this->assertGuest();
    }
}
