<?php

namespace Tests\Feature;

use App\Models\TelegramBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validação da pessoa pelo próprio Telegram (Login Widget) — etapa 1.
 * O robô (token fixo) não é tocado: aqui só se confirma identidade.
 */
class TelegramValidacaoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'TOKEN_TESTE_VALIDACAO_123';

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

        $resposta->assertRedirect(route('grupos.selecionar'));
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
