@extends('layouts.telegram')
@section('titulo', 'Grupos do Telegram')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Código ✓</div>
    <div class="tg-step done">3. Confirmar os Dados ✓</div>
    <div class="tg-step active">4. Grupos do Telegram</div>
    <div class="tg-step">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-1">Grupos do Telegram</h1>
        <p class="text-muted">Selecione em qual grupo da sua conta o disparo será efetuado.</p>

        <form method="POST" action="{{ route('grupos.confirmar') }}">
            @csrf
            @foreach($grupos as $g)
                <label class="border rounded d-flex gap-2 align-items-center p-3 w-100 mb-2" style="cursor:pointer;background:#F8FBFD">
                    <input type="radio" name="grupo_id" value="{{ $g->id }}" class="form-check-input mt-0" {{ (string) old('grupo_id', $agendamento?->grupo_telegram_id ?? $grupos->first()->id) === (string) $g->id ? 'checked' : '' }} required>
                    <span class="fw-bold">{{ $g->nome }}</span>
                    <small class="text-muted">{{ $g->etiquetaModo() }}@if($g->ehFechado() && $g->horario_disparo) — envia às {{ $g->horario_disparo->format('H:i') }}@endif</small>
                </label>
            @endforeach
            @error('grupo_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <button type="submit" class="btn-telegram">Eu confirmo que esse grupo é o correto</button>
        </form>
    </div>
</div>
@endsection
