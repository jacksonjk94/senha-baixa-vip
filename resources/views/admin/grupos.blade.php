@extends('layouts.admin')
@section('titulo', 'Grupos')

@section('conteudo')
<h1 class="h4">👥 Grupos Telegram</h1>

<div class="card stat-card my-3"><div class="card-body">
<form method="POST" action="{{ route('admin.grupos.salvar') }}" class="row g-2">
    @csrf
    <div class="col-md-5"><input name="nome" class="form-control" placeholder="Nome do grupo" required></div>
    <div class="col-md-3"><input name="chat_id" class="form-control" placeholder="chat_id (ex.: -100123...)" required></div>
    <div class="col-md-2"><select name="tipo" class="form-select"><option value="group">group</option><option value="supergroup">supergroup</option><option value="channel">channel</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Adicionar</button></div>
</form>
</div></div>

<div class="card stat-card"><div class="card-body p-0">
<table class="table mb-0">
    <thead><tr><th>Nome</th><th>chat_id</th><th>Tipo</th><th>Ativo</th><th>Ação</th></tr></thead>
    <tbody>
    @forelse ($grupos as $g)
        <tr>
            <td>{{ $g->nome }}</td>
            <td><code>{{ $g->chat_id }}</code></td>
            <td>{{ $g->tipo }}</td>
            <td>{{ $g->ativo ? '✅' : '⏸' }}</td>
            <td>
                <form method="POST" action="{{ route('admin.grupos.alternar', $g) }}">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary">{{ $g->ativo ? 'Desativar' : 'Ativar' }}</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted">Nenhum grupo cadastrado.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $grupos->links() }}</div>
@endsection
