<?php

namespace App\Console\Commands;

use App\Jobs\EnviarSenhaTelegram;
use App\Models\Agendamento;
use Illuminate\Console\Command;

class ProcessarAgendamentos extends Command
{
    protected $signature = 'agendafacil:disparar {--limite=50 : Máximo de agendamentos por execução}';

    protected $description = 'Dispara agendamentos vencidos (status pendente) via Telegram';

    public function handle(): int
    {
        $agendamentos = Agendamento::pendentes()
            ->vencidos()
            ->orderBy('data_hora_envio')
            ->limit((int) $this->option('limite'))
            ->get();

        if ($agendamentos->isEmpty()) {
            $this->info('Nenhum agendamento pendente para disparar.');

            return self::SUCCESS;
        }

        foreach ($agendamentos as $agendamento) {
            EnviarSenhaTelegram::dispatch($agendamento->id);
        }

        $this->info("{$agendamentos->count()} agendamento(s) enfileirado(s) para disparo.");

        return self::SUCCESS;
    }
}
