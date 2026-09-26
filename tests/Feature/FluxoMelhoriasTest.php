<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Melhorias de UX/formulários: spinner nos botões, e-mail opcional no
 * cadastro, tag da senha no resumo e etapa /codigo só via Telegram.
 */
class FluxoMelhoriasTest extends TestCase
{
    use RefreshDatabase;

    public function test_botoes_tem_spinner_ao_enviar(): void
    {
        $resposta = $this->get(route('agendafacil.acesso'));

        $resposta->assertOk();
        // Script global do layout (spinner + desabilitado no submit).
        $resposta->assertSee('af-processando', false);
        $resposta->assertSee('spinner-border', false);
    }

    public function test_cadastro_aceita_email_em_branco(): void
    {
        $resposta = $this->post(route('visitante.cadastro.salvar'), [
            'name' => 'Visitante Sem Email',
            'email' => '',
            'telefone' => '(11) 98765-4321',
        ]);

        $resposta->assertRedirect(route('agendafacil.acesso'));
        $this->assertDatabaseHas('users', [
            'name' => 'Visitante Sem Email',
            'email' => null,
        ]);
    }

    public function test_cadastro_mostra_email_opcional_e_novo_placeholder(): void
    {
        $resposta = $this->get(route('visitante.cadastro'));

        $resposta->assertOk();
        $resposta->assertSee('(opcional)', false);
        $resposta->assertSee('(11) 98765-4321', false);
        $resposta->assertDontSee('(13) 98138-8690', false);
    }

    public function test_codigo_oculta_fallback_do_grupo(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('agendafacil.codigo'));

        $resposta->assertOk();
        $resposta->assertDontSee('Usar o código enviado no grupo', false);
        $resposta->assertSee('tgConfirmStatus', false);
        $resposta->assertSee('Aguardando confirmação no Telegram', false);
    }
}
