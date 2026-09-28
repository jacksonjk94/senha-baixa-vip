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
            'texto' => 'Roberta lallison',
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
        $resposta->assertSee('Roberta lallison');
        // Data/hora sempre partem da sugestão (hoje + 30min arredondado),
        // nunca do agendamento anterior — dias anteriores bloqueados via min.
        $esperado = now()->addMinutes(30)->startOfMinute();
        $resto = ((int) $esperado->format('i')) % 10;
        if ($resto !== 0) {
            $esperado->addMinutes(10 - $resto);
        }
        $resposta->assertSee('value="'.$esperado->format('Y-m-d').'"', false);
        $resposta->assertSee('value="'.$esperado->format('H:i').'"', false);
        $resposta->assertSee('min="'.now()->format('Y-m-d').'"', false);
        $resposta->assertSee('Aberto SEM OK');
        $resposta->assertSee('13981388690');
    }

    public function test_salvar_confirmacao_mostra_resumo_e_confirmar_avanca_para_codigo(): void
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

        $quando = now()->addDay()->setTime(21, 0);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupoNovo->id,
                'mensagem' => 'Jackson Dos Santos',
                'data_envio' => $quando->toDateString(),
                'hora_envio' => $quando->format('H:i'),
            ]);

        $resposta->assertRedirect(route('agendafacil.resumo'));

        $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->get(route('agendafacil.resumo'))
            ->assertOk()
            ->assertSee('Confirme seu agendamento')
            ->assertSee('Jackson Dos Santos')
            ->assertSee('1ª de 1');

        $confirmacao = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'));

        $confirmacao->assertRedirect(route('agendafacil.codigo'));
        // Data diferente = novo agendamento (não remarca o anterior).
        $novoId = session('agendafacil.agendamento_id');
        $this->assertNotEquals($agendamento->id, $novoId);
        $this->assertDatabaseHas('agendamentos', [
            'id' => $novoId,
            'grupo_telegram_id' => $grupoNovo->id,
            'texto' => 'Jackson Dos Santos',
            'destino_id' => $grupoNovo->chat_id,
            'data_hora_envio' => $quando->format('Y-m-d H:i:s'),
        ]);
        // O anterior (outro dia) permanece intacto.
        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'grupo_telegram_id' => $grupoAtual->id,
        ]);
    }

    public function test_mesmo_dia_atualiza_horario_em_vez_de_criar_novo(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '1235']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Dia',
            'chat_id' => '-100000011',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Antigo',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addHours(2)->startOfMinute(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $mesmoDia = now()->addHours(3)->startOfMinute();
        // Garante mesmo dia (evita virada de dia no teste).
        if ($mesmoDia->toDateString() !== now()->toDateString()) {
            $mesmoDia = now()->addMinutes(40)->startOfMinute();
        }

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Novo Horario',
                'data_envio' => $mesmoDia->toDateString(),
                'hora_envio' => $mesmoDia->format('H:i'),
            ])
            ->assertRedirect(route('agendafacil.resumo'));

        $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'))
            ->assertRedirect(route('agendafacil.codigo'));

        $this->assertSame($agendamento->id, session('agendafacil.agendamento_id'));
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'texto' => 'Novo Horario',
            'data_hora_envio' => $mesmoDia->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_agendamento_enviado_nunca_e_reaproveitado(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '1236']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Enviado',
            'chat_id' => '-100000012',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        $enviado = Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Ja foi',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->subHour(),
            'status' => Agendamento::STATUS_ENVIADO,
        ]);

        $quando = now()->addHour()->startOfMinute();

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Novo Pedido',
                'data_envio' => $quando->toDateString(),
                'hora_envio' => $quando->format('H:i'),
            ])
            ->assertRedirect(route('agendafacil.resumo'));

        $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'))
            ->assertRedirect(route('agendafacil.codigo'));

        $novoId = session('agendafacil.agendamento_id');
        $this->assertNotEquals($enviado->id, $novoId);
        $this->assertSame(Agendamento::STATUS_ENVIADO, $enviado->refresh()->status);
        $this->assertDatabaseHas('agendamentos', [
            'id' => $novoId,
            'texto' => 'Novo Pedido',
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
    }

    public function test_salvar_confirmacao_rejeita_horario_no_passado(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '4333']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste',
            'chat_id' => '-100000004',
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

        $passado = now()->subHour();

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Jackson Dos Santos x40',
                'data_envio' => $passado->toDateString(),
                'hora_envio' => $passado->format('H:i'),
            ]);

        $resposta->assertSessionHasErrors('data_envio');
    }

    public function test_salvar_confirmacao_cria_agendamento_quando_ainda_nao_existe(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Jackson Dos Santos',
            'ultimos_digitos_telefone' => '4344',
        ]);
        $grupo = GrupoTelegram::create([
            'nome' => 'Senha normal P1 e Ala',
            'chat_id' => '-100777888',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);
        $quando = now()->addDay()->setTime(21, 0);

        $this->assertDatabaseCount('agendamentos', 0);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Jackson Dos Santos',
                'data_envio' => $quando->toDateString(),
                'hora_envio' => $quando->format('H:i'),
            ]);

        $resposta->assertRedirect(route('agendafacil.resumo'));

        $confirmacao = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'));

        $confirmacao->assertRedirect(route('agendafacil.codigo'));
        $this->assertDatabaseHas('agendamentos', [
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Jackson Dos Santos',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => $quando->format('Y-m-d H:i:s'),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
    }

    public function test_salvar_confirmacao_salva_mensagem_como_digitada(): void
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
                'data_envio' => now()->addDay()->toDateString(),
                'hora_envio' => '21:00',
            ]);

        $resposta->assertRedirect(route('agendafacil.resumo'));
        $this->assertSame('Jackson Dos Santos', session('agendafacil.rascunho.mensagem'));

        $confirmacao = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'));

        $confirmacao->assertRedirect(route('agendafacil.codigo'));
        // A mensagem é salva exatamente como digitada, sem numeração.
        $this->assertDatabaseHas('agendamentos', [
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Jackson Dos Santos',
        ]);
    }

    public function test_salvar_confirmacao_remove_sufixo_legado_xn(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '4323']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Sufixo',
            'chat_id' => '-100000004',
            'tipo' => 'supergroup',
            'ativo' => true,
        ]);

        $resposta = $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Jackson Dos Santos x99',
                'data_envio' => now()->addDay()->toDateString(),
                'hora_envio' => '21:00',
            ]);

        $resposta->assertRedirect(route('agendafacil.resumo'));
        $this->assertSame('Jackson Dos Santos', session('agendafacil.rascunho.mensagem'));
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

    public function test_confirmar_dados_preenche_mensagem_com_nome(): void
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
        $resposta->assertSee('Jackson Dos Santos', false);
        $resposta->assertDontSee('Padrão: nome + senha xNÚMERO');
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

    public function test_resumo_exige_rascunho(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '8881']);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->get(route('agendafacil.resumo'))
            ->assertRedirect(route('agendafacil.confirmar-dados'));

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.resumo.confirmar'))
            ->assertRedirect(route('agendafacil.confirmar-dados'));
    }

    public function test_confirmar_dados_sem_grupos_exibe_mensagem_amigavel(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '8882']);

        $this->assertDatabaseCount('grupo_telegrams', 0);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->get(route('agendafacil.confirmar-dados'))
            ->assertOk()
            ->assertSee('Os grupos ainda estão sendo configurados.');
    }

    public function test_confirmar_dados_exibe_etiqueta_aberto_fechado(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '8883']);
        GrupoTelegram::create([
            'nome' => 'Grupo Livre', 'chat_id' => '-100900001', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100900002', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->get(route('agendafacil.confirmar-dados'))
            ->assertOk()
            ->assertSee('Grupo Livre [Aberto]')
            ->assertSee('Grupo Rodada [Fechado]');
    }

    public function test_mensagem_maior_que_200_e_rejeitada(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '8884']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste', 'chat_id' => '-100900003', 'tipo' => 'supergroup', 'ativo' => true,
        ]);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => str_repeat('a', 201),
                'data_envio' => now()->addDay()->toDateString(),
                'hora_envio' => '21:00',
            ])
            ->assertSessionHasErrors('mensagem');
    }

    public function test_grupo_fechado_fixa_horario_no_disparo(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Visitante Fechado', 'ultimos_digitos_telefone' => '8885',
        ]);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100900004', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_abertura' => '00:00',
            'horario_fechamento' => '23:59', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Visitante Fechado',
                'data_envio' => now()->toDateString(),
                'hora_envio' => '10:00',
            ])
            ->assertRedirect(route('agendafacil.resumo'));

        $confirmacao = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'));

        $confirmacao->assertRedirect(route('agendafacil.codigo'));
        $this->assertDatabaseHas('agendamentos', [
            'user_id' => $usuario->id,
            'texto' => 'Visitante Fechado',
            'data_hora_envio' => now()->format('Y-m-d') . ' 19:58:00',
        ]);
    }

    public function test_grupo_fechado_antes_da_abertura_bloqueia(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '8886']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100900005', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_abertura' => '23:59',
            'horario_fechamento' => '23:59', 'horario_disparo' => '23:59', 'ativo' => true,
        ]);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Visitante x1',
                'data_envio' => now()->toDateString(),
                'hora_envio' => '23:59',
            ])
            ->assertSessionHasErrors('grupo_id');
    }

    public function test_grupo_fechado_apos_fechamento_vai_para_proxima_rodada(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Visitante Rodada', 'ultimos_digitos_telefone' => '8887',
        ]);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100900006', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_abertura' => '00:00',
            'horario_fechamento' => '00:01', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);

        $this->withSession(['agendafacil.visitante_id' => $usuario->id])
            ->post(route('agendafacil.confirmar-dados.salvar'), [
                'grupo_id' => $grupo->id,
                'mensagem' => 'Visitante Rodada x1',
                'data_envio' => now()->toDateString(),
                'hora_envio' => '19:58',
            ])
            ->assertRedirect(route('agendafacil.resumo'));

        $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->get(route('agendafacil.resumo'))
            ->assertOk()
            ->assertSee('próxima rodada');

        $confirmacao = $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.rascunho' => session('agendafacil.rascunho'),
        ])->post(route('agendafacil.resumo.confirmar'));

        $confirmacao->assertRedirect(route('agendafacil.codigo'));
        $confirmacao->assertSessionHas('aviso');
        $this->assertDatabaseHas('agendamentos', [
            'user_id' => $usuario->id,
            'data_hora_envio' => now()->addDay()->format('Y-m-d') . ' 19:58:00',
        ]);
    }

    public function test_posicao_rodada_conta_mesmo_dia(): void
    {
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100900007', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);
        $dia = now()->addDay();

        $this->assertSame(1, Agendamento::proximaPosicaoRodada($grupo->id, $dia));

        Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Pessoa x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => $dia->copy()->setTime(19, 58),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
        Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Pessoa x2',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => $dia->copy()->setTime(19, 58),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->assertSame(3, Agendamento::proximaPosicaoRodada($grupo->id, $dia));
        $this->assertSame(2, Agendamento::totalRodada($grupo->id, $dia));
    }

    public function test_cancelar_recalcula_posicoes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste', 'chat_id' => '-100900008', 'tipo' => 'supergroup', 'ativo' => true,
        ]);
        $quando = now()->addDay()->setTime(21, 0);

        $primeiro = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id, 'texto' => 'A x1', 'posicao' => 1,
            'total_grupo_horario' => 3, 'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id, 'data_hora_envio' => $quando,
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
        $segundo = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id, 'texto' => 'B x2', 'posicao' => 2,
            'total_grupo_horario' => 3, 'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id, 'data_hora_envio' => $quando->copy()->addSecond(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
        $terceiro = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id, 'texto' => 'C x3', 'posicao' => 3,
            'total_grupo_horario' => 3, 'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id, 'data_hora_envio' => $quando->copy()->addSeconds(2),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.cancelar', $primeiro))
            ->assertRedirect();

        $this->assertSame(1, $segundo->refresh()->posicao);
        $this->assertSame(2, $terceiro->refresh()->posicao);
        $this->assertSame(2, $terceiro->refresh()->total_grupo_horario);
    }

    public function test_admin_cadastra_e_edita_grupo_com_modo_e_horarios(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.grupos.salvar'), [
            'nome' => 'Grupo Rodada',
            'chat_id' => '-100900009',
            'tipo' => 'supergroup',
            'modo' => 'fechado',
            'horario_abertura' => '19:50',
            'horario_fechamento' => '19:55',
            'horario_disparo' => '19:58',
            'dias_semana' => '1,2,3,4,5',
            'ordem' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('grupo_telegrams', [
            'chat_id' => '-100900009',
            'modo' => 'fechado',
        ]);

        $grupo = GrupoTelegram::where('chat_id', '-100900009')->first();

        $this->actingAs($admin)->patch(route('admin.grupos.atualizar', $grupo), [
            'nome' => 'Grupo Rodada Editado',
            'chat_id' => '-100900009',
            'tipo' => 'supergroup',
            'modo' => 'aberto',
            'ordem' => 2,
        ])->assertRedirect();

        $this->assertSame('aberto', $grupo->refresh()->modo);
    }

    public function test_concluido_exibe_posicao_e_envio(): void
    {
        $usuario = User::factory()->create(['ultimos_digitos_telefone' => '8888']);
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Teste', 'chat_id' => '-100900010', 'tipo' => 'supergroup', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'posicao' => 5,
            'total_grupo_horario' => 12,
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now()->addDay()->setTime(19, 58),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withSession([
            'agendafacil.visitante_id' => $usuario->id,
            'agendafacil.agendamento_id' => $agendamento->id,
            'agendafacil.codigo_ok' => true,
            'agendafacil.grupo_ok' => true,
        ])->get(route('agendafacil.concluido'))
            ->assertOk()
            ->assertSee('Grupo confirmado. Pode fechar esta janela.')
            ->assertSee('5ª de 12');
    }
}
