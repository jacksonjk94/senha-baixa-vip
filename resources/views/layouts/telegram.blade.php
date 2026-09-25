<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'AgendaFácil') · AgendaFácil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --tg-primary: #2AABEE; --tg-dark: #229ED8; --tg-light: #7DD3F8;
            --tg-bg: #F4F9FC; --tg-success: #28C76F; --tg-warning: #FF9F43; --tg-danger: #EA5455;
        }
        body.tg-body { background: var(--tg-bg); color: #333; min-height: 100vh; }
        .telegram-gradient { background: linear-gradient(135deg, #2AABEE 0%, #229ED8 100%); }
        .telegram-gradient-light { background: linear-gradient(135deg, #7DD3F8 0%, #2AABEE 100%); }
        .btn-telegram {
            background: linear-gradient(135deg, #2AABEE 0%, #1B87B8 100%); color: #fff; border: none;
            border-radius: 12px; padding: 10px 24px; font-weight: 600; display: inline-flex;
            align-items: center; justify-content: center; gap: 8px; text-decoration: none;
            cursor: pointer; user-select: none; -webkit-tap-highlight-color: transparent; touch-action: manipulation;
            transition: transform .15s ease, box-shadow .2s ease, filter .2s ease; width: 100%;
        }
        .btn-telegram:hover { transform: translateY(-2px); filter: brightness(1.06); box-shadow: 0 6px 16px rgba(42,171,238,.4); color: #fff; }
        .btn-telegram:active { transform: translateY(0) scale(.98); filter: brightness(.96); box-shadow: 0 2px 6px rgba(42,171,238,.3); }
        .btn-telegram:focus-visible { outline: 3px solid rgba(42,171,238,.45); outline-offset: 2px; }
        .card-telegram { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(42,171,238,.08); overflow: hidden; background: #fff; }
        .card-header-telegram { background: linear-gradient(90deg, #2AABEE, #229ED8); color: #fff; padding: 12px 20px; font-weight: 700; }
        .tg-steps { display: flex; gap: 6px; margin: 16px 0; flex-wrap: wrap; }
        .tg-step { flex: 1; min-width: 80px; min-height: 38px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; padding: 6px 4px; border-radius: 10px; background: #E8F3FA; color: #6B8CA3; font-weight: 600; }
        .tg-step.active { background: linear-gradient(90deg, #2AABEE, #229ED8); color: #fff; }
        .tg-step.done { background: #D9F2E3; color: #1E9E5A; }
        .badge-tg { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge-pendente { background: #FFF3E2; color: #E88B1D; }
        .badge-enviado { background: #DFF7E9; color: #1E9E5A; }
        .badge-erro { background: #FDE6E6; color: #D33; }
        .badge-cancelado { background: #E8EEF3; color: #64748B; }
        .input-telegram { width: 100%; border: 2px solid #D8ECF7; border-radius: 12px; padding: 10px 14px; font-size: 16px; }
        .input-telegram:focus { border-color: var(--tg-primary); outline: none; box-shadow: 0 0 0 3px rgba(42,171,238,.15); }
        .tg-container { max-width: 640px; margin: 0 auto; padding: 16px; }
        .tg-logo { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; }
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
</body>
</html>
