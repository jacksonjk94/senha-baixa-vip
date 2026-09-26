<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Admin') · AgendaFácil Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // Tema antes da pintura (evita flash).
        (function () {
            try {
                var t = localStorage.getItem('af-admin-theme') || 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <style>
        :root, [data-theme="light"] {
            --af-bg: #F2F6FA;
            --af-surface: #FFFFFF;
            --af-surface-2: #F8FBFD;
            --af-text: #1B2A3A;
            --af-muted: #64748B;
            --af-border: #E2EAF1;
            --af-primary: #229ED8;
            --af-primary-strong: #1B87B8;
            --af-primary-soft: rgba(34, 158, 216, .12);
            --af-sidebar-bg: #FFFFFF;
            --af-sidebar-text: #33465A;
            --af-topbar-bg: rgba(255, 255, 255, .92);
            --af-shadow: 0 6px 24px rgba(27, 42, 58, .08);
            --af-scroll-thumb: rgba(27, 42, 58, .28);
            --af-success-bg: #DFF7E9; --af-success-tx: #1E9E5A;
            --af-warn-bg: #FFF3E2; --af-warn-tx: #C97A12;
            --af-danger-bg: #FDE6E6; --af-danger-tx: #C93A3A;
        }
        [data-theme="dark"] {
            --af-bg: #0F1722;
            --af-surface: #182536;
            --af-surface-2: #1E2E44;
            --af-text: #E8EEF5;
            --af-muted: #93A3B8;
            --af-border: #26374C;
            --af-primary: #2AABEE;
            --af-primary-strong: #229ED8;
            --af-primary-soft: rgba(42, 171, 238, .16);
            --af-sidebar-bg: #14202F;
            --af-sidebar-text: #C9D6E4;
            --af-topbar-bg: rgba(20, 32, 47, .92);
            --af-shadow: 0 8px 28px rgba(0, 0, 0, .35);
            --af-scroll-thumb: rgba(232, 238, 245, .28);
            --af-success-bg: rgba(30, 158, 90, .18); --af-success-tx: #4ADE80;
            --af-warn-bg: rgba(201, 122, 18, .18); --af-warn-tx: #FBBF24;
            --af-danger-bg: rgba(201, 58, 58, .18); --af-danger-tx: #F87171;
        }
        * { -webkit-tap-highlight-color: transparent; }
        body { background: var(--af-bg); color: var(--af-text); }
        .af-shell { display: flex; min-height: 100vh; }

        /* ── Sidebar ─────────────────────────────── */
        .af-sidebar {
            width: 264px; flex: 0 0 264px;
            background: var(--af-sidebar-bg); color: var(--af-sidebar-text);
            border-right: 1px solid var(--af-border);
            position: sticky; top: 0; height: 100vh;
            display: flex; flex-direction: column;
            transition: margin-left .25s ease;
            z-index: 1040;
        }
        .af-brand { display: flex; align-items: center; gap: 10px; padding: 18px 18px 14px; }
        .af-brand-mark {
            width: 38px; height: 38px; border-radius: 12px; flex: 0 0 38px;
            background: linear-gradient(135deg, #2AABEE, #1B87B8);
            color: #fff; font-weight: 800; font-size: 20px;
            display: flex; align-items: center; justify-content: center;
        }
        .af-brand-name { font-weight: 800; font-size: 17px; color: var(--af-text); line-height: 1.1; }
        .af-brand-sub { font-size: 11px; color: var(--af-muted); letter-spacing: .12em; text-transform: uppercase; }
        .af-nav { padding: 6px 12px; overflow-y: auto; flex: 1; overscroll-behavior: contain; scrollbar-gutter: stable; }
        /* ── Scrollbar estilo Android: fina, pílula, aparece ao rolar ── */
        .af-nav { scrollbar-width: thin; scrollbar-color: var(--af-scroll-thumb) transparent; }
        .af-nav::-webkit-scrollbar { width: 6px; }
        .af-nav::-webkit-scrollbar-track { background: transparent; }
        .af-nav::-webkit-scrollbar-thumb { background: transparent; border-radius: 999px; }
        .af-nav:hover::-webkit-scrollbar-thumb, .af-nav:active::-webkit-scrollbar-thumb { background: var(--af-scroll-thumb); }
        .af-nav::-webkit-scrollbar-thumb:hover { background: var(--af-primary); }
        .af-table-wrap::-webkit-scrollbar { height: 6px; }
        .af-table-wrap::-webkit-scrollbar-track { background: transparent; }
        .af-table-wrap::-webkit-scrollbar-thumb { background: var(--af-scroll-thumb); border-radius: 999px; }
        .af-nav-title { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--af-muted); padding: 14px 10px 6px; }
        .af-nav a {
            display: flex; align-items: center; gap: 10px;
            color: var(--af-sidebar-text); text-decoration: none;
            padding: 10px 12px; border-radius: 16px; font-weight: 600; font-size: 14.5px;
            border: 1px solid transparent;
            transition: background .18s ease, transform .12s ease, box-shadow .18s ease;
        }
        .af-nav a .af-ico { width: 22px; text-align: center; font-size: 17px; }
        .af-nav a:hover { background: var(--af-primary-soft); }
        .af-nav a:active { transform: scale(.98); }
        .af-nav a.active {
            background: linear-gradient(135deg, #2AABEE, #1B87B8);
            color: #fff; border-color: transparent;
            box-shadow: 0 4px 14px rgba(42, 171, 238, .4);
        }
        .af-sidebar-foot { padding: 14px 18px; border-top: 1px solid var(--af-border); font-size: 12px; color: var(--af-muted); }

        /* ── Menu de perfil (rodapé da sidebar) ── */
        .af-profile { padding: 12px 14px 6px; border-top: 1px solid var(--af-border); }
        .af-profile-btn {
            width: 100%; display: flex; align-items: center; gap: 10px;
            background: transparent; border: 1px solid transparent; border-radius: 12px;
            padding: 8px 10px; cursor: pointer; color: var(--af-text); text-align: left;
        }
        .af-profile-btn:hover { background: var(--af-primary-soft); }
        .af-avatar {
            width: 38px; height: 38px; border-radius: 50%; flex: 0 0 38px;
            background: linear-gradient(135deg, #2AABEE, #1B87B8);
            color: #fff; font-weight: 800; font-size: 17px;
            display: inline-flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .af-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .af-profile-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .af-profile-name { font-weight: 800; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .af-profile-email { font-size: 11.5px; color: var(--af-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .af-profile-caret { color: var(--af-muted); font-size: 12px; }
        .af-profile-menu {
            background: var(--af-surface); color: var(--af-text);
            border: 1px solid var(--af-border); border-radius: 14px;
            box-shadow: var(--af-shadow); min-width: 220px; padding: 6px;
        }
        .af-profile-menu .dropdown-header { color: var(--af-muted); font-size: 12px; }
        .af-profile-menu .dropdown-item {
            color: var(--af-text); border-radius: 10px; font-weight: 600; font-size: 14px;
            padding: 9px 12px; background: transparent; border: 0; width: 100%; text-align: left; cursor: pointer;
        }
        .af-profile-menu .dropdown-item:hover { background: var(--af-primary-soft); color: var(--af-text); }
        .af-profile-menu .dropdown-divider { border-color: var(--af-border); }

        /* Desktop colapsada */
        body.af-collapsed .af-sidebar { margin-left: -264px; }

        /* ── Conteúdo / topbar ───────────────────── */
        .af-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .af-topbar {
            position: sticky; top: 0; z-index: 1030;
            background: var(--af-topbar-bg); backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--af-border);
            display: flex; align-items: center; gap: 12px;
            padding: 10px 20px;
        }
        .af-icon-btn {
            width: 40px; height: 40px; border-radius: 14px; flex: 0 0 40px;
            border: 1px solid var(--af-border); background: var(--af-surface); color: var(--af-text);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 19px; cursor: pointer;
            transition: background .18s ease, transform .12s ease;
        }
        .af-icon-btn:hover { background: var(--af-primary-soft); }
        .af-icon-btn:active { transform: scale(.94); }
        .af-page-title { font-size: 17px; font-weight: 800; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .af-topbar-spacer { flex: 1; }
        .af-user { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--af-muted); }
        .af-role { font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 999px; background: var(--af-primary-soft); color: var(--af-primary-strong); }
        [data-theme="dark"] .af-role { color: var(--af-primary); }
        .af-content { padding: 24px; max-width: 1200px; width: 100%; margin: 0 auto; }

        /* ── Componentes (toque Material elegante) ── */
        .stat-card { border: 1px solid var(--af-border); border-radius: 20px; box-shadow: var(--af-shadow); background: var(--af-surface); color: var(--af-text); }
        .card.stat-card { background: var(--af-surface); }
        .table { color: var(--af-text); }
        .table thead th { color: var(--af-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid var(--af-border); }
        .table td, .table th { border-bottom: 1px solid var(--af-border); }
        [data-theme="dark"] .table { --bs-table-bg: transparent; --bs-table-color: var(--af-text); --bs-table-border-color: var(--af-border); }
        .form-control, .form-select {
            background: var(--af-surface); border-color: var(--af-border); color: var(--af-text);
        }
        .form-control:focus, .form-select:focus {
            background: var(--af-surface); color: var(--af-text);
            border-color: var(--af-primary); box-shadow: 0 0 0 .2rem var(--af-primary-soft);
        }
        [data-theme="dark"] .form-control::placeholder { color: var(--af-muted); }
        .btn-primary {
            background: linear-gradient(135deg, #2AABEE, #1B87B8);
            border-color: transparent; border-radius: 14px;
            box-shadow: 0 4px 14px rgba(42, 171, 238, .35);
            transition: transform .12s ease, box-shadow .18s ease, filter .18s ease;
        }
        .btn-primary:hover { background: linear-gradient(135deg, #2AABEE, #1B87B8); border-color: transparent; filter: brightness(1.07); box-shadow: 0 6px 18px rgba(42, 171, 238, .45); }
        .btn-primary:active { transform: scale(.98); }
        .btn-outline-primary { color: var(--af-primary-strong); border-color: var(--af-primary); }
        .btn-outline-primary:hover { background: var(--af-primary); border-color: var(--af-primary); }
        [data-theme="dark"] .btn-outline-primary { color: var(--af-primary); }
        [data-theme="dark"] .btn-outline-secondary { color: var(--af-text); border-color: var(--af-border); }
        .modal-content { background: var(--af-surface); color: var(--af-text); border: 1px solid var(--af-border); border-radius: 18px; }
        .modal-header, .modal-footer { border-color: var(--af-border); }
        .btn-close { filter: none; }
        [data-theme="dark"] .btn-close { filter: invert(1); }
        .badge-tg { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge-pendente { background: var(--af-warn-bg); color: var(--af-warn-tx); }
        .badge-enviado { background: var(--af-success-bg); color: var(--af-success-tx); }
        .badge-erro { background: var(--af-danger-bg); color: var(--af-danger-tx); }
        .badge-cancelado { background: var(--af-border); color: var(--af-muted); }
        .badge-info-tg { background: var(--af-primary-soft); color: var(--af-primary-strong); }
        [data-theme="dark"] .badge-info-tg { color: var(--af-primary); }
        .alert { border: 1px solid var(--af-border); }
        [data-theme="dark"] .alert-success { background: var(--af-success-bg); color: var(--af-success-tx); }
        [data-theme="dark"] .alert-warning { background: var(--af-warn-bg); color: var(--af-warn-tx); }
        [data-theme="dark"] .alert-danger { background: var(--af-danger-bg); color: var(--af-danger-tx); }
        [data-theme="dark"] .alert-info { background: var(--af-primary-soft); color: var(--af-text); }
        .af-table-wrap { overflow-x: auto; border-radius: 0 0 16px 16px; }
        .pagination { --bs-pagination-bg: var(--af-surface); --bs-pagination-border-color: var(--af-border); --bs-pagination-color: var(--af-text); }
        code { color: var(--af-primary-strong); }
        [data-theme="dark"] code { color: var(--af-primary); }
        .text-muted { color: var(--af-muted) !important; }

        .af-backdrop {
            position: fixed; inset: 0; background: rgba(10, 20, 30, .5);
            opacity: 0; pointer-events: none; transition: opacity .25s ease; z-index: 1035;
        }

        /* ── Mobile: sidebar off-canvas ──────────── */
        @media (max-width: 991.98px) {            .af-sidebar {
                position: fixed; left: 0; top: 0; bottom: 0;
                transform: translateX(-105%);
                transition: transform .25s ease;
                box-shadow: var(--af-shadow);
            }
            body.af-open .af-sidebar { transform: translateX(0); }
            body.af-open .af-backdrop { opacity: 1; pointer-events: auto; }
            body.af-collapsed .af-sidebar { margin-left: 0; }
            .af-content { padding: 16px; }
            .af-user-name { display: none; }
        }

        /* ── Tabelas viram cards elegantes no mobile ──
           Desktop (>=768px): tabela normal. Mobile: cada linha vira um
           card com rótulos vindos de `data-label` nas <td>. */
        @media (max-width: 767.98px) {
            .af-table-wrap { overflow: visible; }
            .af-table-wrap table.table { display: block; margin-bottom: 0; }
            .af-table-wrap thead { display: none; }
            .af-table-wrap tbody { display: grid; gap: 12px; }
            .af-table-wrap tbody tr {
                display: grid;
                background: var(--af-surface-2);
                border: 1px solid var(--af-border);
                border-radius: 16px;
                padding: 4px 14px;
                box-shadow: var(--af-shadow);
            }
            .af-table-wrap tbody td {
                display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
                border: 0; padding: 8px 0; text-align: right; min-width: 0;
            }
            .af-table-wrap tbody td + td { border-top: 1px dashed var(--af-border); }
            .af-table-wrap tbody td[data-label]::before {
                content: attr(data-label);
                font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
                color: var(--af-muted); text-align: left; flex: 0 0 auto;
            }
            .af-table-wrap tbody td[colspan] { display: block; text-align: center; }
            .af-table-wrap tbody td.af-cell-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: stretch; }
            .af-table-wrap tbody td.af-cell-actions > *,
            .af-table-wrap tbody td.af-cell-actions form > .btn,
            .af-table-wrap tbody td.af-cell-actions > .btn { flex: 1 1 40%; }
            .af-table-wrap tbody td.af-cell-actions .btn { width: 100%; }
            .af-table-wrap tbody td.af-cell-actions form { display: contents; }
            .af-table-wrap tbody td.af-cell-actions .form-select { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
<div class="af-shell">
    <div class="af-backdrop" id="afBackdrop"></div>

    <aside class="af-sidebar" id="afSidebar" aria-label="Navegação administrativa">
        <div class="af-brand">
            <span class="af-brand-mark">✈</span>
            <span>
                <span class="af-brand-name">AgendaFácil</span><br>
                <span class="af-brand-sub">Admin</span>
            </span>
        </div>
        <nav class="af-nav">
            <div class="af-nav-title">Principal</div>
            <a href="{{ route('admin.inicio') }}" class="{{ request()->routeIs('admin.inicio') ? 'active' : '' }}"><span class="af-ico">📊</span>Visão geral</a>
            <a href="{{ route('admin.cadastros') }}" class="{{ request()->routeIs('admin.cadastros*') ? 'active' : '' }}"><span class="af-ico">🧑‍💼</span>Cadastros</a>
            <a href="{{ route('admin.usuarios') }}" class="{{ request()->routeIs('admin.usuarios*') ? 'active' : '' }}"><span class="af-ico">🛡️</span>Usuários</a>
            <a href="{{ route('admin.disparos') }}" class="{{ request()->routeIs('admin.disparos*') ? 'active' : '' }}"><span class="af-ico">✈</span>Disparos</a>
            <a href="{{ route('admin.grupos') }}" class="{{ request()->routeIs('admin.grupos*') ? 'active' : '' }}"><span class="af-ico">👥</span>Grupos</a>
            <div class="af-nav-title">Sistema</div>
            <a href="{{ route('admin.configuracoes') }}" class="{{ request()->routeIs('admin.configuracoes*') ? 'active' : '' }}"><span class="af-ico">⚙️</span>Config. disparos</a>
            <a href="{{ route('admin.telegram') }}" class="{{ request()->routeIs('admin.telegram*') ? 'active' : '' }}"><span class="af-ico">🤖</span>Teste Telegram</a>
            <a href="{{ route('admin.telegram-bots') }}" class="{{ request()->routeIs('admin.telegram-bots*') ? 'active' : '' }}"><span class="af-ico">📡</span>Bots Telegram</a>
            <a href="{{ route('ajuda.bot-telegram') }}" target="_blank" rel="noopener"><span class="af-ico">📖</span>Ajuda: Bot Telegram</a>
            <a href="{{ route('agendafacil.acesso') }}"><span class="af-ico">←</span>Voltar ao fluxo</a>
        </nav>
        <div class="af-profile dropup">
            <button type="button" class="af-profile-btn" data-bs-toggle="dropdown" data-bs-offset="0,8" aria-expanded="false" aria-label="Menu de perfil">
                <span class="af-avatar">
                    @if (auth()->user()?->profile_photo_path)
                        <img src="{{ auth()->user()->profile_photo_url }}" alt="Foto de perfil">
                    @else
                        {{ mb_strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    @endif
                </span>
                <span class="af-profile-meta">
                    <span class="af-profile-name">{{ auth()->user()?->name }}</span>
                    <span class="af-profile-email">{{ auth()->user()?->email }}</span>
                </span>
                <span class="af-profile-caret">▴</span>
            </button>
            <ul class="dropdown-menu af-profile-menu">
                <li><h6 class="dropdown-header">{{ auth()->user()?->name }}<br><span class="af-role">{{ auth()->user()?->role }}</span></h6></li>
                <li><a class="dropdown-item" href="{{ route('profile.show') }}">👤 Meu perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">🚪 Sair</button>
                    </form>
                </li>
            </ul>
        </div>
        <div class="af-sidebar-foot">AgendaFácil</div>
    </aside>

    <div class="af-main">
        <header class="af-topbar">
            <button class="af-icon-btn" id="afSidebarToggle" title="Menu (sidebar)" aria-label="Alternar sidebar">☰</button>
            <h1 class="af-page-title">@yield('titulo', 'Admin')</h1>
            <span class="af-topbar-spacer"></span>
            <span class="af-user">
                <span class="af-user-name">{{ auth()->user()?->name }}</span>
                <span class="af-role">{{ auth()->user()?->role }}</span>
            </span>
            <button class="af-icon-btn" id="afThemeToggle" title="Alternar tema claro/escuro" aria-label="Alternar tema">🌙</button>
        </header>

        <main class="af-content">
            @if (session('sucesso'))<div class="alert alert-success">{{ session('sucesso') }}</div>@endif
            @if (session('aviso'))<div class="alert alert-warning">{{ session('aviso') }}</div>@endif
            @if (session('erro'))<div class="alert alert-danger">{{ session('erro') }}</div>@endif
            @yield('conteudo')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        var body = document.body;
        var isMobile = function () { return window.matchMedia('(max-width: 991.98px)').matches; };

        // Restaura sidebar recolhida no desktop.
        try {
            if (localStorage.getItem('af-sidebar-collapsed') === '1' && !isMobile()) {
                body.classList.add('af-collapsed');
            }
        } catch (e) {}

        document.getElementById('afSidebarToggle').addEventListener('click', function () {
            if (isMobile()) {
                body.classList.toggle('af-open');
            } else {
                body.classList.toggle('af-collapsed');
                try { localStorage.setItem('af-sidebar-collapsed', body.classList.contains('af-collapsed') ? '1' : '0'); } catch (e) {}
            }
        });
        document.getElementById('afBackdrop').addEventListener('click', function () {
            body.classList.remove('af-open');
        });
        // Fecha a sidebar mobile ao navegar.
        document.querySelectorAll('.af-nav a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (isMobile()) body.classList.remove('af-open');
            });
        });

        // Tema claro/escuro.
        var themeBtn = document.getElementById('afThemeToggle');
        var syncIcon = function () {
            themeBtn.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
        };
        syncIcon();
        themeBtn.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('af-admin-theme', next); } catch (e) {}
            syncIcon();
        });
    })();
</script>
</body>
</html>
