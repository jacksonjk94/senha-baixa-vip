@extends('layouts.telegram')
@section('titulo', 'Teste de grupos Telegram')

@section('conteudo')
<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#1D4ED8;letter-spacing:.04em">Diagnóstico</p>
        <h1 class="h4 mb-2">Login e grupos Telegram</h1>
        <p class="text-muted">Confirme sua identidade pelo Telegram e autorize a consulta dos grupos da conta pessoal.</p>

        @error('telegram')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if ($usuario)
            <div class="border rounded p-3 mb-3" style="background:#F8FAFC">
                <p class="small text-muted mb-1">Conta validada</p>
                <p class="fw-bold mb-0">{{ $usuario->name }} · {{ $usuario->telegram_username ? '@'.$usuario->telegram_username : 'Telegram ID '.$usuario->telegram_id }}</p>
            </div>

            @if ($grupos->isEmpty())
                <div class="alert alert-warning mb-0">Nenhum grupo ou supergrupo foi retornado para esta conta.</div>
            @else
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Grupo</th><th>Chat ID</th><th>Tipo</th><th>Ativo</th></tr></thead>
                        <tbody>
                        @foreach ($grupos as $grupo)
                            <tr>
                                <td>{{ $grupo->nome }}</td>
                                <td><code>{{ $grupo->chat_id }}</code></td>
                                <td>{{ $grupo->tipo }}</td>
                                <td>{{ $grupo->ativo ? 'Sim' : 'Não' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif ($temBot)
            <div class="d-flex justify-content-center my-3">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="{{ $username }}"
                        data-size="large"
                        data-auth-url="{{ route('telegram.grupos-teste.callback') }}"></script>
            </div>
            <p class="text-muted small text-center mb-0">@if ($mtprotoConfigurado)Após validar sua identidade, você poderá autorizar a consulta MTProto da sua conta pessoal.@else A listagem completa exige a configuração do serviço MTProto local.@endif</p>
        @else
            <div class="alert alert-warning mb-0">Login Telegram indisponível: nenhum bot está configurado.</div>
        @endif

        <a href="{{ route('agendafacil.acesso') }}" class="btn btn-outline-secondary mt-3">Voltar ao acesso</a>
    </div>
</div>
@endsection
