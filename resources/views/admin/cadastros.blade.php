@extends('layouts.admin')
@section('titulo', 'Cadastros')

@section('conteudo')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">🧑‍💼 Cadastros <small class="text-muted fs-6">· visitantes</small></h1>
    <span class="ms-auto"></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoVisitante">＋ Novo visitante</button>
</div>

<form method="GET" class="d-flex gap-2 my-3" style="max-width:480px">
    <input name="busca" class="form-control" placeholder="Buscar por nome, e-mail ou telefone..." value="{{ $busca }}">
    <button class="btn btn-primary">Buscar</button>
</form>

<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Telegram</th><th>Ativo</th><th class="text-end">Ações</th></tr></thead>
    <tbody>
    @include('admin.partials.cadastros_rows', ['usuarios' => $usuarios])
    </tbody>
</table>
</div></div></div>
<div class="mt-3">{{ $usuarios->links() }}</div>

{{-- Dialog: novo visitante --}}
<div class="modal fade" id="modalNovoVisitante" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.cadastros.salvar') }}">
            @csrf
            <div class="modal-header"><h2 class="h5 mb-0">Novo visitante</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="name" class="form-control" maxlength="120" required></div>
                <div><label class="form-label fw-semibold">E-mail *</label><input name="email" type="email" class="form-control" maxlength="190" required></div>
                <div><label class="form-label fw-semibold">Telefone *</label><div class="input-group"><span class="input-group-text">+55</span><input name="telefone" class="form-control" maxlength="30" placeholder="(11) 90000-0000" required></div><small class="text-muted">Os 4 últimos dígitos são calculados automaticamente.</small></div>
                <div><label class="form-label fw-semibold">Telegram (usuário)</label><input name="telegram_username" class="form-control" maxlength="60" placeholder="usuario_telegram"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Cadastrar</button></div>
        </form>
    </div></div>
</div>

@foreach ($usuarios as $u)
{{-- Dialog: editar --}}
<div class="modal fade" id="modalEditarVisitante{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.cadastros.atualizar', $u) }}">
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="h5 mb-0">Editar visitante</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="name" class="form-control" maxlength="120" value="{{ $u->name }}" required></div>
                <div><label class="form-label fw-semibold">E-mail *</label><input name="email" type="email" class="form-control" maxlength="190" value="{{ $u->email }}" required></div>
                <div><label class="form-label fw-semibold">Telefone *</label><input name="telefone" class="form-control" maxlength="30" value="{{ $u->telefone }}" required></div>
                <div><label class="form-label fw-semibold">Telegram (usuário)</label><input name="telegram_username" class="form-control" maxlength="60" value="{{ $u->telegram_username }}"></div>
                <div class="form-check"><input type="checkbox" name="active" value="1" class="form-check-input" id="ativo{{ $u->id }}" @checked($u->active)><label class="form-check-label" for="ativo{{ $u->id }}">Cadastro ativo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
        </form>
    </div></div>
</div>
{{-- Dialog: confirmar exclusão --}}
<div class="modal fade" id="modalExcluirVisitante{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.cadastros.excluir', $u) }}">
            @csrf @method('DELETE')
            <div class="modal-header"><h2 class="h5 mb-0">Excluir cadastro</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">Confirma a exclusão do visitante <strong>{{ $u->name }}</strong> ({{ $u->email }})? Os agendamentos vinculados são mantidos.</div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Excluir</button></div>
        </form>
    </div></div>
</div>
@endforeach

<script>
    (function () {
        function ids(doc) {
            return Array.from(doc.querySelectorAll('tbody [data-row-id]')).map(function (r) { return r.dataset.rowId; }).join(',');
        }
        async function refresh() {
            try {
                var r = await fetch(window.location.pathname + window.location.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                var doc = new DOMParser().parseFromString(await r.text(), 'text/html');
                if (ids(doc) !== ids(document)) { location.reload(); return; }
                var novo = doc.querySelector('tbody');
                var atual = document.querySelector('tbody');
                if (novo && atual) atual.innerHTML = novo.innerHTML;
            } catch (e) { /* ignora */ }
        }
        setInterval(refresh, 5000);
    })();
</script>
@endsection
