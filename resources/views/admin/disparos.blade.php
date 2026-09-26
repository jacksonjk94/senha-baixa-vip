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
    @forelse ($agendamentos as $a)
        <tr>
            <td data-label="Visitante">{{ $a->nome_visitante ?? $a->texto }}<br><small class="text-muted">{{ $a->senha_codigo ?? ('posição ' . ($a->posicao ?? '—')) }}</small>
                @if ($a->mensagem_erro)<br><small class="text-danger">{{ \Illuminate\Support\Str::limit($a->mensagem_erro, 80) }}</small>@endif
            </td>
            <td data-label="Destino">{{ $a->destino_nome }} <small class="text-muted">({{ $a->destino_tipo }})</small></td>
            <td data-label="Envio">{{ $a->data_hora_envio?->format('d/m/Y H:i') }}</td>
            <td data-label="Status"><span class="badge-tg badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span></td>
            <td class="d-flex gap-1 af-cell-actions">
                @if (in_array($a->status, ['pendente', 'erro']))
                    <form method="POST" action="{{ route('admin.reenviar', $a) }}">@csrf<button class="btn btn-sm btn-outline-primary">↻ Reenviar</button></form>
                    <form method="POST" action="{{ route('admin.cancelar', $a) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancelar este agendamento?')">✕ Cancelar</button></form>
                @endif
                <form method="POST" action="{{ route('admin.status', $a) }}">@csrf @method('PATCH')
                    <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                        <option value="pendente" @selected($a->status==='pendente')>Pendente</option>
                        <option value="enviado" @selected($a->status==='enviado')>Enviado</option>
                        <option value="erro" @selected($a->status==='erro')>Erro</option>
                        <option value="cancelado" @selected($a->status==='cancelado')>Cancelado</option>
                    </select>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted">Nenhum disparo encontrado.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div></div>
<div class="mt-3">{{ $agendamentos->links() }}</div>
@endsection
