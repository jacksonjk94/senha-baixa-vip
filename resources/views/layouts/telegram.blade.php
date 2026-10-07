<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <title>@yield('titulo', 'AgendaFácil') · AgendaFácil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --tg-primary: #1E3A8A; --tg-dark: #1E40AF; --tg-light: #3B82F6;
            --tg-bg: #F8FAFC; --tg-success: #059669; --tg-warning: #FF9F43; --tg-danger: #EA5455;
        }
        body.tg-body { background: var(--tg-bg); color: #1E293B; min-height: 100vh; }
        .telegram-gradient { background: linear-gradient(135deg, #1E3A8A 0%, #1D4ED8 100%); }
        .telegram-gradient-light { background: linear-gradient(135deg, #3B82F6 0%, #1E3A8A 100%); }
        .btn-telegram {
            background: linear-gradient(135deg, #1E3A8A 0%, #1D4ED8 100%); background-size: 150% 150%; background-position: 0% 0%; color: #fff; border: none;
            border-radius: 12px; padding: 10px 24px; font-weight: 600; display: inline-flex;
            align-items: center; justify-content: center; gap: 8px; text-decoration: none;
            cursor: pointer; user-select: none; -webkit-tap-highlight-color: transparent; touch-action: manipulation;
            transition: transform .15s ease, box-shadow .2s ease, background-position .35s ease, filter .2s ease; width: 100%;
        }
        .btn-telegram:hover { transform: translateY(-2px); filter: brightness(1.08); background-position: 100% 100%; box-shadow: 0 6px 16px rgba(30,58,138,.35); color: #fff; }
        .btn-telegram:active { transform: translateY(0) scale(.98); filter: brightness(.96); box-shadow: 0 2px 6px rgba(30,58,138,.3); }
        .btn-telegram:focus-visible { outline: 3px solid rgba(30,58,138,.45); outline-offset: 2px; }
        .af-processando { opacity: .75; cursor: not-allowed !important; pointer-events: none; }
        .card-telegram { border: 1px solid #F1F5F9; border-radius: 16px; box-shadow: 0 4px 20px rgba(30,58,138,.06); overflow: hidden; background: #fff; }
        .card-header-telegram { background: linear-gradient(90deg, #1E3A8A, #1D4ED8); color: #fff; padding: 12px 20px; font-weight: 700; }
        .tg-steps { display: flex; gap: 6px; margin: 16px 0; flex-wrap: wrap; }
        .tg-step { flex: 1; min-width: 80px; min-height: 38px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; padding: 6px 4px; border-radius: 10px; background: #F1F5F9; color: #64748B; font-weight: 600; }
        .tg-step.active { background: linear-gradient(90deg, #1E3A8A, #1D4ED8); color: #fff; }
        .tg-step.done { background: #DCFCE7; color: #059669; }
        .badge-tg { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge-pendente { background: #FFF3E2; color: #E88B1D; }
        .badge-enviado { background: #DFF7E9; color: #1E9E5A; }
        .badge-erro { background: #FDE6E6; color: #D33; }
        .badge-cancelado { background: #E8EEF3; color: #64748B; }
        .input-telegram { width: 100%; border: 2px solid #E2E8F0; border-radius: 12px; padding: 10px 14px; font-size: 14px; }
        .input-telegram:focus { border-color: #1D4ED8; outline: none; box-shadow: 0 0 0 3px rgba(29,78,216,.12); }
        .tg-container { max-width: 640px; margin: 0 auto; padding: 16px; }
        .tg-logo { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; }
        @keyframes af-step-pop { 0% { transform: scale(.4); opacity: 0; } 60% { transform: scale(1.15); } 100% { transform: scale(1); opacity: 1; } }
        @keyframes af-step-check { 0% { transform: scale(0) rotate(-30deg); } 70% { transform: scale(1.2) rotate(0); } 100% { transform: scale(1); } }
        @keyframes af-step-glow { 0%, 100% { box-shadow: 0 0 0 0 rgba(29,78,216,.35); } 50% { box-shadow: 0 0 0 6px rgba(29,78,216,.12); } }
        .af-step-atual { animation: af-step-pop .35s ease-out, af-step-glow 1.6s ease-in-out .35s infinite; }
        .af-step-feita { animation: af-step-pop .3s ease-out; }
        .af-step-check { display: inline-block; animation: af-step-check .35s ease-out; }
        .tg-topbar { color: #fff; padding: 0; height: 50px; display: flex; align-items: center; }
        .tg-topbar .tg-container { padding-top: 0; padding-bottom: 0; flex: 1; width: 100%; }
        .tg-topbar a { color: #fff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body class="tg-body">
<header class="telegram-gradient tg-topbar">
    <div class="tg-container d-flex align-items-center justify-content-between">
        <a href="{{ route('agendafacil.acesso') }}">✈ AgendaFácil</a>
        <nav class="d-flex gap-3 small">
            <a href="{{ route('agendafacil.acesso') }}">Início</a>
            @auth
                <a href="{{ route('admin.inicio') }}">Admin</a>
            @endauth
        </nav>
    </div>
</header>

<main class="tg-container">
    @if (session('sucesso'))
        <div class="alert alert-success mt-3">{{ session('sucesso') }}</div>
    @endif
    @if (session('aviso'))
        <div class="alert alert-warning mt-3">{{ session('aviso') }}</div>
    @endif
    @if (session('erro'))
        <div class="alert alert-danger mt-3">{{ session('erro') }}</div>
    @endif

    @yield('conteudo')
</main>

<footer class="text-center text-muted small pb-4">
    AgendaFácil · {{ date('Y') }}
</footer>
<script>
    // Botões de formulário/etapa: spinner + desabilitado ao enviar (evita duplo clique).
    (function () {
        function marcar(form) {
            form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (btn) {
                if (btn.disabled || btn.querySelector('.spinner-border')) return;
                btn.disabled = true;
                btn.classList.add('af-processando');
                var sp = document.createElement('span');
                sp.className = 'spinner-border spinner-border-sm';
                sp.setAttribute('aria-hidden', 'true');
                sp.style.marginRight = '8px';
                btn.prepend(sp);
            });
        }
        function desmarcar() {
            document.querySelectorAll('.af-processando').forEach(function (btn) {
                btn.disabled = false;
                btn.classList.remove('af-processando');
                var sp = btn.querySelector('.spinner-border');
                if (sp) sp.remove();
            });
        }
        document.addEventListener('submit', function (e) {
            if (e.target instanceof HTMLFormElement) marcar(e.target);
        }, true);
        window.addEventListener('pageshow', function (e) { if (e.persisted) desmarcar(); });
    })();
</script>
</body>
</html>
