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

{{-- Modal premium de boas-vindas ao concluir --}}
@php
    $tgUser = session('agendafacil.telegram_user') ?? [];
    $primeiroNome = trim((string) ($tgUser['firstName'] ?? 'Visitante'));
    $username = $tgUser['username'] ?? null;
@endphp
<div id="modalConcluido" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: linear-gradient(135deg,#172B6B 0%,#1E3A8A 45%,#1D4ED8 100%); animation: af-fade-in .4s ease-out;">
    <button id="fecharModal" type="button" aria-label="Fechar" style="position:absolute;top:18px;right:18px;width:40px;height:40px;border-radius:999px;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;font-size:18px;cursor:pointer;transition:background .2s" onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">✕</button>

    <div class="relative w-full max-w-md text-center text-white" style="animation: af-step-pop .5s ease-out">
        <p class="text-sm font-bold tracking-widest uppercase" style="color:#BFDBFE">✈ AgendaFácil</p>

        <div class="relative w-24 h-24 mx-auto mt-5">
            <div class="w-24 h-24 rounded-full bg-white text-blue-900 flex items-center justify-center text-3xl font-bold" style="box-shadow:0 8px 24px rgba(0,0,0,.3)">{{ \Illuminate\Support\Str::substr($primeiroNome, 0, 1) }}</div>
            <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center text-lg" style="box-shadow:0 4px 10px rgba(0,0,0,.35)">✓</div>
        </div>
        <h2 class="mt-3 text-xl font-bold">{{ $primeiroNome }}</h2>
        @if ($username)<p class="text-sm" style="color:#BFDBFE">{{ '@' . $username }}</p>@endif

        <div class="mt-6 rounded-2xl p-5 text-left" style="background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.20);backdrop-filter:blur(6px)">
            <p class="text-xs font-bold tracking-wider uppercase" style="color:#BFDBFE">Agendamento confirmado</p>
            @if ($agendamento?->data_hora_envio)
                <p class="mt-2 mb-1 text-sm">📬 Grupo: <strong>{{ $agendamento->destino_nome }}</strong></p>
                <p class="mb-1 text-sm">🗓️ Envio: <strong>{{ $agendamento->data_hora_envio->format('d/m/Y \à\s H:i') }}</strong></p>
                <p class="mb-0 text-sm">🔢 Posição: <strong>{{ $agendamento->posicaoTexto() }}</strong></p>
            @else
                <p class="mt-2 mb-0 text-sm">Seu agendamento foi registrado com sucesso.</p>
            @endif
        </div>

        <div class="mt-5 inline-flex items-center gap-2 rounded-full px-5 py-2" style="background:rgba(16,185,129,.18);border:1px solid rgba(16,185,129,.45)">
            <span style="font-size:20px">✅</span>
            <span class="font-semibold text-sm">Tudo certo! Vamos avisar você no horário.</span>
        </div>
        <p class="mt-4 text-xs" style="color:#93C5FD">Obrigado por usar o AgendaFácil — sua mensagem estará na hora certa. 💙</p>
    </div>

    {{-- partículas decorativas flutuando --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <span class="af-float" style="top:15%;left:10%">✨</span>
        <span class="af-float" style="top:20%;right:12%;animation-delay:.6s">🎉</span>
        <span class="af-float" style="bottom:18%;left:15%;animation-delay:1.1s">🎈</span>
        <span class="af-float" style="bottom:22%;right:10%;animation-delay:.3s">✨</span>
    </div>
</div>

<style>
    @keyframes af-fade-in { from { opacity: 0; } to { opacity: 1; } }
    @keyframes af-float { 0%,100% { transform: translateY(0) rotate(-4deg); } 50% { transform: translateY(-14px) rotate(5deg); } }
    .af-float { position: absolute; font-size: 26px; animation: af-float 3s ease-in-out infinite; }
</style>
<script>
    document.getElementById('fecharModal').addEventListener('click', function () {
        document.getElementById('modalConcluido').style.display = 'none';
    });
</script>

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
