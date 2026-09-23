@extends('layouts.telegram')
@section('titulo', 'Confirmar grupo')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1 ✓</div>
    <div class="tg-step done">2 ✓</div>
    <div class="tg-step done">3 ✓</div>
    <div class="tg-step done">4 ✓</div>
    <div class="tg-step active">5. Confirmar</div>
    <div class="tg-step">6. Agendar</div>
</div>

<div class="card card-telegram text-center">
    <div class="card-header-telegram">✅ Confirmação do grupo</div>
    <div class="card-body p-4">
        <div class="display-4">👥</div>
        <h2 class="h5 mt-2">{{ $grupo->nome }}</h2>
        <p class="text-muted">{{ $grupo->tipo }} · ID {{ $grupo->chat_id }}</p>
        <p>As senhas serão enviadas para este destino no horário programado.</p>
        <form method="POST" action="{{ route('grupos.confirmar-final') }}">
            @csrf
            <button type="submit" class="btn-telegram">Está correto, agendar →</button>
        </form>
        <a href="{{ route('grupos.selecionar') }}" class="btn btn-link mt-2">← Escolher outro grupo</a>
    </div>
</div>
@endsection
