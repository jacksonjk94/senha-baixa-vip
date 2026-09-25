@extends('layouts.telegram')
@section('titulo', 'Cadastro')

@section('conteudo')
<div class="text-center mt-3">
    <span class="tg-logo telegram-gradient">✈</span>
    <h1 class="h4 mt-2 mb-1">AgendaFácil</h1>
    <p class="text-muted">Cadastre-se para usar o agendamento pelo Telegram.</p>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">📝 Novo cadastro</div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('visitante.cadastro.salvar') }}">
            @csrf
            <label class="form-label fw-semibold" for="name">Nome completo</label>
            <input id="name" name="name" class="input-telegram" maxlength="120" value="{{ old('name') }}" required autofocus>
            @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <label class="form-label fw-semibold mt-3" for="email">E-mail</label>
            <input id="email" name="email" type="email" class="input-telegram" maxlength="190" value="{{ old('email') }}" required>
            @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <label class="form-label fw-semibold mt-3" for="telefone">Telefone / WhatsApp</label>
            <div class="input-group">
                <span class="input-group-text">+55</span>
                <input id="telefone" name="telefone" class="form-control input-telegram" maxlength="30" placeholder="(13) 98138-8690" value="{{ old('telefone') }}" required>
            </div>
            @error('telefone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <button type="submit" class="btn-telegram mt-3">Cadastrar e continuar →</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="{{ route('agendafacil.acesso') }}">Já tenho cadastro — ir para o acesso</a></p>
    </div>
</div>
@endsection
