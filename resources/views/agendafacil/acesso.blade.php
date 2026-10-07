@extends('layouts.telegram')
@section('titulo', 'Acesso')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 1])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">AgendaFácil</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Qual é o seu telefone?</h1>
    <p class="text-slate-500 text-sm md:text-base mb-6">Escolha a opção que se encaixa em você.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6" role="group">
        <button type="button" class="modo-btn py-3 px-4 rounded-lg border-2 border-blue-900 bg-blue-50 text-blue-900 font-semibold shadow-sm text-left transition-all duration-200" data-modo="novo">Primeiro agendamento</button>
        <button type="button" class="modo-btn py-3 px-4 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-blue-50 hover:border-blue-200 text-left transition-all duration-200" data-modo="retorno">Já agendei antes</button>
    </div>

    @error('digitos')
        <div class="bg-red-50 text-red-600 text-xs rounded-lg px-4 py-3 mb-4">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('agendafacil.acesso.validar') }}">
        @csrf
        <input type="hidden" name="modo" id="modoInput" value="{{ old('modo', 'novo') }}">

        <div id="modoRetorno" hidden>
            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="digitos">4 últimos dígitos do telefone</label>
            <input id="digitos" name="digitos" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200"
                   inputmode="numeric" maxlength="4" placeholder="0000"
                   value="{{ old('digitos') }}">
        </div>

        <div id="modoNovo">
            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="telefone">Telefone completo</label>
            <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden focus-within:border-blue-700 focus-within:ring-1 focus-within:ring-blue-700 transition-all duration-200">
                <span class="px-3 py-3 bg-slate-50 border-r border-slate-200 text-slate-500 text-sm">+55</span>
                <input id="telefone" name="telefone" class="flex-1 px-4 py-3 outline-none text-slate-800 text-sm"
                       inputmode="tel" placeholder="(11) 90000-0000"
                       value="{{ old('telefone') }}" autofocus>
            </div>
            <p class="text-xs text-slate-500 mt-1">Digite o DDD e o número — o +55 já está fixo.</p>
            @error('telefone')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Continuar</button>
    </form>
</div>

<script>
    (function () {
        var botoes = document.querySelectorAll('.modo-btn');
        var modoInput = document.getElementById('modoInput');
        var retorno = document.getElementById('modoRetorno');
        var novo = document.getElementById('modoNovo');
        var digitos = document.getElementById('digitos');
        var telefone = document.getElementById('telefone');

        var ativo = 'modo-btn py-3 px-4 rounded-lg border-2 border-blue-900 bg-blue-50 text-blue-900 font-semibold shadow-sm text-left transition-all duration-200';
        var inativo = 'modo-btn py-3 px-4 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-blue-50 hover:border-blue-200 text-left transition-all duration-200';

        function aplicar(modo) {
            modoInput.value = modo;
            retorno.hidden = modo !== 'retorno';
            novo.hidden = modo !== 'novo';
            digitos.required = modo === 'retorno';
            telefone.required = modo === 'novo';
            (modo === 'novo' ? telefone : digitos).focus();
            botoes.forEach(function (b) { b.className = b.dataset.modo === modo ? ativo : inativo; });
        }

        botoes.forEach(function (b) { b.addEventListener('click', function () { aplicar(b.dataset.modo); }); });
        aplicar(modoInput.value || 'novo');
    })();
</script>
@endsection
