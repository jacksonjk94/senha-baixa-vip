@extends('layouts.telegram')
@section('titulo', 'Acesso')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step active">1. Acesso</div>
    <div class="tg-step">2. Confirmar os Dados</div>
    <div class="tg-step">3. Código</div>
    <div class="tg-step">4. Grupos do Telegram</div>
    <div class="tg-step">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-1">Desbloquear</h1>
        <p class="text-muted">Informe os 4 últimos dígitos do telefone do agendamento.</p>
        <form method="POST" action="{{ route('agendafacil.acesso.validar') }}">
            @csrf
            <label class="form-label fw-semibold" for="digitos">4 últimos dígitos</label>
            <input id="digitos" name="digitos" class="input-telegram"
                   inputmode="numeric" maxlength="4" placeholder=""
                   value="{{ old('digitos') }}" required autofocus>
            @error('digitos')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn-telegram mt-3">Continuar</button>
        </form>
    </div>
</div>
@endsection
