<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendamentoFactory extends Factory
{
    protected $model = Agendamento::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'grupo_telegram_id' => null,
            'nome_visitante' => fake()->name(),
            'dados_senha' => 'Senha de teste',
            'senha_codigo' => 'AF-' . strtoupper(fake()->bothify('?????')),
            'destino_tipo' => Agendamento::DESTINO_GRUPO,
            'destino_id' => '-1001234567890',
            'destino_nome' => 'Grupo Teste',
            'data_hora_envio' => now()->addHour(),
            'status' => Agendamento::STATUS_PENDENTE,
            'mensagem_erro' => null,
            'enviado_em' => null,
        ];
    }
}
