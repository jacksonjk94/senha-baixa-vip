@extends('layouts.admin')
@section('titulo', 'Grupos')

@section('conteudo')
<h1 class="h4">👥 Grupos Telegram</h1>

<div class="d-flex flex-wrap gap-2 my-3">
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalListarTelegram">📡 Listar do Telegram</button>
</div>

<div class="card stat-card my-3"><div class="card-body">
<form method="POST" action="{{ route('admin.grupos.salvar') }}" class="row g-2">
    @csrf
    <div class="col-md-5"><input name="nome" class="form-control" placeholder="Nome do grupo" required></div>
    <div class="col-md-3"><input name="chat_id" class="form-control" placeholder="chat_id (ex.: -100123...)" required></div>
    <div class="col-md-2"><select name="tipo" class="form-select"><option value="group">group</option><option value="supergroup">supergroup</option><option value="channel">channel</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Adicionar</button></div>
</form>
</div></div>

<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
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
</div></div></div>
<div class="mt-3">{{ $grupos->links() }}</div>

{{-- Dialog: grupos via Bot API + cadastro dos não cadastrados --}}
<div class="modal fade" id="modalListarTelegram" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h2 class="h5 mb-0">Grupos do Telegram (via Bot API)</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            @if (! $telegramConfigurado)
                <div class="alert alert-warning mb-0"><code>TELEGRAM_TOKEN</code> vazio. Preencha o token do BotFather nas variáveis de ambiente.</div>
            @elseif (empty($gruposApi))
                <div class="alert alert-info mb-0">Nenhum grupo com atividade recente. Adicione o bot ao grupo e envie uma mensagem lá (ex.: <code>/start</code>), depois recarregue a página.</div>
            @else
                <div class="af-table-wrap"><table class="table mb-0">
                    <thead><tr><th>Nome</th><th>chat_id</th><th>Tipo</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                    @foreach ($gruposApi as $g)
                        <tr>
                            <td>{{ $g['nome'] }}</td>
                            <td><code>{{ $g['chat_id'] }}</code></td>
                            <td>{{ $g['tipo'] }}</td>
                            <td class="text-end">
                                @if (in_array($g['chat_id'], $chatIdsCadastrados, true))
                                    <small class="text-muted">✅ já cadastrado</small>
                                @else
                                    <form method="POST" action="{{ route('admin.grupos.importar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="chat_id" value="{{ $g['chat_id'] }}">
                                        <input type="hidden" name="nome" value="{{ $g['nome'] }}">
                                        <input type="hidden" name="tipo" value="{{ $g['tipo'] }}">
                                        <button class="btn btn-sm btn-primary">Cadastrar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
    </div></div>
</div>
@endsection
