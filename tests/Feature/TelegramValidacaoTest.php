<?php

namespace Tests\Feature;

use App\Models\TelegramBot;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Validação da pessoa pelo próprio Telegram (Login Widget) — etapa 1.
 * O robô (token fixo) não é tocado: aqui só se confirma identidade.
 */
class TelegramValidacaoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'TOKEN_TESTE_VALIDACAO_123';

    private array $statusPorGrupo = [];
    private array $respostasMtproto = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v1/auth/send-code')) {
                return Http::response($this->respostasMtproto['send'] ?? [
                    'operationId' => 'op-test',
                    'isCodeViaApp' => true,
                ]);
            }

            if (str_contains($request->url(), '/v1/auth/verify-code')) {
                return Http::response($this->respostasMtproto['verify'] ?? [
                    'user' => ['id' => '123456789', 'username' => 'maria_teste', 'firstName' => 'Maria'],
                    'groups' => [],
                ]);
            }

            if (str_contains($request->url(), '/getChatMember')) {
                return Http::response([
                    'ok' => true,
                    'result' => ['status' => $this->statusPorGrupo[(string) $request['chat_id']] ?? 'left'],
                ]);
            }

            return Http::response(['ok' => true, 'result' => []]);
        });
    }

    private function configurarMtproto(): void
    {
        config([
            'services.telegram_mtproto.api_id' => '123456',
            'services.telegram_mtproto.api_hash' => 'hash-de-teste',
            'services.telegram_mtproto.url' => 'http://mtproto.test',
            'services.telegram_mtproto.service_token' => 'token-interno-teste',
        ]);
    }

    private function botPadrao(): TelegramBot
    {
        return TelegramBot::create([
            'name' => 'Bot Teste',
            'username' => 'BotTeste_bot',
            'token' => self::TOKEN,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /** Monta um payload do widget com hash oficial válido. */
    private function payloadValido(array $extra = []): array
    {
        $dados = array_merge([
            'id' => '123456789',
            'first_name' => 'Maria',
            'username' => 'maria_teste',
            'auth_date' => (string) time(),
        ], $extra);

        $ordenados = $dados;
        ksort($ordenados);
        $linhas = [];
        foreach ($ordenados as $chave => $valor) {
            $linhas[] = $chave.'='.$valor;
        }

        $dados['hash'] = hash_hmac('sha256', implode("\n", $linhas), hash('sha256', self::TOKEN, true));

        return $dados;
    }

    public function test_acesso_usa_4_digitos_sem_widget(): void
    {
        $this->botPadrao();

        $resposta = $this->get(route('agendafacil.acesso'));

        $resposta->assertOk();
        $resposta->assertDontSee('telegram-widget.js', false);
        $resposta->assertSee('4 últimos dígitos');
    }

    public function test_codigo_exibe_widget_de_confirmacao(): void
    {
        $this->botPadrao();
        $usuario = User::factory()->create();

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('agendafacil.codigo'));

        $resposta->assertOk();
        $resposta->assertSee('telegram-widget.js', false);
        $resposta->assertSee('data-telegram-login="BotTeste_bot"', false);
    }

    public function test_codigo_informa_etapa_mtproto_quando_configurada(): void
    {
        $this->botPadrao();
        $this->configurarMtproto();
        $usuario = User::factory()->create();

        $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('agendafacil.codigo'))
            ->assertOk()
            ->assertSee('autorizar a consulta dos grupos da sua conta');
    }

    public function test_pagina_teste_exibe_login_widget(): void
    {
        $this->botPadrao();

        $this->get(route('telegram.grupos-teste'))
            ->assertOk()
            ->assertSee('data-telegram-login="BotTeste_bot"', false)
            ->assertSee(route('telegram.grupos-teste.callback'), false);
    }

    public function test_teste_telegram_lista_grupos_da_conta_validada(): void
    {
        $this->botPadrao();
        $usuario = User::factory()->create([
            'telegram_id' => '123456789',
            'telegram_username' => 'maria_teste',
        ]);
        $outroUsuario = User::factory()->create(['telegram_id' => '987654321']);

        \App\Models\GrupoTelegram::create([
            'nome' => 'Grupo da Maria', 'chat_id' => '-100501001', 'tipo' => 'supergroup',
            'usuario_id' => $usuario->id, 'ativo' => true,
        ]);
        \App\Models\GrupoTelegram::create([
            'nome' => 'Grupo de Outra Conta', 'chat_id' => '-100501002', 'tipo' => 'supergroup',
            'usuario_id' => $outroUsuario->id, 'ativo' => true,
        ]);

        $this->get(route('telegram.grupos-teste'));

        $this->get(route('telegram.grupos-teste.callback', $this->payloadValido()))
            ->assertOk()
            ->assertSee('Conta validada')
            ->assertSee('Grupo da Maria')
            ->assertDontSee('Grupo de Outra Conta');
    }

    public function test_login_descobre_e_associa_grupo_conhecido_pelo_bot(): void
    {
        $this->botPadrao();
        $visitante = User::factory()->create([
            'telegram_id' => '123456789',
            'telegram_username' => 'maria_teste',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        \App\Models\GrupoTelegram::create([
            'nome' => 'Grupo já conhecido',
            'chat_id' => '-100501003',
            'tipo' => 'supergroup',
            'usuario_id' => $admin->id,
            'ativo' => true,
        ]);

        $this->statusPorGrupo['-100501003'] = 'member';

        $this->withSession(['agendafacil' => [
            'visitante_id' => $visitante->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $this->payloadValido()))
            ->assertRedirect(route('agendafacil.confirmar-dados'));

        Http::assertSent(fn ($request, $response) => str_contains($request->url(), '/getChatMember')
            && (string) $request['user_id'] === '123456789'
            && $response?->json('result.status') === 'member');

        $this->assertDatabaseHas('grupo_telegrams', [
            'nome' => 'Grupo já conhecido',
            'chat_id' => '-100501003',
            'usuario_id' => $visitante->id,
            'ativo' => true,
        ]);
    }

    public function test_login_mtproto_com_consentimento_lista_grupos_e_libera_fluxo(): void
    {
        $this->botPadrao();
        $this->configurarMtproto();
        $visitante = User::factory()->create([
            'telegram_id' => '123456789',
            'telegram_username' => 'maria_teste',
        ]);
        GrupoTelegram::create([
            'nome' => 'Canal antigo',
            'chat_id' => '-100700009',
            'tipo' => 'channel',
            'usuario_id' => $visitante->id,
            'ativo' => true,
        ]);
        GrupoTelegram::create([
            'nome' => 'Grupo antigo',
            'chat_id' => '-100700008',
            'tipo' => 'supergroup',
            'usuario_id' => $visitante->id,
            'ativo' => true,
        ]);
        $this->respostasMtproto['verify'] = [
            'user' => ['id' => '123456789', 'username' => 'maria_teste', 'firstName' => 'Maria'],
            'groups' => [
                ['chat_id' => '-100700001', 'nome' => 'Grupo da conta', 'tipo' => 'group'],
                ['chat_id' => '-100700002', 'nome' => 'Supergrupo da conta', 'tipo' => 'supergroup'],
                ['chat_id' => '-100700003', 'nome' => 'Canal não listado', 'tipo' => 'channel'],
            ],
        ];

        $this->withSession(['agendafacil' => [
            'visitante_id' => $visitante->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $this->payloadValido()))
            ->assertRedirect(route('telegram.mtproto.autorizacao'));

        $this->get(route('telegram.mtproto.autorizacao'))
            ->assertOk()
            ->assertSee('Autorizo o ' . config('app.name'));

        $this->post(route('telegram.mtproto.enviar-codigo'), [
            'telefone' => '+5511999999999',
            'consentimento' => '1',
        ])->assertRedirect(route('telegram.mtproto.autorizacao'));

        $this->post(route('telegram.mtproto.verificar'), ['codigo' => '12345'])
            ->assertRedirect(route('agendafacil.confirmar-dados'));

        $this->assertTrue((bool) session('agendafacil.telegram_ok'));
        $this->assertDatabaseHas('grupo_telegrams', [
            'chat_id' => '-100700001',
            'usuario_id' => $visitante->id,
            'tipo' => 'group',
            'ativo' => true,
        ]);
        $this->assertDatabaseHas('grupo_telegrams', [
            'chat_id' => '-100700002',
            'usuario_id' => $visitante->id,
            'tipo' => 'supergroup',
        ]);
        $this->assertDatabaseMissing('grupo_telegrams', ['chat_id' => '-100700003']);
        $this->assertDatabaseHas('grupo_telegrams', [
            'chat_id' => '-100700009',
            'usuario_id' => $visitante->id,
            'ativo' => false,
        ]);
        $this->assertDatabaseHas('grupo_telegrams', [
            'chat_id' => '-100700008',
            'usuario_id' => $visitante->id,
            'ativo' => false,
        ]);
    }

    public function test_widget_abre_consentimento_mtproto_quando_configurado(): void
    {
        $this->botPadrao();
        $this->configurarMtproto();
        $visitante = User::factory()->create([
            'telegram_id' => '123456789',
            'telegram_username' => 'maria_teste',
        ]);

        $this->withSession(['agendafacil' => [
            'visitante_id' => $visitante->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $this->payloadValido()))
            ->assertRedirect(route('telegram.mtproto.autorizacao'));

        $this->get(route('telegram.mtproto.autorizacao'))
            ->assertOk()
            ->assertSee('Autorizo o ' . config('app.name'));
    }

    public function test_login_mtproto_solicita_2fa_e_rejeita_conta_diferente(): void
    {
        $this->botPadrao();
        $this->configurarMtproto();
        $visitante = User::factory()->create([
            'telegram_id' => '123456789',
            'telegram_username' => 'maria_teste',
        ]);
        $this->respostasMtproto['verify'] = ['needsPassword' => true];

        $this->withSession(['agendafacil' => [
            'visitante_id' => $visitante->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $this->payloadValido()));

        $this->post(route('telegram.mtproto.enviar-codigo'), [
            'telefone' => '+5511999999999',
            'consentimento' => '1',
        ]);

        $this->post(route('telegram.mtproto.verificar'), ['codigo' => '12345'])
            ->assertRedirect(route('telegram.mtproto.autorizacao'));
        $this->assertTrue((bool) session('agendafacil.mtproto.needs_password'));

        $this->respostasMtproto['verify'] = [
            'user' => ['id' => '999999999', 'username' => 'outra_conta', 'firstName' => 'Outra'],
            'groups' => [['chat_id' => '-100700004', 'nome' => 'Grupo alheio', 'tipo' => 'supergroup']],
        ];

        $this->post(route('telegram.mtproto.verificar'), ['senha_2fa' => 'senha-temporaria'])
            ->assertRedirect(route('agendafacil.codigo'))
            ->assertSessionHasErrors('telegram');

        $this->assertDatabaseMissing('grupo_telegrams', ['chat_id' => '-100700004']);
    }

    public function test_sair_limpa_sessao_do_fluxo(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'telegram_ok' => true,
        ]])->post(route('agendafacil.sair'));

        $resposta->assertRedirect(route('agendafacil.acesso'));
        $this->assertNull(session('agendafacil'));
    }

    public function test_sessao_do_fluxo_expira_em_2h(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time() - 7300,
        ]])->get(route('agendafacil.confirmar-dados'));

        $resposta->assertRedirect(route('agendafacil.acesso'));
    }

    public function test_confirmar_valido_libera_etapa_grupos(): void
    {
        $this->botPadrao();
        $usuario = User::factory()->create(['telegram_id' => '123456789', 'telegram_username' => 'maria_teste']);

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $this->payloadValido()));

        $resposta->assertRedirect(route('agendafacil.confirmar-dados'));
        $this->assertTrue((bool) session('agendafacil.codigo_ok'));
    }

    public function test_confirmar_rejeita_conta_diferente_do_cadastro(): void
    {
        $this->botPadrao();
        $usuario = User::factory()->create(['telegram_id' => '999888777', 'telegram_username' => 'outra_pessoa']);

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $this->payloadValido()));

        $resposta->assertRedirect(route('agendafacil.codigo'));
        $resposta->assertSessionHasErrors('telegram');
        $this->assertNull(session('agendafacil.codigo_ok'));
    }

    public function test_confirmar_exige_visitante_identificado(): void
    {
        $this->botPadrao();

        $resposta = $this->get(route('telegram.confirmar-callback', $this->payloadValido()));

        $resposta->assertRedirect(route('agendafacil.acesso'));
    }

    public function test_confirmar_rejeita_hash_adulterado(): void
    {
        $this->botPadrao();
        $usuario = User::factory()->create(['telegram_id' => '123456789']);

        $payload = $this->payloadValido();
        $payload['first_name'] = 'Outro Nome';

        $resposta = $this->withSession(['agendafacil' => [
            'visitante_id' => $usuario->id,
            'digitos_ok' => true,
            'acesso_em' => time(),
        ]])->get(route('telegram.confirmar-callback', $payload));

        $resposta->assertRedirect(route('agendafacil.codigo'));
        $resposta->assertSessionHasErrors('telegram');
        $this->assertNull(session('agendafacil.codigo_ok'));
    }
}
