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

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8 text-center">
    <div class="text-4xl">👥</div>
    <h2 class="text-lg font-bold text-slate-800 mt-2">{{ $grupo->nome }}</h2>
    <p class="text-slate-500 text-sm">{{ $grupo->tipo }} · ID {{ $grupo->chat_id }}</p>
    <p class="text-slate-500 text-sm">As senhas serão enviadas para este destino no horário programado.</p>
    <form method="POST" action="{{ route('grupos.confirmar-final') }}">
        @csrf
        <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-4 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Está correto, agendar →</button>
    </form>
    <a href="{{ route('grupos.selecionar') }}" class="inline-block mt-3 text-sm text-blue-700 hover:text-blue-900 font-medium transition-colors">← Escolher outro grupo</a>
</div>
@endsection
