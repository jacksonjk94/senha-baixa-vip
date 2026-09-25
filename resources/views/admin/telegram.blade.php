@extends('layouts.admin')
@section('titulo', 'Teste Telegram')

@section('conteudo')
<h1 class="h4 mb-3">🤖 Teste do Telegram</h1>

<form method="GET" action="{{ route('admin.telegram') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3">
    <div style="max-width:320px">
        <label class="form-label fw-semibold">Bot para testar</label>
        <select name="bot_id" class="form-select" onchange="this.form.submit()">
            <option value="">Padrão ({{ $bot ?: 'env' }})</option>
            @foreach ($bots as $item)
                <option value="{{ $item->id }}" @selected($botSelecionado?->id === $item->id)>{{ '@' . $item->username }} — {{ $item->name }}</option>
            @endforeach
        </select>
    </div>
    <a href="{{ route('admin.telegram-bots') }}" class="btn btn-outline-primary">Gerenciar bots</a>
</form>

<div class="card stat-card mb-3"><div class="card-body">
    <h2 class="h6">Conexão (getMe)</h2>
    @if (! $configurado)
        <div class="alert alert-warning mb-0"><code>TELEGRAM_TOKEN</code> vazio. Preencha o token do BotFather nas variáveis de ambiente.</div>
    @elseif ($status['ok'])
        <div class="alert alert-success mb-0">Conectado ao bot <strong>{{ $status['nome'] }}</strong> (widget: <strong>{{ $bot }}</strong>).</div>
    @else
        <div class="alert alert-danger mb-0">Token rejeitado: {{ $status['erro'] }}. Confira o <code>TELEGRAM_TOKEN</code>.</div>
    @endif
</div></div>

<div class="card stat-card mb-3"><div class="card-body">
    <h2 class="h6">Chats conhecidos (chat_id)</h2>
    @if ($chats->isEmpty())
        <p class="text-muted mb-0">Nenhum chat cadastrado. Cadastre em <a href="{{ route('admin.grupos') }}">Grupos</a> (a conexão do bot/grupos é feita aqui no admin).</p>
    @else
        <div class="af-table-wrap"><table class="table mb-0">
            <thead><tr><th>Nome</th><th>chat_id</th><th>Tipo</th><th>Ativo</th><th class="text-end">Usar em</th></tr></thead>
            <tbody>
            @foreach ($chats as $chat)
                <tr>
                    <td>{{ $chat->nome }}</td>
                    <td><code>{{ $chat->chat_id }}</code></td>
                    <td>{{ $chat->tipo }}</td>
                    <td>{{ $chat->ativo ? '✅' : '⏸' }}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-preencher="testeChatId" data-chat="{{ $chat->chat_id }}">Teste</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-preencher="codigoChatId" data-chat="{{ $chat->chat_id }}">Código</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div></div>

<div class="card stat-card mb-3"><div class="card-body">
    <h2 class="h6">Grupos do Telegram (via Bot API, automático)</h2>
    @if (! $configurado)
        <p class="text-muted mb-0">Configure o <code>TELEGRAM_TOKEN</code> para listar os grupos onde o bot teve atividade.</p>
    @elseif (empty($gruposApi))
        <p class="text-muted mb-0">Nenhum grupo com atividade recente. Adicione o bot ao grupo e envie uma mensagem lá (ex.: <code>/start</code>), depois recarregue.</p>
    @else
        <div class="af-table-wrap"><table class="table mb-0">
            <thead><tr><th>Nome</th><th>chat_id</th><th>Tipo</th><th>Situação</th><th class="text-end">Usar em</th></tr></thead>
            <tbody>
            @foreach ($gruposApi as $g)
                <tr>
                    <td>{{ $g['nome'] }}</td>
                    <td><code>{{ $g['chat_id'] }}</code></td>
                    <td>{{ $g['tipo'] }}</td>
                    <td>{{ in_array($g['chat_id'], $chatIdsCadastrados, true) ? '✅ cadastrado' : '⏳ não cadastrado' }}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-preencher="testeChatId" data-chat="{{ $g['chat_id'] }}">Teste</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-preencher="codigoChatId" data-chat="{{ $g['chat_id'] }}">Código</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div></div>

<div class="row g-3">
    <div class="col-lg-6"><div class="card stat-card h-100"><div class="card-body">
        <h2 class="h6">1 · Enviar mensagem de teste</h2>
        <form method="POST" action="{{ route('admin.telegram.enviar') }}" class="d-grid gap-3">
            @csrf
            <input type="hidden" name="bot_id" value="{{ $botSelecionado?->id }}">
            <div><label class="form-label fw-semibold">chat_id *</label><input name="chat_id" id="testeChatId" class="form-control" placeholder="-100123... (grupo) ou id do usuário" required></div>
            <div><label class="form-label fw-semibold">Mensagem *</label><textarea name="mensagem" class="form-control" rows="3" maxlength="1000" required>Teste AgendaFácil 🤖 — se você leu isto, o bot está enviando.</textarea></div>
            <div><button class="btn btn-primary">Enviar teste</button></div>
        </form>
    </div></div></div>

    <div class="col-lg-6"><div class="card stat-card h-100"><div class="card-body">
        <h2 class="h6">2 · Verificação por código</h2>
        <form method="POST" action="{{ route('admin.telegram.codigo') }}" class="d-grid gap-3">
            @csrf
            <input type="hidden" name="bot_id" value="{{ $botSelecionado?->id }}">
            <div><label class="form-label fw-semibold">chat_id *</label><input name="chat_id" id="codigoChatId" class="form-control" placeholder="Para onde enviar o código" required value="{{ $codigoEnviado }}"></div>
            <div><button class="btn btn-outline-primary">Gerar e enviar código</button></div>
        </form>
        <hr>
        <form method="POST" action="{{ route('admin.telegram.verificar') }}" class="d-grid gap-3">
            @csrf
            <div><label class="form-label fw-semibold">Código de 6 dígitos *</label><input name="codigo" class="form-control" inputmode="numeric" maxlength="6" placeholder="••••••" required></div>
            <div><button class="btn btn-primary">Verificar código</button></div>
        </form>
    </div></div></div>
</div>

<script>
    document.querySelectorAll('[data-preencher]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var alvo = document.getElementById(btn.getAttribute('data-preencher'));
            if (alvo) { alvo.value = btn.getAttribute('data-chat'); alvo.focus(); }
        });
    });
</script>
@endsection
