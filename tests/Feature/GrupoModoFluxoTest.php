<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrupoModoFluxoTest extends TestCase
{
    use RefreshDatabase;

    protected function sessaoBase(array $extras = []): array
    {
        return array_merge([
            'agendafacil.telegram_ok' => true,
            'agendafacil.telefone' => '+5511999999999',
            'agendafacil.acesso_em' => time(),
            'agendafacil.telegram_user' => ['id' => '999001', 'username' => 'luana', 'firstName' => 'Luana'],
            'agendafacil.telegram_grupos' => [
                ['chat_id' => '-5552808073', 'nome' => 'amigas vip', 'tipo' => 'group'],
            ],
            'agendafacil.rascunho' => [
                'data_envio' => now('America/Sao_Paulo')->addDay()->format('Y-m-d'),
                'hora_envio' => '19:00',
            ],
        ], $extras);
    }

    public function test_selecionar_exibe_opcao_de_modo(): void
    {
        $this->withSession($this->sessaoBase())
            ->get(route('grupos.selecionar'))
            ->assertOk()
            ->assertSee('Tipo de envio do grupo')
            ->assertSee('Fechado')
            ->assertSee('horario_disparo', false);
    }

    public function test_confirmar_exige_horario_quando_fechado(): void
    {
        $this->withSession($this->sessaoBase())
            ->post(route('grupos.confirmar'), ['chat_id' => '-5552808073', 'modo' => 'fechado'])
            ->assertSessionHasErrors('horario_disparo');
    }

    public function test_confirmar_guarda_modo_e_horario_na_sessao(): void
    {
        $this->withSession($this->sessaoBase())
            ->post(route('grupos.confirmar'), [
                'chat_id' => '-5552808073',
                'modo' => 'fechado',
                'horario_disparo' => '19:58',
            ])
            ->assertRedirect(route('agendafacil.confirmar-dados'));

        $this->assertSame('fechado', session('agendafacil.grupo.modo'));
        $this->assertSame('19:58', session('agendafacil.grupo.horario_disparo'));
    }

    public function test_confirmar_resumo_aplica_modo_ao_grupo(): void
    {
        $this->withSession($this->sessaoBase([
            'agendafacil.grupo' => [
                'chat_id' => '-5552808073',
                'nome' => 'amigas vip',
                'tipo' => 'group',
                'modo' => 'fechado',
                'horario_disparo' => '19:58',
            ],
        ]))->post(route('agendafacil.resumo.confirmar'), ['mensagem' => 'Luana'])
            ->assertRedirect(route('agendafacil.concluido'));

        $grupo = GrupoTelegram::where('chat_id', '-5552808073')->first();

        $this->assertNotNull($grupo);
        $this->assertSame(GrupoTelegram::MODO_FECHADO, $grupo->modo);
        $this->assertSame('19:58', $grupo->horario_disparo->format('H:i'));
        $this->assertSame(Agendamento::STATUS_PENDENTE, Agendamento::latest()->first()->status);
    }
}
