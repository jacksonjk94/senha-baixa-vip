@extends('layouts.telegram')
@section('titulo', 'Início')

@section('conteudo')
<div class="text-center mt-4">
    <span class="tg-logo telegram-gradient">✈</span>
    <h1 class="h3 mt-3 mb-2">SenhaBaixa</h1>
    <p class="text-muted">Agende sua senha pelo navegador, <strong>sem instalar nada</strong> e <strong>sem conta no Telegram</strong>. O robô envia sua mensagem no grupo <strong>exatamente no horário programado</strong> — a ordem de chegada é a sua posição.</p>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">Como funciona</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>Escolha o <strong>grupo de destino</strong>;</li>
            <li>Defina <strong>data e horário</strong> do disparo;</li>
            <li>Digite o <strong>texto</strong> que vai aparecer no grupo (ex.: nome + observação);</li>
            <li>Confirme e acompanhe a <strong>posição: X de Y</strong>.</li>
        </ol>
        <a href="{{ route('agendar') }}" class="btn-telegram mt-3 text-center">Agendar agora ✈</a>
        <p class="text-center mt-3 mb-0"><a href="{{ route('agendamento.meus') }}">Ver meus agendamentos</a></p>
    </div>
</div>
@endsection
