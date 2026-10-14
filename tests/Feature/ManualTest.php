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

    public function test_visao_geral_exibe_foto_do_visitante(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $visitante = User::factory()->create(['role' => 'user', 'name' => 'Luana', 'telegram_id' => '999001']);
        \App\Models\Agendamento::factory()->create([
            'user_id' => $visitante->id,
            'nome_visitante' => 'Luana',
            'texto' => 'Luana',
        ]);

        $resposta = $this->actingAs($admin)->get(route('admin.inicio'));

        $resposta->assertOk();
        $resposta->assertSee('af-avatar-sm', false);
        $resposta->assertSee('/admin/telegram/foto/visitante/' . $visitante->uuid, false);
    }

    public function test_foto_do_visitante_exige_telegram_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $semTelegram = User::factory()->create(['role' => 'user', 'telegram_id' => null]);

        $this->actingAs($admin)
            ->get(route('admin.telegram.foto.visitante', $semTelegram))
            ->assertNotFound();
    }

    public function test_manual_abre_para_admin_com_conteudo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $resposta = $this->actingAs($admin)->get(route('admin.manual'));

        $resposta->assertOk();
        $resposta->assertSee('Manual do sistema');
        $resposta->assertSee(config('app.name'), false);
        $resposta->assertSee('Fluxo do visitante');
        $resposta->assertSee('Papéis e permissões');
        $resposta->assertSee('Disparo automático');
        $resposta->assertSee('Solução de problemas');
    }
}
