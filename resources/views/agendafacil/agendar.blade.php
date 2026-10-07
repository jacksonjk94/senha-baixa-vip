@extends('layouts.telegram')
@section('titulo', 'Agendar disparo')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1 ✓</div>
    <div class="tg-step done">2 ✓</div>
    <div class="tg-step done">3 ✓</div>
    <div class="tg-step done">4 ✓</div>
    <div class="tg-step done">5 ✓</div>
    <div class="tg-step active">6. Agendar</div>
</div>

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Agendamento do disparo</p>
    <p class="text-slate-500 text-sm md:text-base mb-4">Destino: <strong class="text-slate-800">{{ $grupoSessao['destino_nome'] ?? '—' }}</strong> ({{ $grupoSessao['destino_tipo'] ?? 'grupo' }})</p>
    @if (($modoDisparo ?? 'agendado') === 'imediato')
        <div class="bg-blue-50 text-blue-900 text-xs rounded-lg px-4 py-3 mb-4">Modo <strong>disparo na hora</strong>: a senha será enviada imediatamente após confirmar.</div>
    @endif
    <form method="POST" action="{{ route('agendamento.salvar') }}">
        @csrf
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nome do visitante</label>
        <input name="nome_visitante" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" value="{{ old('nome_visitante', $confirmacao['nome_visitante'] ?? '') }}" required>
        <input type="hidden" name="destino_tipo" value="{{ $grupoSessao['destino_tipo'] ?? 'grupo' }}">
        @if (! empty($grupoSessao['grupo_id']))
            <input type="hidden" name="grupo_telegram_id" value="{{ $grupoSessao['grupo_id'] }}">
        @endif

        <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Data</label>
                <input type="date" name="data_envio" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" value="{{ old('data_envio', date('Y-m-d')) }}" @if (($modoDisparo ?? 'agendado') !== 'imediato') required @endif>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Hora</label>
                <input type="time" name="hora_envio" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" value="{{ old('hora_envio', $horarioPadrao ?? '') }}" @if (($modoDisparo ?? 'agendado') !== 'imediato') required @endif>
            </div>
        </div>
        @error('data_envio')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @error('hora_envio')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

        <label class="block text-sm font-medium text-slate-700 mb-1.5 mt-3">Observações (opcional)</label>
        <textarea name="dados_senha" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" rows="2" maxlength="500">{{ old('dados_senha') }}</textarea>

        <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Agendar disparo ✈</button>
    </form>
</div>
@endsection
