@extends('layouts.telegram')
@section('titulo', 'Grupos do Telegram')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 4])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Telegram</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Escolha o grupo</h1>
    <p class="text-slate-500 text-sm md:text-base mb-6">Somente grupos e supergrupos da sua conta aparecem aqui.</p>

    <form method="POST" action="{{ route('grupos.confirmar') }}" id="formGrupo">
        @csrf
        @php
            $chatSelecionado = (string) old('chat_id', $grupo['chat_id'] ?? $grupos->first()['chat_id']);
            $modoPadrao = old('modo', $grupo['modo'] ?? ($modos[$chatSelecionado]['modo'] ?? 'aberto'));
            $disparoPadrao = old('horario_disparo', $grupo['horario_disparo'] ?? ($modos[$chatSelecionado]['horario_disparo'] ?? ''));
        @endphp
        @foreach($grupos as $g)
            @php $modoGrupo = $modos[(string) $g['chat_id']]['modo'] ?? 'aberto'; @endphp
            <label class="flex gap-2 items-center p-3 w-full mb-2 rounded-lg cursor-pointer border border-slate-200 bg-slate-50 hover:bg-blue-50 hover:border-blue-200 transition-all duration-200 grupo-opcao"
                   data-modo="{{ $modoGrupo }}" data-disparo="{{ $modos[(string) $g['chat_id']]['horario_disparo'] ?? '' }}">
                <input type="radio" name="chat_id" value="{{ $g['chat_id'] }}" class="mt-0 accent-blue-900"
                       {{ $chatSelecionado === (string) $g['chat_id'] ? 'checked' : '' }} required>
                <span style="position:relative;width:40px;height:40px;flex-shrink:0">
                    <span style="position:absolute;inset:0;border-radius:999px;background:#1E3A8A;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px">{{ \Illuminate\Support\Str::substr($g['nome'], 0, 1) }}</span>
                    <img src="{{ route('telegram.foto.grupo', ['chatId' => $g['chat_id']]) }}" alt="Foto do grupo {{ $g['nome'] }}" loading="lazy"
                         style="position:absolute;inset:0;width:100%;height:100%;border-radius:999px;object-fit:cover;background:#E2E8F0" onerror="this.remove()">
                </span>
                <span class="flex-grow-1">
                    <span class="font-bold block text-sm text-slate-800">{{ $g['nome'] }}</span>
                    <small class="text-slate-500">{{ $g['tipo'] === 'supergroup' ? 'Supergrupo' : 'Grupo' }}</small>
                </span>
                <span style="width:10px;height:10px;border-radius:999px;background:#{{ $g['tipo'] === 'supergroup' ? '1D4ED8' : '1E3A8A' }}"></span>
            </label>
        @endforeach
        @error('chat_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

        <div class="border border-slate-200 rounded-lg p-4 mt-4 bg-slate-50">
            <p class="font-bold text-slate-800 text-sm mb-1">Tipo de envio do grupo</p>
            <p class="text-slate-500 text-xs mb-3">Aberto envia no minuto agendado. Fechado envia tudo junto no horário do disparo.</p>
            <div class="flex gap-2">
                <label class="flex-1 flex gap-2 items-center p-3 rounded-lg cursor-pointer border border-slate-200 bg-white transition-all duration-200 modo-opcao">
                    <input type="radio" name="modo" value="aberto" class="accent-blue-900" id="modoAberto" {{ $modoPadrao !== 'fechado' ? 'checked' : '' }} required>
                    <span><span class="font-bold block text-sm text-slate-800">🔓 Aberto</span>
                    <small class="text-slate-500">Horário livre</small></span>
                </label>
                <label class="flex-1 flex gap-2 items-center p-3 rounded-lg cursor-pointer border border-slate-200 bg-white transition-all duration-200 modo-opcao">
                    <input type="radio" name="modo" value="fechado" class="accent-blue-900" id="modoFechado" {{ $modoPadrao === 'fechado' ? 'checked' : '' }} required>
                    <span><span class="font-bold block text-sm text-slate-800">🔒 Fechado</span>
                    <small class="text-slate-500">Disparo em bloco</small></span>
                </label>
            </div>
            @error('modo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <div id="blocoDisparo" class="mt-3" hidden>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="horario_disparo">Horário do disparo em bloco</label>
                <input type="time" name="horario_disparo" id="horario_disparo" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" value="{{ $disparoPadrao }}">
                @error('horario_disparo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Confirmar grupo</button>
    </form>
    <div class="mt-4 text-center">
        <a href="{{ route('agendafacil.horario') }}" class="text-sm text-blue-700 hover:text-blue-900 font-medium transition-colors">← Voltar</a>
    </div>
</div>

<style>
    .grupo-opcao:has(input:checked) { border-color: #1E3A8A !important; background: #EFF6FF !important; }
    .modo-opcao:has(input:checked) { border-color: #1E3A8A !important; background: #EFF6FF !important; }
</style>

<script>
    (function () {
        var form = document.getElementById('formGrupo');
        if (!form) return;
        var bloco = document.getElementById('blocoDisparo');
        var hora = document.getElementById('horario_disparo');
        var modos = form.querySelectorAll('input[name="modo"]');

        function modoAtual() {
            var marcado = form.querySelector('input[name="modo"]:checked');
            return marcado ? marcado.value : 'aberto';
        }

        function aplicar() {
            var fechado = modoAtual() === 'fechado';
            bloco.hidden = !fechado;
            hora.required = fechado;
            if (!fechado) return;
            if (!hora.value) {
                var card = form.querySelector('.grupo-opcao:has(input[name="chat_id"]:checked)');
                if (card && card.dataset.disparo) hora.value = card.dataset.disparo;
            }
        }

        modos.forEach(function (m) { m.addEventListener('change', aplicar); });
        form.querySelectorAll('input[name="chat_id"]').forEach(function (c) {
            c.addEventListener('change', function () {
                var card = c.closest('.grupo-opcao');
                if (!card || modoAtual() === 'fechado') return;
                var alvo = form.querySelector('input[name="modo"][value="' + card.dataset.modo + '"]');
                if (alvo) { alvo.checked = true; aplicar(); }
            });
        });
        aplicar();
    })();
</script>
@endsection
