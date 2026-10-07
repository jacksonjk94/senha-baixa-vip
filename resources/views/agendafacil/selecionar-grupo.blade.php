@extends('layouts.telegram')
@section('titulo', 'Grupos do Telegram')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 4])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Telegram</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Escolha o grupo</h1>
    <p class="text-slate-500 text-sm md:text-base mb-6">Somente grupos e supergrupos da sua conta aparecem aqui.</p>

    <form method="POST" action="{{ route('grupos.confirmar') }}">
        @csrf
        @foreach($grupos as $g)
            <label class="flex gap-2 items-center p-3 w-full mb-2 rounded-lg cursor-pointer border border-slate-200 bg-slate-50 hover:bg-blue-50 hover:border-blue-200 transition-all duration-200 grupo-opcao">
                <input type="radio" name="chat_id" value="{{ $g['chat_id'] }}" class="mt-0 accent-blue-900"
                       {{ (string) old('chat_id', $grupo['chat_id'] ?? $grupos->first()['chat_id']) === (string) $g['chat_id'] ? 'checked' : '' }} required>
                <span class="flex-grow-1">
                    <span class="font-bold block text-sm text-slate-800">{{ $g['nome'] }}</span>
                    <small class="text-slate-500">{{ $g['tipo'] === 'supergroup' ? 'Supergrupo' : 'Grupo' }}</small>
                </span>
                <span style="width:10px;height:10px;border-radius:999px;background:#{{ $g['tipo'] === 'supergroup' ? '1D4ED8' : '1E3A8A' }}"></span>
            </label>
        @endforeach
        @error('chat_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Confirmar grupo</button>
    </form>
</div>

<style>
    .grupo-opcao:has(input:checked) { border-color: #1E3A8A !important; background: #EFF6FF !important; }
</style>
@endsection
