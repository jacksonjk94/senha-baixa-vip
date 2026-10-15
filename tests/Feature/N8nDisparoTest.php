<?php

namespace Tests\Feature;

use App\Http\Controllers\N8nDisparoController;
use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class N8nDisparoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.n8n.secret' => 'segredo-teste']);
        config(['services.telegram.token' => 'token-teste']);
    }

    protected function headers(string $secret = 'segredo-teste'): array
    {
        return ['X-N8N-Secret' => $secret];
    }

    public function test_sem_chave_secreta_retorna_403(): void
    {
        $this->getJson(route('n8n.disparos.processar'))
            ->assertForbidden()
            ->assertJson(['erro' => 'Acesso negado']);
    }

    public function test_chave_errada_retorna_403(): void
    {
        $this->withHeaders($this->headers('errada'))
            ->getJson(route('n8n.disparos.processar'))
            ->assertForbidden()
            ->assertJson(['erro' => 'Acesso negado']);
    }

    public function test_processa_pendente_vencido_e_marca_enviado(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200001', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('processados', 1)
            ->assertJsonPath('enviados', 1)
            ->assertJsonPath('erros', 0);

        $agendamento->refresh();

        $this->assertSame(Agendamento::STATUS_ENVIADO, $agendamento->status);
        $this->assertNotNull($agendamento->enviado_em);
        $this->assertSame(1, $agendamento->posicao);
        $this->assertSame(1, $agendamento->total_grupo_horario);
    }

    public function test_falha_no_telegram_marca_erro(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200002', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('processados', 1)
            ->assertJsonPath('enviados', 0)
            ->assertJsonPath('erros', 1);

        $agendamento->refresh();

        $this->assertSame(Agendamento::STATUS_ERRO, $agendamento->status);
        $this->assertNotEmpty($agendamento->mensagem_erro);
    }

    public function test_grupo_fechado_antes_do_disparo_mantem_pendente(): void
    {
        $this->travelTo(now('America/Sao_Paulo')->setTime(12, 0, 0));

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100200003', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Visitante x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('processados', 1)
            ->assertJsonPath('enviados', 0)
            ->assertJsonPath('ignorados', 1);

        $this->assertSame(
            Agendamento::STATUS_PENDENTE,
            $agendamento->refresh()->status
        );
    }

    public function test_cron_vercel_com_bearer_processa_e_le_precisao(): void
    {
        config(['services.n8n.secret' => 'segredo-n8n', 'services.cron.secret' => 'segredo-cron']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200015', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Vencido x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer segredo-cron'])
            ->getJson(route('n8n.disparos.precisao'))
            ->assertOk()
            ->assertJsonStructure(['latencia_ativa', 'latencia_ms', 'antecipacao_segundos']);

        $this->withHeaders(['Authorization' => 'Bearer segredo-cron'])
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('enviados', 1);
    }

    public function test_bearer_errado_retorna_403(): void
    {
        config(['services.n8n.secret' => 'segredo-n8n', 'services.cron.secret' => 'segredo-cron']);

        $this->withHeaders(['Authorization' => 'Bearer errada'])
            ->getJson(route('n8n.disparos.processar'))
            ->assertForbidden();
    }
    public function test_precisao_exige_chave_secreta(): void
    {
        $this->getJson(route('n8n.disparos.precisao'))
            ->assertForbidden();
    }

    public function test_precisao_retorna_latencia_do_painel(): void
    {
        \App\Models\Configuracao::set(\App\Models\Configuracao::DISPARO_LATENCIA_ATIVO, '1');
        \App\Models\Configuracao::set(\App\Models\Configuracao::DISPARO_LATENCIA_MS, '250');

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.precisao'))
            ->assertOk()
            ->assertJsonPath('latencia_ativa', true)
            ->assertJsonPath('latencia_ms', 250);
    }

    public function test_processar_informa_latencia_aplicada(): void
    {
        \App\Models\Configuracao::set(\App\Models\Configuracao::DISPARO_LATENCIA_ATIVO, '1');
        \App\Models\Configuracao::set(\App\Models\Configuracao::DISPARO_LATENCIA_MS, '250');

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200013', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Vencido x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('enviados', 1)
            ->assertJsonPath('latencia_ativa', true)
            ->assertJsonPath('latencia_ms', 250);
    }

    public function test_alvo_envio_aberto_e_inicio_do_minuto(): void
    {
        $controller = new N8nDisparoController();
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200010', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->make([
            'grupo_telegram_id' => $grupo->id,
            'data_hora_envio' => \Carbon\Carbon::parse('2026-10-13 12:17:45'),
        ]);

        $this->assertSame('12:17:00', $controller->alvoEnvio($agendamento, $grupo)->format('H:i:s'));
    }

    public function test_alvo_envio_fechado_usa_horario_de_disparo(): void
    {
        $controller = new N8nDisparoController();
        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100200011', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->make([
            'grupo_telegram_id' => $grupo->id,
            'data_hora_envio' => \Carbon\Carbon::parse('2026-10-13 10:00:00'),
        ]);

        $alvo = $controller->alvoEnvio($agendamento, $grupo);

        $this->assertSame('2026-10-13 19:58:00', $alvo->format('Y-m-d H:i:s'));
    }

    public function test_fechado_com_latencia_envia_no_bloco_antecipado(): void
    {
        // Data passada de propósito: o cálculo da espera usa o relógio real,
        // então o "agora" congelado precisa já estar após o instante de
        // disparo (250ms antes do bloco) para cair direto na trava do bloco.
        $base = \Carbon\Carbon::parse('2026-10-10 19:57:59.800', 'America/Sao_Paulo');
        $this->travelTo($base);

        \App\Models\Configuracao::set(\App\Models\Configuracao::DISPARO_LATENCIA_ATIVO, '1');
        \App\Models\Configuracao::set(\App\Models\Configuracao::DISPARO_LATENCIA_MS, '250');

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Rodada', 'chat_id' => '-100200014', 'tipo' => 'supergroup',
            'modo' => 'fechado', 'horario_disparo' => '19:58', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Rodada x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => $base->copy()->setTime(10, 0, 0),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('processados', 1)
            ->assertJsonPath('enviados', 1)
            ->assertJsonPath('erros', 0);

        $this->assertSame(Agendamento::STATUS_ENVIADO, $agendamento->refresh()->status);
    }

    public function test_futuro_alem_da_janela_mantem_pendente(): void
    {
        config(['services.disparo.antecipacao_segundos' => 70]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200012', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        $agendamento = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Futuro x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => now('America/Sao_Paulo')->addMinutes(10),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('processados', 0)
            ->assertJsonPath('enviados', 0);

        $this->assertSame(Agendamento::STATUS_PENDENTE, $agendamento->refresh()->status);
    }

    public function test_futuros_e_cancelados_nao_entram_no_lote(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $grupo = GrupoTelegram::create([
            'nome' => 'Grupo Aberto', 'chat_id' => '-100200004', 'tipo' => 'supergroup',
            'modo' => 'aberto', 'ativo' => true,
        ]);
        Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Futuro x1',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => now('America/Sao_Paulo')->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
        ]);
        $cancelado = Agendamento::factory()->create([
            'grupo_telegram_id' => $grupo->id,
            'texto' => 'Cancelado x2',
            'destino_tipo' => 'grupo',
            'destino_id' => $grupo->chat_id,
            'data_hora_envio' => now('America/Sao_Paulo')->subMinutes(5),
            'status' => Agendamento::STATUS_CANCELADO,
        ]);

        $this->withHeaders($this->headers())
            ->getJson(route('n8n.disparos.processar'))
            ->assertOk()
            ->assertJsonPath('processados', 0)
            ->assertJsonPath('enviados', 0);

        $this->assertSame(
            Agendamento::STATUS_CANCELADO,
            $cancelado->refresh()->status
        );
    }
}
