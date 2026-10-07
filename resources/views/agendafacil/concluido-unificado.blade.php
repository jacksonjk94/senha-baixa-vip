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
        <div class="border border-slate-200 rounded-lg p-4 mt-4 bg-blue-50">
            <p class="mb-0 text-sm text-slate-800">Agendado com sucesso! ✅ Sua mensagem será enviada no grupo <strong>{{ $agendamento->destino_nome }}</strong> no dia <strong>{{ $agendamento->data_hora_envio->format('d/m/Y \à\s H:i') }}</strong>. Posição: <strong>{{ $agendamento->posicaoTexto() }}</strong>.</p>
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
