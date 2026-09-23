<?php

use App\Console\Commands\ProcessarAgendamentos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AgendaFácil · verifica a cada minuto agendamentos vencidos e enfileira o disparo.
Schedule::command(ProcessarAgendamentos::class)->everyMinute();
