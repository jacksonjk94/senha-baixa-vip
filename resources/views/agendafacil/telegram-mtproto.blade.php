@extends('layouts.telegram')
@section('titulo', 'Conectar conta Telegram')

@section('conteudo')
<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Autorização da conta</p>
        <h1 class="h4 mb-2">Conectar ao Telegram</h1>
        <p class="text-muted">Conta identificada: <strong>{{ $usuario->name }}</strong>{{ $usuario->telegram_username ? ' · @'.$usuario->telegram_username : '' }}</p>

        @error('telegram')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('consentimento')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('telefone')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('codigo')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('senha_2fa')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if (! empty($estado['operation_id']) && ! empty($estado['needs_password']))
            <p class="mb-2">O Telegram exige a senha da verificação em duas etapas para continuar.</p>
            <form method="POST" action="{{ route('telegram.mtproto.verificar') }}">
                @csrf
                <label class="form-label fw-semibold" for="senha_2fa">Senha de verificação em duas etapas</label>
                <input id="senha_2fa" name="senha_2fa" type="password" class="input-telegram" autocomplete="current-password" required autofocus>
                <button type="submit" class="btn-telegram mt-3">Confirmar e listar grupos</button>
            </form>
        @elseif (! empty($estado['operation_id']))
            <div class="alert alert-info">
                @if ($estado['is_code_via_app'] ?? false)
                    O código foi enviado pelo Telegram. Confira a conversa oficial de serviço nos seus dispositivos.
                @else
                    O Telegram enviou um código para o número informado.
                @endif
            </div>
            <form method="POST" action="{{ route('telegram.mtproto.verificar') }}">
                @csrf
                <label class="form-label fw-semibold" for="codigo">Código de acesso</label>
                <input id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" class="input-telegram" maxlength="8" required autofocus>
                <button type="submit" class="btn-telegram mt-3">Validar e listar grupos</button>
            </form>
        @else
            <div class="border rounded p-3 mb-3" style="background:#F8FBFD">
                <p class="fw-semibold mb-2">O que será acessado</p>
                <p class="small mb-0">A autorização será usada somente para ler a lista de grupos e supergrupos da conta, confirmar a identidade e associar esses chats ao cadastro. O sistema não lerá mensagens nem enviará mensagens pela sua conta.</p>
            </div>

            <form method="POST" action="{{ route('telegram.mtproto.enviar-codigo') }}">
                @csrf
                <label class="form-label fw-semibold" for="telefone">Telefone da conta Telegram</label>
                <input id="telefone" name="telefone" type="tel" inputmode="tel" class="input-telegram" placeholder="+5511999999999" value="{{ old('telefone') }}" autocomplete="tel" required autofocus>

                <label class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="consentimento" value="1" @checked(old('consentimento')) required>
                    <span class="form-check-label">Autorizo o AgendaFácil a autenticar minha conta Telegram e consultar meus grupos e supergrupos para este fluxo.</span>
                </label>

                <p class="text-muted small mt-3 mb-0">O código e a senha 2FA, se solicitada, são enviados somente ao serviço MTProto local e não são registrados. Após a consulta, a sessão Telegram é encerrada.</p>
                <button type="submit" class="btn-telegram mt-3">Enviar código Telegram</button>
            </form>
        @endif

        <form method="POST" action="{{ route('telegram.mtproto.cancelar') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-link text-muted p-0">Cancelar e voltar</button>
        </form>
    </div>
</div>
@endsection
