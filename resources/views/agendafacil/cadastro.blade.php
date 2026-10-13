@extends('layouts.telegram')
@section('titulo', 'Cadastro')

@section('conteudo')
<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">{{ config('app.name') }}</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Novo cadastro</h1>
    <p class="text-slate-500 text-sm md:text-base mb-6">Cadastre-se para usar o agendamento pelo Telegram.</p>

    <form method="POST" action="{{ route('visitante.cadastro.salvar') }}">
        @csrf
        <label class="block text-sm font-medium text-slate-700 mb-1.5" for="name">Nome completo</label>
        <input id="name" name="name" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" maxlength="120" value="{{ old('name') }}" required autofocus>
        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

        <label class="block text-sm font-medium text-slate-700 mb-1.5 mt-3" for="email">E-mail <span class="text-slate-500 font-normal">(opcional)</span></label>
        <input id="email" name="email" type="email" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" maxlength="190" value="{{ old('email') }}">
        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

        <label class="block text-sm font-medium text-slate-700 mb-1.5 mt-3" for="telefone">Telefone / WhatsApp</label>
        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden focus-within:border-blue-700 focus-within:ring-1 focus-within:ring-blue-700 transition-all duration-200">
            <span class="px-3 py-3 bg-slate-50 border-r border-slate-200 text-slate-500 text-sm">+55</span>
            <input id="telefone" name="telefone" class="flex-1 px-4 py-3 outline-none text-slate-800 text-sm" maxlength="30" placeholder="(11) 90000-0000" value="{{ old('telefone') }}" required>
        </div>
        @error('telefone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

        <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Cadastrar e continuar →</button>
    </form>
    <p class="text-center mt-4 mb-0 text-sm"><a class="text-blue-700 hover:text-blue-900 font-medium transition-colors" href="{{ route('agendafacil.acesso') }}">Já tenho cadastro — ir para o acesso</a></p>
</div>
@endsection
