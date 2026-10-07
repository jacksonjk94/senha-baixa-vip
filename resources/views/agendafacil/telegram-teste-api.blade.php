@extends('layouts.telegram')
@section('titulo', 'Teste API Telegram')

@section('conteudo')
<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#1D4ED8;letter-spacing:.04em">Teste de integração</p>
        <h1 class="h4 mb-2">Login Telegram via API (MTProto)</h1>
        <p class="text-muted">Serviço configurado: <strong>{{ $configurado ? 'sim' : 'não' }}</strong></p>

        @if ($erro)
            <pre class="alert alert-danger" style="white-space:pre-wrap">{{ $erro }}</pre>
        @endif

        <h2 class="h6 mt-3">1) Enviar código</h2>
        <form method="POST" action="{{ route('telegram.teste-api.enviar') }}">
            @csrf
            <input name="telefone" type="tel" class="input-telegram" placeholder="+5511999999999" required>
            <button type="submit" class="btn-telegram mt-3">Enviar código</button>
        </form>

        <h2 class="h6 mt-4">2) Verificar código</h2>
        <form method="POST" action="{{ route('telegram.teste-api.verificar') }}">
            @csrf
            <input name="operation_id" class="input-telegram" placeholder="operationId" value="{{ $operationId }}" required>
            <input name="codigo" inputmode="numeric" class="input-telegram mt-2" placeholder="Código (ex: 12345)">
            <input name="senha_2fa" type="password" class="input-telegram mt-2" placeholder="Senha 2FA (se exigida)">
            <button type="submit" class="btn-telegram mt-3">Verificar</button>
        </form>

        @if ($resultado)
            <h2 class="h6 mt-4">Resposta</h2>
            <pre style="white-space:pre-wrap;background:#0F172A;color:#E2E8F0;padding:1rem;border-radius:.5rem">{{ $resultado }}</pre>
        @endif
    </div>
</div>
@endsection
