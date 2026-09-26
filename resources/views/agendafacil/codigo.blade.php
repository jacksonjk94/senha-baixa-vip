@extends('layouts.telegram')
@section('titulo', 'Código Telegram')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Confirmar os Dados ✓</div>
    <div class="tg-step active">3. Código</div>
    <div class="tg-step">4. Grupos do Telegram</div>
    <div class="tg-step">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-1">Confirme que é você</h1>
        <p class="text-muted">Faça login com a sua conta do Telegram para confirmar. Isso <strong>não</strong> dá acesso do sistema à sua conta — é só uma verificação de identidade.</p>

        @error('telegram')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if ($temBot)
            <div class="d-flex justify-content-center my-3">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="{{ $botUsername }}"
                        data-size="large"
                        data-auth-url="{{ $confirmarUrl }}"></script>
            </div>
            @if ($usandoEnv)
                <div class="alert alert-warning small">Bot via configuração do ambiente. Se o botão acima não aparecer, libere o domínio <code>{{ $dominio ?? 'do site' }}</code> no <strong>@BotFather</strong> com o comando <code>/setdomain</code>.</div>
            @endif
            <p class="text-muted small text-center">Prefere o código do grupo? Use abaixo.</p>
        @else
            <div class="alert alert-warning">Confirmação pelo Telegram indisponível no momento (nenhum bot configurado). Use o código do grupo abaixo.</div>
        @endif

        <details class="mt-2">
            <summary class="fw-semibold" style="cursor:pointer">Usar o código enviado no grupo</summary>
            <div class="mt-3">
                <p class="text-muted">Digite o código recebido no Telegram.
                    @if ($grupo)
                        Enviamos no grupo <strong>{{ $grupo->nome }}</strong>.
                    @endif
                </p>

                @if (! empty($codigo_debug))
                    <div class="alert alert-info">Ambiente local — código: <strong>{{ $codigo_debug }}</strong></div>
                @endif
                @if (empty($codigo_enviado_telegram))
                    <div class="alert alert-warning">Não conseguimos entregar o código agora. Toque em reenviar para tentar de novo.</div>
                @endif

                <form method="POST" action="{{ route('agendafacil.codigo.validar') }}">
                    @csrf
                    <label class="form-label fw-semibold" for="codigo">Código</label>
                    <input id="codigo" name="codigo" class="input-telegram" inputmode="numeric" maxlength="6" placeholder="" required>
                    @error('codigo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <button type="submit" class="btn-telegram mt-3">Enviar</button>
                </form>

                <form method="POST" action="{{ route('agendafacil.codigo.reenviar') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn-telegram" style="background:linear-gradient(135deg,#4A6076 0%,#33475B 100%)">Reenviar código</button>
                </form>
                <p class="text-muted small mt-2">Não recebeu ou deu erro? Reenvie o código.</p>
            </div>
        </details>
    </div>
</div>
@endsection
