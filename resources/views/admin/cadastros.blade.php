@extends('layouts.admin')
@section('titulo', 'Cadastros')

@section('conteudo')
<h1 class="h4">🧑‍💼 Cadastros</h1>
<form method="GET" class="d-flex gap-2 my-3" style="max-width:420px">
    <input name="busca" class="form-control" placeholder="Buscar por nome ou e-mail..." value="{{ $busca }}">
    <button class="btn btn-primary">Buscar</button>
</form>
<div class="card stat-card"><div class="card-body p-0">
<table class="table mb-0">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Telegram</th><th>Papel</th></tr></thead>
    <tbody>
    @foreach ($usuarios as $u)
        <tr>
            <td>{{ $u->name }}</td>
            <td>{{ $u->email }}</td>
            <td>{{ $u->telegram_username ?? '—' }}</td>
            <td>{{ $u->role }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $usuarios->links() }}</div>
@endsection
