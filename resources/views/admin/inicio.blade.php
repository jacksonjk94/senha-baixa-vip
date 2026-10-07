@extends('layouts.admin')
@section('titulo', 'Visão geral')

@section('conteudo')
<h1 class="h4">📊 Visão geral</h1>
<div class="row g-3 mt-1">
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Total</small><div class="h3" id="statTotal">{{ $total }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Enviados</small><div class="h3 text-success" id="statEnviados">{{ $enviados }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Pendentes</small><div class="h3 text-warning" id="statPendentes">{{ $pendentes }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Com erro</small><div class="h3 text-danger" id="statErros">{{ $erros }}</div></div></div>
</div>

<h2 class="h6 mt-4">Agendamentos recentes</h2>
<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Visitante</th><th>Destino</th><th>Envio</th><th>Status</th></tr></thead>
    <tbody id="recentesBody">
    @forelse ($recentes as $a)
        <tr>
            <td data-label="Visitante">{{ $a->nome_visitante ?? $a->texto }} <small class="text-muted">{{ $a->senha_codigo ?? ('pos. ' . ($a->posicao ?? '—')) }}</small></td>
            <td data-label="Destino">{{ $a->destino_nome }}</td>
            <td data-label="Envio">{{ $a->data_hora_envio?->format('d/m H:i') }}</td>
            <td data-label="Status"><span class="badge-tg badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span></td>
        </tr>
    @empty
        <tr><td colspan="4" class="text-center text-muted">Nenhum registro.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div></div>

<script>
    (function () {
        function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }

        async function atualizar() {
            try {
                var r = await fetch('{{ route('admin.dados') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                var d = await r.json();
                document.getElementById('statTotal').textContent = d.total;
                document.getElementById('statEnviados').textContent = d.enviados;
                document.getElementById('statPendentes').textContent = d.pendentes;
                document.getElementById('statErros').textContent = d.erros;

                var body = document.getElementById('recentesBody');
                if (!d.recentes.length) {
                    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhum registro.</td></tr>';
                    return;
                }
                body.innerHTML = d.recentes.map(function (a) {
                    return '<tr>'
                        + '<td data-label="Visitante">' + esc(a.visitante) + ' <small class="text-muted">' + esc(a.codigo) + '</small></td>'
                        + '<td data-label="Destino">' + esc(a.destino) + '</td>'
                        + '<td data-label="Envio">' + esc(a.envio) + '</td>'
                        + '<td data-label="Status"><span class="badge-tg badge-' + esc(a.status) + '">' + esc(String(a.status).toUpperCase()) + '</span></td>'
                        + '</tr>';
                }).join('');
            } catch (e) { /* ignora falhas de rede */ }
        }

        setInterval(atualizar, 5000);
    })();
</script>
@endsection
