<?php

namespace Tests\Feature;

use App\Jobs\EnviarSenhaTelegram;
use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MtprotoDisparoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.token' => 'token-teste']);
        config(['services.telegram_mtproto.api_id' => '123']);
        config(['services.telegram_mtproto.api_hash' => 'hash-teste']);
        config(['services.telegram_mtproto.url' => 'http://mtproto.test']);
        config(['services.telegram_mtproto.service_token' => 'token-interno']);
    }

    protected function agendamentoPara(User $usuario, GrupoTelegram $grupo): Agendamento
    {
        return Agendamento::factory()->create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
    }

    protected function grupoDe(User $usuario, string $chatId = '-5552808073'): GrupoTelegram
    {
        return GrupoTelegram::create([
            'nome' => 'Amigas VIP',
            'chat_id' => $chatId,
            'tipo' => 'group',
            'modo' => 'aberto',
            'ativo' => true,
            'usuario_id' => $usuario->id,
        ]);
    }

    public function test_envia_via_mtproto_quando_ha_sessao_guardada(): void
    {
        Http::fake([
            'http://mtproto.test/*' => function (\Illuminate\Http\Client\Request $request) {
                if (str_contains($request->url(), '/v1/messages/history')) {
                    return Http::response(['ok' => true, 'mensagens' => [
                        ['id' => '103', 'texto' => 'manual nova', 'data' => 300, 'saida' => false],
                        ['id' => '42', 'texto' => 'Visitante x1', 'data' => 200, 'saida' => true],
                        ['id' => '101', 'texto' => 'manual antiga', 'data' => 100, 'saida' => false],
                    ]], 200);
                }

                return Http::response(['ok' => true, 'messageId' => 42], 200);
            },
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);

        EnviarSenhaTelegram::dispatchSync($agendamento->id);

        $agendamento->refresh();

        $this->assertSame(Agendamento::STATUS_ENVIADO, $agendamento->status);
        $this->assertSame('42', $agendamento->telegram_message_id);
        $this->assertSame(2, (int) $agendamento->posicao_grupo);
        $this->assertSame(250, (int) $agendamento->latencia_ms);
        $this->assertCount(3, $agendamento->contexto_grupo['mensagens'] ?? []);

        Http::assertSent(function ($request) use ($grupo) {
            return str_contains($request->url(), '/v1/messages/send')
                && $request['session'] === 'sessao-valida-123'
                && $request['chatId'] === $grupo->chat_id;
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }

    public function test_recorre_ao_bot_quando_nao_ha_sessao(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $usuario = User::factory()->create();
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);

        EnviarSenhaTelegram::dispatchSync($agendamento->id);

        $this->assertSame(Agendamento::STATUS_ENVIADO, $agendamento->refresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }

    public function test_sessao_revogada_limpa_e_recai_no_bot(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response(['message' => 'Sessão expirada.', 'sessionInvalid' => true], 401),
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-revogada', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);

        EnviarSenhaTelegram::dispatchSync($agendamento->id);

        $this->assertSame(Agendamento::STATUS_ENVIADO, $agendamento->refresh()->status);
        $this->assertNull($usuario->fresh()->telegram_mtproto_session);
    }

    public function test_fluxo_codigo_guarda_sessao_em_usuario_existente(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response([
                'user' => ['id' => '777001', 'username' => 'roberta', 'firstName' => 'Roberta'],
                'groups' => [['chat_id' => '-5552808073', 'nome' => 'amigas vip', 'tipo' => 'group']],
                'session' => 'sessao-do-fluxo',
            ], 200),
        ]);

        $usuario = User::factory()->create(['telefone' => '+5511999999999', 'telegram_id' => '777001']);

        $this->withSession([
            'agendafacil.telefone' => '+5511999999999',
            'agendafacil.mtproto_operation_id' => 'op-1',
        ])->post(route('agendafacil.codigo.verificar'), ['codigo' => '12345'])
            ->assertRedirect(route('agendafacil.horario'));

        $this->assertTrue($usuario->fresh()->mtprotoSessaoValida());
        $this->assertNull(session('agendafacil.mtproto_session'));
    }

    public function test_fluxo_codigo_stash_sessao_quando_ainda_sem_cadastro(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response([
                'user' => ['id' => '777002', 'username' => 'novo', 'firstName' => 'Novo'],
                'groups' => [],
                'session' => 'sessao-pendente',
            ], 200),
        ]);

        $this->withSession([
            'agendafacil.telefone' => '+5511888888888',
            'agendafacil.mtproto_operation_id' => 'op-2',
        ])->post(route('agendafacil.codigo.verificar'), ['codigo' => '54321'])
            ->assertRedirect(route('agendafacil.horario'));

        $this->assertSame('sessao-pendente', session('agendafacil.mtproto_session'));
    }

    public function test_erro_real_do_mtproto_nao_mascara_com_bot(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response(['message' => 'A conta não tem permissão para escrever neste grupo.'], 403),
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);

        EnviarSenhaTelegram::dispatchSync($agendamento->id);

        $agendamento->refresh();

        $this->assertSame(Agendamento::STATUS_ERRO, $agendamento->status);
        $this->assertStringContainsString('permissão', $agendamento->mensagem_erro);
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }

    public function test_mtproto_fora_do_ar_recai_no_bot_mantendo_sessao(): void
    {
        // Porta discard: conexão recusada de verdade (sem fake que lance
        // exceção — mais fiel ao serviço fora do ar).
        config(['services.telegram_mtproto.url' => 'http://127.0.0.1:9']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);

        EnviarSenhaTelegram::dispatchSync($agendamento->id);

        $this->assertSame(Agendamento::STATUS_ENVIADO, $agendamento->refresh()->status);
        $this->assertTrue($usuario->fresh()->mtprotoSessaoValida());
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_contexto_ao_vivo_mostra_posicao(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response(['ok' => true, 'mensagens' => [
                ['id' => '103', 'texto' => 'manual nova', 'data' => 300, 'saida' => false],
                ['id' => '42', 'texto' => 'Visitante x1', 'data' => 200, 'saida' => true],
            ]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);
        $agendamento->forceFill([
            'status' => Agendamento::STATUS_ENVIADO,
            'telegram_message_id' => '42',
        ])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.disparos.contexto', $agendamento))
            ->assertOk()
            ->assertJsonPath('ao_vivo', true)
            ->assertJsonPath('posicao', 2)
            ->assertJsonPath('posicao_fixa', false)
            ->assertJsonPath('total', 2);

        // A página lida fica gravada no DB (mesclada ao retrato).
        $contexto = $agendamento->refresh()->contexto_grupo;
        $this->assertCount(2, $contexto['mensagens'] ?? []);
        $this->assertSame(2, (int) ($contexto['posicao'] ?? 0));
        $this->assertNotEmpty($contexto['atualizado_em'] ?? null);
    }

    public function test_contexto_mescla_paginas_sem_duplicar(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response(['ok' => true, 'mensagens' => [
                ['id' => '40', 'texto' => 'mais antiga', 'data' => 100, 'saida' => false],
                ['id' => '39', 'texto' => 'anterior', 'data' => 90, 'saida' => false],
            ]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);
        $agendamento->forceFill([
            'status' => Agendamento::STATUS_ENVIADO,
            'telegram_message_id' => '42',
            'posicao_grupo' => 2,
            'contexto_grupo' => [
                'capturado_em' => '2026-10-14 12:00:00',
                'posicao' => 2,
                'mensagens' => [
                    ['id' => '42', 'texto' => 'Visitante x1', 'data' => 200, 'saida' => true],
                    ['id' => '41', 'texto' => 'outra', 'data' => 150, 'saida' => false],
                    ['id' => '40', 'texto' => 'mais antiga', 'data' => 100, 'saida' => false],
                ],
            ],
        ])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.disparos.contexto', $agendamento) . '?limit=2&offset_id=42&lidas=2')
            ->assertOk();

        $contexto = $agendamento->refresh()->contexto_grupo;

        // 40 já existia (sem dupe), 39 entrou; posição preservada.
        $this->assertCount(4, $contexto['mensagens'] ?? []);
        $this->assertSame(2, (int) ($contexto['posicao'] ?? 0));
    }

    public function test_contexto_usa_retrato_quando_sem_sessao(): void
    {
        $usuario = User::factory()->create();
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);
        $agendamento->forceFill([
            'status' => Agendamento::STATUS_ENVIADO,
            'telegram_message_id' => '42',
            'posicao_grupo' => 1,
            'contexto_grupo' => [
                'capturado_em' => '2026-10-14 12:00:00',
                'posicao' => 1,
                'mensagens' => [
                    ['id' => '42', 'texto' => 'Visitante x1', 'data' => 200, 'saida' => true],
                ],
            ],
        ])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.disparos.contexto', $agendamento))
            ->assertOk()
            ->assertJsonPath('ao_vivo', false)
            ->assertJsonPath('posicao', 1)
            ->assertJsonPath('posicao_fixa', true);
    }

    public function test_contexto_mostra_posicao_gravada_quando_fora_da_janela(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response(['ok' => true, 'mensagens' => [
                ['id' => '103', 'texto' => 'manual nova', 'data' => 300, 'saida' => false],
                ['id' => '101', 'texto' => 'manual antiga', 'data' => 100, 'saida' => false],
            ]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);
        $agendamento->forceFill([
            'status' => Agendamento::STATUS_ENVIADO,
            'telegram_message_id' => '42',
            'posicao_grupo' => 5,
        ])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.disparos.contexto', $agendamento))
            ->assertOk()
            ->assertJsonPath('ao_vivo', true)
            ->assertJsonPath('posicao', 5)
            ->assertJsonPath('posicao_fixa', true);
    }

    public function test_contexto_404_sem_nada_capturado(): void
    {
        $usuario = User::factory()->create();
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);
        $agendamento->forceFill(['status' => Agendamento::STATUS_ENVIADO])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.disparos.contexto', $agendamento))
            ->assertNotFound();
    }

    public function test_contexto_pagina_2_com_posicao_global(): void
    {
        Http::fake([
            'http://mtproto.test/*' => Http::response(['ok' => true, 'mensagens' => [
                ['id' => '41', 'texto' => 'outra', 'data' => 150, 'saida' => false],
                ['id' => '42', 'texto' => 'Visitante x1', 'data' => 140, 'saida' => true],
            ]], 200),
        ]);

        $usuario = User::factory()->create();
        $usuario->guardarSessaoMtproto('sessao-valida-123', '+5511999999999');
        $grupo = $this->grupoDe($usuario);
        $agendamento = $this->agendamentoPara($usuario, $grupo);
        $agendamento->forceFill([
            'status' => Agendamento::STATUS_ENVIADO,
            'telegram_message_id' => '42',
        ])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.disparos.contexto', $agendamento) . '?limit=2&offset_id=43&lidas=15')
            ->assertOk()
            ->assertJsonPath('ao_vivo', true)
            ->assertJsonPath('posicao', 17)
            ->assertJsonPath('tem_mais', true)
            ->assertJsonPath('next_offset_id', '42');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/messages/history')
                && (int) $request['offsetId'] === 43
                && (int) $request['limit'] === 2;
        });
    }
}
