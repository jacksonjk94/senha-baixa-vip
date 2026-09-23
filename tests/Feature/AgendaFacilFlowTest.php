<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaFacilFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_acesso_exige_quatro_digitos(): void
    {
        $resposta = $this->post(route('agendafacil.acesso.validar'), ['digitos' => '12']);

        $resposta->assertSessionHasErrors('digitos');
    }

    public function test_acesso_com_digitos_validos_avanca_para_confirmacao(): void
    {
        $usuario = User::factory()->create([
            'ultimos_digitos_telefone' => '4321',
        ]);

        $resposta = $this->post(route('agendafacil.acesso.validar'), ['digitos' => '4321']);

        $resposta->assertRedirect(route('agendafacil.confirmar-dados'));
        $this->assertEquals($usuario->id, session('agendafacil.visitante_id'));
    }

    public function test_codigo_invalido_e_rejeitado(): void
    {
        $resposta = $this->withSession(['agendafacil.codigo' => '123456'])
            ->post(route('agendafacil.codigo.validar'), ['codigo' => '000000']);

        $resposta->assertSessionHasErrors('codigo');
    }

    public function test_agendamento_pessoa_e_persistido_como_pendente(): void
    {
        $usuario = User::factory()->create([
            'ultimos_digitos_telefone' => '7777',
            'telegram_id' => '999888777',
        ]);

        $quando = now()->addHour()->startOfMinute();

        $resposta = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.confirmacao' => ['nome_visitante' => 'Visitante Teste', 'destino_tipo' => 'pessoa'],
            'agendafacil.grupo' => ['destino_tipo' => 'pessoa', 'destino_nome' => 'Chat individual'],
        ])->post(route('agendamento.salvar'), [
            'nome_visitante' => 'Visitante Teste',
            'destino_tipo' => 'pessoa',
            'data_envio' => $quando->toDateString(),
            'hora_envio' => $quando->format('H:i'),
        ]);

        $agendamento = Agendamento::first();

        $this->assertNotNull($agendamento);
        $this->assertSame(Agendamento::STATUS_PENDENTE, $agendamento->status);
        $this->assertSame('pessoa', $agendamento->destino_tipo);
        $resposta->assertRedirect(route('agendamento.concluido', $agendamento));
    }

    public function test_admin_exige_papel_privilegiado(): void
    {
        $comum = User::factory()->create(['role' => 'user']);

        $this->actingAs($comum)
            ->get(route('admin.inicio'))
            ->assertForbidden();
    }

    public function test_admin_visualiza_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.inicio'))
            ->assertOk();
    }

    public function test_confirmar_grupo_sem_grupo_id_retorna_erro_sem_500(): void
    {
        $resposta = $this->post(route('grupos.confirmar'), ['destino_tipo' => 'grupo']);

        $resposta->assertSessionHasErrors('grupo_id');
    }

    public function test_confirmar_pessoa_sem_grupo_avanca_para_agendamento(): void
    {
        $resposta = $this->post(route('grupos.confirmar'), ['destino_tipo' => 'pessoa']);

        $resposta->assertRedirect(route('agendamento.novo'));
        $this->assertSame('pessoa', session('agendafacil.grupo.destino_tipo'));
    }

    public function test_confirmar_grupo_valido_exibe_confirmacao(): void
    {
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste',
            'chat_id' => '-100123456',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);

        $resposta = $this->post(route('grupos.confirmar'), [
            'destino_tipo' => 'grupo',
            'grupo_id' => $grupo->id,
        ]);

        $resposta->assertOk();
        $this->assertSame($grupo->id, session('agendafacil.grupo.grupo_id'));
    }

    public function test_texto_mensagem_segue_padrao_da_senha(): void
    {
        $agendamento = Agendamento::factory()->make([
            'nome_visitante' => 'Maria',
            'destino_nome' => 'Grupo Central',
            'senha_codigo' => 'AF-12345',
            'data_hora_envio' => now(),
        ]);

        $texto = $agendamento->textoMensagem();

        $this->assertStringContainsString('SENHA AUTORIZADA', $texto);
        $this->assertStringContainsString('Maria', $texto);
        $this->assertStringContainsString('AF-12345', $texto);
    }
}
