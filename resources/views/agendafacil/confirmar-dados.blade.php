@extends('layouts.telegram')
@section('titulo', 'Confirmar dados')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step active">2. Dados</div>
    <div class="tg-step">3. Telegram</div>
    <div class="tg-step">4. Código</div>
    <div class="tg-step">5. Grupo</div>
    <div class="tg-step">6. Agendar</div>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">📋 Confirme seus dados</div>
    <div class="card-body p-4">
        <p class="text-muted">Olá, <strong>{{ $usuario->name }}</strong>! Confira e complete as informações da senha.</p>
        <form method="POST" action="{{ route('agendafacil.confirmar-dados.salvar') }}">
            @csrf
            <label class="form-label fw-semibold">Nome do visitante</label>
            <input name="nome_visitante" class="input-telegram" value="{{ old('nome_visitante', $usuario->name) }}" required maxlength="120">
            @error('nome_visitante')<div class="text-danger small">{{ $message }}</div>@enderror

            <label class="form-label fw-semibold mt-3">Tipo de disparo</label>
            <select name="destino_tipo" class="input-telegram" required>
                <option value="grupo" @selected(old('destino_tipo') === 'grupo')>Para um grupo do Telegram</option>
                <option value="pessoa" @selected(old('destino_tipo') === 'pessoa')>Para pessoa (chat individual)</option>
            </select>

            <button type="submit" class="btn-telegram mt-3">Conectar Telegram →</button>
        </form>
    </div>
</div>
@endsection
