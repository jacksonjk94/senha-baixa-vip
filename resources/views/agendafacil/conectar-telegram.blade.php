@extends('layouts.telegram')
@section('titulo', 'Conectar Telegram')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Dados ✓</div>
    <div class="tg-step active">3. Telegram</div>
    <div class="tg-step">4. Código</div>
    <div class="tg-step">5. Grupo</div>
    <div class="tg-step">6. Agendar</div>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">✈ Conectar Telegram</div>
    <div class="card-body p-4 text-center">
        <p class="text-muted">Autorize com sua conta do Telegram para continuar. Se tiver o app instalado, a confirmação é imediata.</p>
        <div class="d-flex justify-content-center my-3" style="min-height:44px">
            {!! $botaoWidget !!}
        </div>
        @if ($bot)
            <p class="text-muted small">Bot: <strong>{{ $bot }}</strong> · após autorizar você volta automaticamente.</p>
        @endif
        <p class="mt-3 mb-0"><a href="{{ route('agendafacil.codigo') }}">Pular esta etapa por agora →</a></p>
    </div>
</div>
@endsection
