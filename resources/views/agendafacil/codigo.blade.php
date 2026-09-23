@extends('layouts.telegram')
@section('titulo', 'Código de confirmação')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Dados ✓</div>
    <div class="tg-step done">3. Telegram ✓</div>
    <div class="tg-step active">4. Código</div>
    <div class="tg-step">5. Grupo</div>
    <div class="tg-step">6. Agendar</div>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">🔑 Validação por código</div>
    <div class="card-body p-4 text-center">
        <p class="text-muted">Digite o código de 6 dígitos para validar. Ele foi gerado nesta etapa.</p>
        @if (! empty($codigo_debug))
            <div class="alert alert-info">Ambiente local — código: <strong>{{ $codigo_debug }}</strong></div>
        @endif
        @if (! empty($codigo_enviado_telegram))
            <div class="alert alert-success">Enviamos o código no seu Telegram (@{{ config('services.telegram.bot') }}). Confira a conversa com o bot.</div>
        @else
            <div class="alert alert-warning">Conecte seu Telegram e inicie uma conversa com o bot para receber o código por lá.</div>
        @endif
        <form method="POST" action="{{ route('agendafacil.codigo.validar') }}">
            @csrf
            <input name="codigo" class="input-telegram text-center fs-3 fw-bold" inputmode="numeric" maxlength="6" placeholder="••••••" required>
            @error('codigo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <button type="submit" class="btn-telegram mt-3">Validar código →</button>
        </form>
        <a href="{{ route('telegram.connect') }}" class="btn btn-link mt-2">Conectar / reconectar Telegram</a>
    </div>
</div>
@endsection
