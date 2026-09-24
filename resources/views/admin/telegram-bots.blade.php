@extends('layouts.admin')
@section('titulo', 'Bots Telegram')

@section('conteudo')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">🤖 Bots Telegram</h1>
    <span class="ms-auto"></span>
    <form method="POST" action="{{ route('admin.telegram-bots.importar-env') }}" class="d-inline">
        @csrf
        <button class="btn btn-outline-primary" title="Cria/atualiza a partir de TELEGRAM_BOT_NAME/TELEGRAM_TOKEN do ambiente e valida">⇪ Importar do .env</button>
    </form>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoBot">＋ Novo bot</button>
</div>
<p class="text-muted">O bot <strong>padrão ativo</strong> é usado no widget, no fluxo e nos disparos. Sem padrão, vale o <code>.env</code> (<code>{{ $envUsername ?: '—' }}</code>{{ $envHasToken ? ', com token' : ', sem token' }}). Nunca exibimos tokens na tela.</p>

<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Nome</th><th>Username</th><th>Padrão</th><th>Ativo</th><th class="text-end">Ações</th></tr></thead>
    <tbody>
    @forelse ($bots as $b)
        <tr>
            <td>{{ $b->name }}</td>
            <td><code>{{ '@' . $b->username }}</code></td>
            <td>{{ $b->is_default ? '⭐' : '—' }}</td>
            <td>{{ $b->is_active ? '✅' : '⏸' }}</td>
            <td class="text-end text-nowrap">
                @if (! $b->is_default && $b->is_active)
                    <form method="POST" action="{{ route('admin.telegram-bots.padrao', $b) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary">Padrão</button></form>
                @endif
                <form method="POST" action="{{ route('admin.telegram-bots.alternar', $b) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $b->is_active ? 'Desativar' : 'Ativar' }}</button></form>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarBot{{ $b->id }}">Editar</button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluirBot{{ $b->id }}">Excluir</button>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted">Nenhum bot cadastrado. Importe do .env ou cadastre um novo.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div></div>
<div class="mt-3">{{ $bots->links() }}</div>

{{-- Dialog: novo --}}
<div class="modal fade" id="modalNovoBot" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.telegram-bots.salvar') }}">
            @csrf
            <div class="modal-header"><h2 class="h5 mb-0">Novo bot</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="name" class="form-control" maxlength="120" placeholder="Principal" required></div>
                <div><label class="form-label fw-semibold">Username * (sem @)</label><input name="username" class="form-control" maxlength="60" placeholder="AgendaFacil26_bot" required></div>
                <div><label class="form-label fw-semibold">Token * (BotFather)</label><input name="token" class="form-control" maxlength="500" placeholder="123456:ABC-DEF..." required></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="novoBotAtivo" checked><label class="form-check-label" for="novoBotAtivo">Ativo</label></div>
                <div class="form-check"><input type="checkbox" name="is_default" value="1" class="form-check-input" id="novoBotPadrao"><label class="form-check-label" for="novoBotPadrao">Definir como padrão</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Cadastrar</button></div>
        </form>
    </div></div>
</div>

@foreach ($bots as $b)
{{-- Dialog: editar (token opcional: vazio mantém) --}}
<div class="modal fade" id="modalEditarBot{{ $b->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.telegram-bots.atualizar', $b) }}">
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="h5 mb-0">Editar bot</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="name" class="form-control" maxlength="120" value="{{ $b->name }}" required></div>
                <div><label class="form-label fw-semibold">Username *</label><input name="username" class="form-control" maxlength="60" value="{{ $b->username }}" required></div>
                <div><label class="form-label fw-semibold">Novo token (vazio = manter)</label><input name="token" class="form-control" maxlength="500" placeholder="••••••••"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="botAtivo{{ $b->id }}" @checked($b->is_active)><label class="form-check-label" for="botAtivo{{ $b->id }}">Ativo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
        </form>
    </div></div>
</div>
{{-- Dialog: excluir --}}
<div class="modal fade" id="modalExcluirBot{{ $b->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.telegram-bots.excluir', $b) }}">
            @csrf @method('DELETE')
            <div class="modal-header"><h2 class="h5 mb-0">Excluir bot</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">Confirma a exclusão do bot <strong>{{ $b->name }}</strong> (<code>{{ '@' . $b->username }}</code>)? O token é apagado junto.</div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Excluir</button></div>
        </form>
    </div></div>
</div>
@endforeach
@endsection
