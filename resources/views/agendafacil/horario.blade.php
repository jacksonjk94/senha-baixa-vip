@extends('layouts.telegram')
@section('titulo', 'Horário')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 3])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Agendamento</p>
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Quando enviaremos?</h1>
        <p class="text-slate-500 text-sm md:text-base mb-6">Escolha a data e o horário do envio.</p>

    <form method="POST" action="{{ route('agendafacil.horario.salvar') }}">
        @csrf
        <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="data_envio">Data do envio</label>
                    <input id="data_envio" type="date" name="data_envio" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200"
                           value="{{ old('data_envio', $rascunho['data_envio'] ?? $sugestaoData) }}"
                           min="{{ $dataMinima }}" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="hora_envio">Horário</label>
                    <input id="hora_envio" type="time" name="hora_envio" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200"
                           value="{{ old('hora_envio', $rascunho['hora_envio'] ?? $sugestaoHora) }}" required>
                </div>
            </div>
            @error('data_envio')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('hora_envio')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

            <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold         transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Avançar →</button>
    </form>
</div>
@endsection
