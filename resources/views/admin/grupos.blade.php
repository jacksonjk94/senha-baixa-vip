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
    <div class="col-md-3"><input name="nome" class="form-control" placeholder="Nome do grupo" required></div>
    <div class="col-md-2"><input name="chat_id" class="form-control" placeholder="chat_id (ex.: -100123...)" required></div>
    <div class="col-md-2"><select name="tipo" class="form-select"><option value="group">group</option><option value="supergroup">supergroup</option><option value="channel">channel</option></select></div>
    <div class="col-md-2"><select name="modo" class="form-select" title="Regra de envio"><option value="aberto">Aberto</option><option value="fechado">Fechado</option></select></div>
    <div class="col-md-1"><input name="ordem" type="number" min="0" max="9999" class="form-control" placeholder="ordem" title="Ordem de exibição"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Adicionar</button></div>
    <div class="col-md-3"><input name="horario_abertura" type="time" class="form-control" title="Fechado: abre para agendamento às"></div>
    <div class="col-md-3"><input name="horario_fechamento" type="time" class="form-control" title="Fechado: fecha para agendamento às"></div>
    <div class="col-md-3"><input name="horario_disparo" type="time" class="form-control" title="Fechado: envia em bloco às"></div>
    <div class="col-md-3"><input name="dias_semana" class="form-control" placeholder="Dias 1-7 (vazio = todos)" pattern="[1-7](,[1-7])*" title="Dias da semana ISO 1=seg … 7=dom, ex: 1,2,3,4,5"></div>
</form>
</div></div>

<div class="card stat-card"><div class="card-body p-0"><div class="af-table-wrap">
<table class="table mb-0">
    <thead><tr><th>Nome</th><th>chat_id</th><th>Tipo</th><th>Regra</th><th>Horários</th><th>Ativo</th><th>Ação</th></tr></thead>
    <tbody>
    @forelse ($grupos as $g)
        <tr>
            <td>{{ $g->nome }}</td>
            <td><code>{{ $g->chat_id }}</code></td>
            <td>{{ $g->tipo }}</td>
            <td><span class="badge-tg {{ $g->modo === 'fechado' ? 'badge-pendente' : 'badge-enviado' }}">{{ $g->etiquetaModo() }}</span></td>
            <td><small>{{ $g->horario_abertura?->format('H:i') ?? '—' }} / {{ $g->horario_fechamento?->format('H:i') ?? '—' }} / {{ $g->horario_disparo?->format('H:i') ?? '—' }}</small></td>
            <td>{{ $g->ativo ? '✅' : '⏸' }}</td>
            <td class="text-nowrap">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarGrupo{{ $g->id }}">Editar</button>
                <form method="POST" action="{{ route('admin.grupos.alternar', $g) }}" class="d-inline">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary">{{ $g->ativo ? 'Desativar' : 'Ativar' }}</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted">Nenhum grupo cadastrado.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div></div>
<div class="mt-3">{{ $grupos->links() }}</div>

@foreach ($grupos as $g)
{{-- Dialog: editar regras do grupo --}}
<div class="modal fade" id="modalEditarGrupo{{ $g->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.grupos.atualizar', $g) }}">
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="h5 mb-0">Editar grupo</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body d-grid gap-3">
                <div><label class="form-label fw-semibold">Nome *</label><input name="nome" class="form-control" maxlength="150" value="{{ $g->nome }}" required></div>
                <div><label class="form-label fw-semibold">chat_id *</label><input name="chat_id" class="form-control" maxlength="60" value="{{ $g->chat_id }}" required></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label fw-semibold">Tipo *</label><select name="tipo" class="form-select"><option value="group" @selected($g->tipo === 'group')>group</option><option value="supergroup" @selected($g->tipo === 'supergroup')>supergroup</option><option value="channel" @selected($g->tipo === 'channel')>channel</option></select></div>
                    <div class="col-6"><label class="form-label fw-semibold">Regra *</label><select name="modo" class="form-select"><option value="aberto" @selected($g->modo === 'aberto')>Aberto (horário livre)</option><option value="fechado" @selected($g->modo === 'fechado')>Fechado (rodada fixa)</option></select></div>
                </div>
                <div class="row g-2">
                    <div class="col-4"><label class="form-label fw-semibold">Abre às</label><input name="horario_abertura" type="time" class="form-control" value="{{ $g->horario_abertura?->format('H:i') }}"></div>
                    <div class="col-4"><label class="form-label fw-semibold">Fecha às</label><input name="horario_fechamento" type="time" class="form-control" value="{{ $g->horario_fechamento?->format('H:i') }}"></div>
                    <div class="col-4"><label class="form-label fw-semibold">Envia às</label><input name="horario_disparo" type="time" class="form-control" value="{{ $g->horario_disparo?->format('H:i') }}"></div>
                </div>
                <div><label class="form-label fw-semibold">Dias (1=seg … 7=dom)</label><input name="dias_semana" class="form-control" maxlength="20" placeholder="vazio = todos os dias" value="{{ $g->dias_semana }}"></div>
                <div><label class="form-label fw-semibold">Ordem</label><input name="ordem" type="number" min="0" max="9999" class="form-control" value="{{ $g->ordem }}"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Salvar</button></div>
        </form>
    </div></div>
</div>
@endforeach

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
