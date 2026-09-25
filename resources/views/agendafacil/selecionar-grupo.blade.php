@extends('layouts.telegram')
@section('titulo', 'Grupos do Telegram')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Confirmar os Dados ✓</div>
    <div class="tg-step done">3. Código ✓</div>
    <div class="tg-step active">4. Grupos do Telegram</div>
    <div class="tg-step">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-1">Grupos do Telegram</h1>
        <p class="text-muted">Selecione o grupo alvo/onde sua mensagem rascunho deve ser enviada</p>

        <form method="POST" action="{{ route('grupos.confirmar') }}">
            @csrf
            <label class="border rounded d-flex gap-2 align-items-center p-3 w-100 mb-3" style="cursor:pointer;background:#F8FBFD">
                <input type="radio" name="grupo_id" value="{{ $grupo->id }}" class="form-check-input mt-0" checked required>
                <span class="fw-bold">{{ $grupo->nome }}</span>
            </label>
            @error('grupo_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <button type="submit" class="btn-telegram">Eu confirmo que esse grupo é o correto</button>
        </form>
    </div>
</div>
@endsection
