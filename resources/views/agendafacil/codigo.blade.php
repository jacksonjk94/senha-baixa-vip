@extends('layouts.telegram')
@section('titulo', 'Código Telegram')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 2])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Login Telegram</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Confirme que é você</h1>
    <p class="text-slate-500 text-sm md:text-base mb-6">Enviamos um código para o Telegram do número <strong>{{ $telefone }}</strong>.</p>

    @if ($erroEnvio)
        <div class="bg-red-50 text-red-600 text-xs rounded-lg px-4 py-3 mb-4">{{ $erroEnvio }}</div>
    @elseif (session('sucesso'))
        <div class="bg-emerald-50 text-emerald-700 text-xs rounded-lg px-4 py-3 mb-4">{{ session('sucesso') }}</div>
    @endif

    @error('telegram')
        <div class="bg-red-50 text-red-600 text-xs rounded-lg px-4 py-3 mb-4">{{ $message }}</div>
    @enderror

    @if (session('telegram_2fa_required'))
        <div class="bg-amber-50 text-amber-700 text-xs rounded-lg px-4 py-3 mb-4">Esta conta exige a senha de verificação em duas etapas (2FA).</div>
    @endif

    @if ($needsPassword)
        <form method="POST" action="{{ route('agendafacil.codigo.verificar') }}">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="senha_2fa">Senha 2FA</label>
            <input id="senha_2fa" name="senha_2fa" type="password" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" required autofocus>
            @error('senha_2fa')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Verificar</button>
        </form>
    @else
        <form method="POST" action="{{ route('agendafacil.codigo.verificar') }}">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="codigo">Código do Telegram</label>
            <div class="relative">
                <input id="codigo" name="codigo" class="w-full px-4 py-3 pr-24 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" inputmode="numeric" maxlength="8" placeholder="00000" required autofocus>
                <button type="button" id="btnColar" title="Colar" class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-full border border-slate-200 bg-white text-blue-900 text-xs font-semibold hover:bg-blue-50 transition-all duration-200">📋 Colar</button>
            </div>
            @error('codigo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Verificar</button>
        </form>

        <form method="POST" action="{{ route('agendafacil.codigo.reenviar') }}" class="mt-4 text-center" id="formReenviar">
            @csrf
            <button type="submit" class="text-sm text-blue-700 hover:text-blue-900 font-medium transition-colors disabled:text-slate-400" id="btnReenviar" disabled>Reenviar código</button>
            <span class="text-slate-400 text-xs" id="timerReenviar"></span>
        </form>
    @endif
</div>

@if (! $needsPassword)
<script>
    (function () {
        var btn = document.getElementById('btnReenviar');
        var timer = document.getElementById('timerReenviar');
        var enviadoEm = {{ (int) $enviadoEm }} * 1000;
        var cooldown = 60000;

        function tick() {
            var restante = cooldown - (Date.now() - enviadoEm);
            if (restante <= 0) {
                btn.disabled = false;
                timer.textContent = '';
                clearInterval(iv);
            } else {
                btn.disabled = true;
                timer.textContent = '(' + Math.ceil(restante / 1000) + 's)';
            }
        }

        var iv = setInterval(tick, 1000);
        tick();

        var btnColar = document.getElementById('btnColar');
        if (btnColar) {
            btnColar.addEventListener('click', async function () {
                var campo = document.getElementById('codigo');
                try {
                    var texto = await navigator.clipboard.readText();
                    campo.value = String(texto || '').replace(/\D/g, '').slice(0, 8);
                    campo.focus();
                } catch (e) {
                    campo.focus();
                    document.execCommand('paste');
                }
            });
        }
    })();
</script>
@endif
@endsection
