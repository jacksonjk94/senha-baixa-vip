@extends('layouts.telegram')
@section('titulo', 'Acesso')

@section('conteudo')
<div class="text-center mt-3">
    <span class="tg-logo telegram-gradient">✈</span>
    <h1 class="h4 mt-2 mb-1">AgendaFácil</h1>
    <p class="text-muted">Digite os <strong>últimos 4 dígitos</strong> do seu telefone para começar.</p>
</div>

<div class="tg-steps">
    <div class="tg-step active">1. Acesso</div>
    <div class="tg-step">2. Dados</div>
    <div class="tg-step">3. Telegram</div>
    <div class="tg-step">4. Código</div>
    <div class="tg-step">5. Grupo</div>
    <div class="tg-step">6. Agendar</div>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">🔓 Desbloquear acesso</div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('agendafacil.acesso.validar') }}">
            @csrf
            <label class="form-label fw-semibold" for="digitos">Últimos 4 dígitos</label>
            <input id="digitos" name="digitos" class="input-telegram text-center fs-3 fw-bold"
                   inputmode="numeric" maxlength="4" placeholder="••••"
                   value="{{ old('digitos') }}" required autofocus>
            @error('digitos')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn-telegram mt-3">Continuar →</button>
        </form>
    </div>
</div>
@endsection
