<?php

namespace Tests\Feature;

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
