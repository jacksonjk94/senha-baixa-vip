@extends('layouts.telegram')
@section('titulo', 'Concluído')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 6])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Login Telegram</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Concluído</h1>
    <p class="text-slate-500 text-sm md:text-base mb-0">Grupo confirmado. Pode fechar esta janela.</p>
    <form method="POST" action="{{ route('agendafacil.sair') }}" class="mt-3">
        @csrf
        <button type="submit" class="text-sm text-blue-700 hover:text-blue-900 font-medium transition-colors">Sair (encerrar esta sessão)</button>
    </form>
    @if ($agendamento?->data_hora_envio)
        <div class="border border-emerald-200 rounded-lg p-4 mt-4 bg-emerald-50 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-emerald-600 text-white flex items-center justify-center text-2xl" style="animation: af-step-pop .4s ease-out">✓</div>
            <p class="mt-3 mb-0 text-sm text-slate-800">Agendado com sucesso! ✅ Sua mensagem será enviada no grupo <strong>{{ $agendamento->destino_nome }}</strong> no dia <strong>{{ $agendamento->data_hora_envio->format('d/m/Y \à\s H:i') }}</strong>. Posição: <strong>{{ $agendamento->posicaoTexto() }}</strong>.</p>
            @php
                $inicio = $agendamento->data_hora_envio->format('Ymd\THis');
                $fim = $agendamento->data_hora_envio->copy()->addMinutes(30)->format('Ymd\THis');
                $gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode('Envio no grupo ' . $agendamento->destino_nome) . '&dates=' . $inicio . '/' . $fim . '&details=' . urlencode('Posição: ' . $agendamento->posicaoTexto());
            @endphp
            <a href="{{ $gcal }}" target="_blank" rel="noopener" class="inline-block mt-4 px-4 py-2 rounded-lg border border-blue-200 text-blue-900 text-sm font-medium hover:bg-blue-50 transition-all duration-200">📅 Adicionar ao calendário</a>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script>
    (function () {
        if (typeof confetti !== 'function') return;
        var fim = Date.now() + 1200;
        (function lancar() {
            confetti({ particleCount: 4, angle: 60, spread: 55, origin: { x: 0, y: 0.7 }, colors: ['#1E3A8A', '#1D4ED8', '#059669', '#EFF6FF'] });
            confetti({ particleCount: 4, angle: 120, spread: 55, origin: { x: 1, y: 0.7 }, colors: ['#1E3A8A', '#1D4ED8', '#059669', '#EFF6FF'] });
            if (Date.now() < fim) requestAnimationFrame(lancar);
        })();
    })();
</script>
@endsection
