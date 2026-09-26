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
            <p id="tgConfirmHint" class="text-muted small text-center mb-0">Toque no botão acima e confirme no seu Telegram.</p>
            <p id="tgConfirmStatus" class="text-center small mt-2 mb-0" hidden>
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Aguardando confirmação no Telegram… conclua na outra janela.
            </p>
        @else
            <div class="alert alert-warning mb-0">Confirmação pelo Telegram indisponível no momento (nenhum bot configurado). Fale com o responsável.</div>
        @endif
    </div>
</div>

@if ($temBot)
<script>
    // O widget abre a confirmação em outra janela: mostra spinner enquanto
    // o visitante confirma lá; se ele voltar sem concluir, esconde de novo.
    (function () {
        var status = document.getElementById('tgConfirmStatus');
        var hint = document.getElementById('tgConfirmHint');
        if (!status) return;
        window.addEventListener('blur', function () {
            status.hidden = false;
            if (hint) hint.hidden = true;
        });
        window.addEventListener('focus', function () {
            status.hidden = true;
            if (hint) hint.hidden = false;
        });
    })();
</script>
@endif
@endsection
