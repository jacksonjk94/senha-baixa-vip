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
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '0001']);

        $resposta = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.codigo' => '123456',
        ])->post(route('agendafacil.codigo.validar'), ['codigo' => '000000']);

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

    public function test_confirmar_dados_exibe_grupo_mensagem_horario_tipo_telefone(): void
    {
        $usuario = User::factory()->create([
            'telefone' => '13981388690',
            'ultimos_digitos_telefone' => '8690',
        ]);
        $grupo = GrupoTelegram::create([
            'nome' => 'Senha normal P1 e Ala',
            'chat_id' => '-100111222',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'nome_visitante' => 'Roberta lallison',
            'texto' => 'Roberta lallison x8',
            'dados_senha' => 'Aberto SEM OK',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addDay()->setTime(21, 0),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->get(route('agendafacil.confirmar-dados'));

        $resposta->assertOk();
        $resposta->assertSee('Senha normal P1 e Ala');
        $resposta->assertSee('Roberta lallison x8');
        $resposta->assertSee('21:00');
        $resposta->assertSee('Aberto SEM OK');
        $resposta->assertSee('13981388690');
    }

    public function test_salvar_confirmacao_atualiza_grupo_e_mensagem_e_avanca_para_codigo(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '1234']);
        $grupoAtual = GrupoTelegram::create([
            'nome' => 'Grupo Antigo',
            'chat_id' => '-100000001',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        $grupoNovo = GrupoTelegram::create([
            'nome' => 'Senha normal P1 e Ala',
            'chat_id' => '-100000002',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupoAtual->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupoAtual->chat_id,
            'destino_nome' => $grupoAtual->nome,
            'data_hora_envio' => now()->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupoNovo->id,
                'mensagem' => 'Jackson Dos Santos x40',
            ]);

        $resposta->assertRedirect(route('agendafacil.codigo'));
        $this->assertSame($agendamento->id, session('agendafacil.agendamento_id'));
        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'grupo_telegram_id' => $grupoNovo->id,
            'texto' => 'Jackson Dos Santos x40',
            'destino_id' => $grupoNovo->chat_id,
        ]);
    }

    public function test_salvar_confirmacao_rejeita_mensagem_fora_do_padrao_xnumero(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '4322']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste',
            'chat_id' => '-100000003',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Jackson Dos Santos',
            ]);

        $resposta->assertSessionHasErrors('mensagem');
    }

    public function test_cadastro_salva_telefone_com_ddi_55_fixo(): void
    {
        $resposta = $this->post(route('visitante.cadastro.salvar'), [
            'name' => 'Jackson Dos Santos',
            'email' => 'jackson@example.com',
            'telefone' => '(13) 98138-8690',
        ]);

        $resposta->assertRedirect(route('agendafacil.acesso'));
        $this->assertDatabaseHas('users', [
            'email' => 'jackson@example.com',
            'telefone' => '+55 13981388690',
        ]);
    }

    public function test_confirmar_dados_preenche_mensagem_com_proxima_senha_da_sequencia(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Jackson Dos Santos',
            'ultimos_digitos_telefone' => '9988',
        ]);
        $grupo = GrupoTelegram::create([
            'nome' => 'Senha normal P1 e Ala',
            'chat_id' => '-100444555',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Outra Pessoa x8',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
        Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => null,
            'nome_visitante' => 'Jackson Dos Santos',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addHours(2),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->get(route('agendafacil.confirmar-dados'));

        $resposta->assertOk();
        $resposta->assertSee('Jackson Dos Santos x9', false);
        $resposta->assertDontSee('Padrão: nome + senha xNÚMERO');
    }

    public function test_proxima_senha_retorna_sequencia_do_grupo(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '9977']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Sequencia',
            'chat_id' => '-100666777',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Pessoa x39',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->getJson(route('agendafacil.proxima-senha', ['grupo_id' => $grupo->id]));

        $resposta->assertOk();
        $resposta->assertJson(['proxima' => 40]);
    }

    public function test_confirmar_grupo_sem_grupo_id_retorna_erro_sem_500(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '5555']);

        $resposta = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.codigo_ok' => true,
        ])->post(route('grupos.confirmar'), []);

        $resposta->assertSessionHasErrors('grupo_id');
    }

    public function test_confirmar_grupo_valido_conclui_fluxo(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '6666']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste',
            'chat_id' => '-100123456',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $resposta = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.codigo_ok' => true,
        ])->post(route('grupos.confirmar'), [
            'grupo_id' => $grupo->id,
        ]);

        $resposta->assertRedirect(route('agendafacil.concluido'));
        $this->assertSame($grupo->id, session('agendafacil.grupo.grupo_id'));
    }

    public function test_concluido_exibe_texto_do_print(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '7778']);

        $resposta = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.codigo_ok' => true,
            'agendafacil.grupo_ok' => true,
        ])->get(route('agendafacil.concluido'));

        $resposta->assertOk();
        $resposta->assertSee('Grupo confirmado. Pode fechar esta janela.');
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
