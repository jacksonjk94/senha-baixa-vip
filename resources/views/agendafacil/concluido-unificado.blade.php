@extends('layouts.telegram')
@section('titulo', 'Concluído')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Confirmar os Dados ✓</div>
    <div class="tg-step done">3. Código ✓</div>
    <div class="tg-step done">4. Grupos do Telegram ✓</div>
    <div class="tg-step active">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-1">Concluído</h1>
        <p class="text-muted mb-0">Grupo confirmado. Pode fechar esta janela.</p>
        <form method="POST" action="{{ route('agendafacil.sair') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-muted p-0">Sair (encerrar esta sessão)</button>
        </form>
        @if ($agendamento?->data_hora_envio)
            <div class="border rounded p-3 mt-3" style="border-color:#D8ECF7;background:#F8FBFD">
                <p class="mb-0">Agendado com sucesso! ✅ Sua mensagem será enviada no grupo <strong>{{ $agendamento->destino_nome }}</strong> no dia <strong>{{ $agendamento->data_hora_envio->format('d/m/Y \à\s H:i') }}</strong>. Posição: <strong>{{ $agendamento->posicaoTexto() }}</strong>.</p>
            </div>
        @endif
    </div>
</div>
@endsection
