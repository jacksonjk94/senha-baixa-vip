@extends('layouts.admin')
@section('titulo', 'Visão geral')

@section('conteudo')
<h1 class="h4">📊 Visão geral</h1>
<div class="row g-3 mt-1">
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Total</small><div class="h3">{{ $total }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Enviados</small><div class="h3 text-success">{{ $enviados }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Pendentes</small><div class="h3 text-warning">{{ $pendentes }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card p-3"><small class="text-muted">Com erro</small><div class="h3 text-danger">{{ $erros }}</div></div></div>
</div>

<h2 class="h6 mt-4">Agendamentos recentes</h2>
<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Visitante</th><th>Destino</th><th>Envio</th><th>Status</th></tr></thead>
    <tbody>
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
@endsection
