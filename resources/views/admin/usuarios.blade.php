@extends('layouts.admin')
@section('titulo', 'Usuários')

@section('conteudo')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">🛡️ Usuários <small class="text-muted fs-6">· sistema</small></h1>
    <span class="ms-auto"></span>
    @if (count($atribuiveis) > 0)
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">＋ Novo usuário</button>
    @endif
</div>
<p class="text-muted">Visíveis conforme sua hierarquia (<strong>{{ auth()->user()->role }}</strong>). Abaixo do seu nível: sempre. Mesmo nível: só com <code>can_manage_admins</code>.</p>

<form method="GET" class="d-flex gap-2 my-3" style="max-width:480px">
    <input name="busca" class="form-control" placeholder="Buscar por nome ou e-mail..." value="{{ $busca }}">
    <button class="btn btn-primary">Buscar</button>
</form>

<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Admins?</th><th>Ativo</th><th class="text-end">Ações</th></tr></thead>
    <tbody>
    @forelse ($usuarios as $u)
        <tr>
            <td>{{ $u->name }}</td>
            <td>{{ $u->email }}</td>
            <td><span class="badge-tg badge-info-tg">{{ $u->role }}</span></td>
            <td>{{ $u->can_manage_admins ? '✅' : '—' }}</td>
            <td>{{ $u->active ? '✅' : '⏸' }}</td>
            <td class="text-end text-nowrap">
                @if (\App\Support\PapelHierarquia::podeGerenciar(auth()->user(), $u))
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario{{ $u->id }}">Editar</button>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluirUsuario{{ $u->id }}">Excluir</button>
                @else
                    <small class="text-muted">sem permissão</small>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted">Nenhum usuário visível para o seu papel.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div></div>
<div class="mt-3">{{ $usuarios->links() }}</div>

@if (count($atribuiveis) > 0)
{{-- Dialog: novo usuário --}}
<div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.usuarios.salvar') }}">
            @csrf
            <div class="modal-header"><h2 class="h5 mb-0">Novo usuário de sistema</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="name" class="form-control" maxlength="120" required></div>
                <div><label class="form-label fw-semibold">E-mail *</label><input name="email" type="email" class="form-control" maxlength="190" required></div>
                <div><label class="form-label fw-semibold">Senha * (mín. 8)</label><input name="password" type="password" class="form-control" minlength="8" required></div>
                <div><label class="form-label fw-semibold">Papel *</label>
                    <select name="role" class="form-select" required>
                        @foreach ($atribuiveis as $papel)<option value="{{ $papel }}">{{ $papel }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label fw-semibold">Telefone</label><div class="input-group"><span class="input-group-text">+55</span><input name="telefone" class="form-control" maxlength="30" placeholder="(13) 98138-8690"></div></div>
                @if ($podeGerenciarPares)
                    <div class="form-check"><input type="checkbox" name="can_manage_admins" value="1" class="form-check-input" id="novoCanAdmin"><label class="form-check-label" for="novoCanAdmin">Pode gerenciar admins (<code>can_manage_admins</code>)</label></div>
                @endif
                <div class="form-check"><input type="checkbox" name="active" value="1" class="form-check-input" id="novoAtivo" checked><label class="form-check-label" for="novoAtivo">Ativo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Criar</button></div>
        </form>
    </div></div>
</div>
@endif

@foreach ($usuarios as $u)
@if (\App\Support\PapelHierarquia::podeGerenciar(auth()->user(), $u))
{{-- Dialog: editar --}}
<div class="modal fade" id="modalEditarUsuario{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.usuarios.atualizar', $u) }}">
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="h5 mb-0">Editar usuário</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="name" class="form-control" maxlength="120" value="{{ $u->name }}" required></div>
                <div><label class="form-label fw-semibold">E-mail *</label><input name="email" type="email" class="form-control" maxlength="190" value="{{ $u->email }}" required></div>
                <div><label class="form-label fw-semibold">Nova senha (vazio = manter)</label><input name="password" type="password" class="form-control" minlength="8"></div>
                <div><label class="form-label fw-semibold">Papel *</label>
                    <select name="role" class="form-select" required>
                        @foreach ($atribuiveis as $papel)<option value="{{ $papel }}" @selected($u->role === $papel)>{{ $papel }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label fw-semibold">Telefone</label><input name="telefone" class="form-control" maxlength="30" value="{{ $u->telefone }}"></div>
                @if ($podeGerenciarPares)
                    <div class="form-check"><input type="checkbox" name="can_manage_admins" value="1" class="form-check-input" id="canAdmin{{ $u->id }}" @checked($u->can_manage_admins)><label class="form-check-label" for="canAdmin{{ $u->id }}">Pode gerenciar admins</label></div>
                @endif
                <div class="form-check"><input type="checkbox" name="active" value="1" class="form-check-input" id="ativoU{{ $u->id }}" @checked($u->active)><label class="form-check-label" for="ativoU{{ $u->id }}">Ativo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
        </form>
    </div></div>
</div>
{{-- Dialog: confirmar exclusão --}}
<div class="modal fade" id="modalExcluirUsuario{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.usuarios.excluir', $u) }}">
            @csrf @method('DELETE')
            <div class="modal-header"><h2 class="h5 mb-0">Excluir usuário</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">Confirma a exclusão de <strong>{{ $u->name }}</strong> ({{ $u->email }}, papel <strong>{{ $u->role }}</strong>)?</div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Excluir</button></div>
        </form>
    </div></div>
</div>
@endif
@endforeach
@endsection
