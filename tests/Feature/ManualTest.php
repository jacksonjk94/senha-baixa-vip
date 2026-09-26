<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_exige_login(): void
    {
        $this->get(route('admin.manual'))->assertRedirect(route('login'));
    }

    public function test_manual_bloqueia_visitante(): void
    {
        $visitante = User::factory()->create(['role' => 'user']);

        $this->actingAs($visitante)
            ->get(route('admin.manual'))
            ->assertForbidden();
    }

    public function test_manual_abre_para_admin_com_conteudo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $resposta = $this->actingAs($admin)->get(route('admin.manual'));

        $resposta->assertOk();
        $resposta->assertSee('Manual do sistema');
        $resposta->assertSee('Fluxo do visitante');
        $resposta->assertSee('Papéis e permissões');
        $resposta->assertSee('Disparo automático');
        $resposta->assertSee('Solução de problemas');
    }
}
