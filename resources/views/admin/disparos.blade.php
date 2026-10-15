@extends('layouts.admin')
@section('titulo', 'Disparos')

@section('conteudo')
<h1 class="h4">✈ Disparos</h1>
<form method="GET" class="row g-2 my-3">
    <div class="col-md-2"><select name="status" class="form-select"><option value="">Todos status</option><option value="pendente" @selected(request('status')==='pendente')>Pendente</option><option value="enviado" @selected(request('status')==='enviado')>Enviado</option><option value="erro" @selected(request('status')==='erro')>Erro</option><option value="cancelado" @selected(request('status')==='cancelado')>Cancelado</option></select></div>
    <div class="col-md-2"><select name="destino" class="form-select"><option value="">Todos destinos</option><option value="grupo" @selected(request('destino')==='grupo')>Grupo</option><option value="pessoa" @selected(request('destino')==='pessoa')>Pessoa</option></select></div>
    <div class="col-md-2"><input type="date" name="de" class="form-control" value="{{ request('de') }}"></div>
    <div class="col-md-2"><input type="date" name="ate" class="form-control" value="{{ request('ate') }}"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>

<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Visitante / Senha</th><th>Destino</th><th>Envio</th><th>Status</th><th>Ações</th></tr></thead>
    <tbody>
    @include('admin.partials.disparos_rows', ['agendamentos' => $agendamentos])
    </tbody>
</table>
</div></div></div>
<div class="mt-3">{{ $agendamentos->links() }}</div>

@include('admin.partials.contexto_modal')

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
