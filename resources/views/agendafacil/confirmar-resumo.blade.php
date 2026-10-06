@extends('layouts.telegram')
@section('titulo', 'Confirme seu agendamento')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Código ✓</div>
    <div class="tg-step active">3. Confirmar os Dados</div>
    <div class="tg-step">4. Grupos do Telegram</div>
    <div class="tg-step">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-3">✅ Confirme seu agendamento</h1>

        <div class="border rounded p-3 mb-3" style="border-color:#D8ECF7;background:#F8FBFD">
            <p class="text-muted small mb-0">Grupo</p>
            <p class="fw-bold mb-2">{{ $grupo->nome }} ({{ $grupo->ehFechado() ? 'Fechado' : 'Aberto' }})</p>

            <p class="text-muted small mb-0">Mensagem</p>
            <p class="fw-bold mb-2">{{ $rascunho['mensagem'] }}</p>

            <p class="text-muted small mb-0">Envio</p>
            <p class="fw-bold mb-2">{{ \Carbon\Carbon::parse($rascunho['data_hora_envio'])->format('d/m/Y \à\s H:i') }}</p>

            <p class="text-muted small mb-0">Sua posição</p>
            <p class="fw-bold mb-0">{{ $rascunho['posicao'] }}ª de {{ $rascunho['total'] }} — sua mensagem será a {{ $rascunho['posicao'] }}ª a aparecer no grupo.</p>
        </div>

        @if ($rascunho['proxima_rodada'])
            <div class="alert alert-warning">Este grupo já fechou hoje. Você estará na 1ª posição da próxima rodada.</div>
        @endif

        <div class="d-flex gap-2">
            <a href="{{ route('agendafacil.confirmar-dados') }}" class="btn btn-outline-secondary flex-fill" style="border-radius:12px">← Voltar e editar</a>
            <form method="POST" action="{{ route('agendafacil.resumo.confirmar') }}" class="flex-fill">
                @csrf
                <button type="submit" class="btn-telegram">Confirmar agendamento</button>
            </form>
        </div>
    </div>
</div>
@endsection
