<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Admin') · AgendaFácil Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #F4F9FC; }
        .sidebar { background: linear-gradient(180deg, #2AABEE, #229ED8); min-height: 100vh; color: #fff; }
        .sidebar a { color: #fff; text-decoration: none; display: block; padding: 10px 16px; border-radius: 10px; font-weight: 600; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.2); }
        .stat-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(42,171,238,.08); }
        .badge-tg { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge-pendente { background: #FFF3E2; color: #E88B1D; }
        .badge-enviado { background: #DFF7E9; color: #1E9E5A; }
        .badge-erro { background: #FDE6E6; color: #D33; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 sidebar p-3">
            <h2 class="h5">✈ AgendaFácil <small class="fw-normal">admin</small></h2>
            <nav class="mt-3">
                <a href="{{ route('admin.inicio') }}">📊 Visão geral</a>
                <a href="{{ route('admin.cadastros') }}">🧑‍💼 Cadastros</a>
                <a href="{{ route('admin.disparos') }}">✈ Disparos</a>
                <a href="{{ route('admin.grupos') }}">👥 Grupos</a>
                <a href="{{ route('agendafacil.acesso') }}">← Voltar ao fluxo</a>
            </nav>
        </aside>
        <main class="col-md-9 col-lg-10 p-4">
            @if (session('sucesso'))<div class="alert alert-success">{{ session('sucesso') }}</div>@endif
            @if (session('aviso'))<div class="alert alert-warning">{{ session('aviso') }}</div>@endif
            @if (session('erro'))<div class="alert alert-danger">{{ session('erro') }}</div>@endif
            @yield('conteudo')
        </main>
    </div>
</div>
</body>
</html>
