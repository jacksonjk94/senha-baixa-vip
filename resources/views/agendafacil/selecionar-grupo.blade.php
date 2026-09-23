@extends('layouts.telegram')
@section('titulo', 'Selecionar grupo')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Dados ✓</div>
    <div class="tg-step done">3. Telegram ✓</div>
    <div class="tg-step done">4. Código ✓</div>
    <div class="tg-step active">5. Grupo</div>
    <div class="tg-step">6. Agendar</div>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">👥 Selecione o destino</div>
    <div class="card-body p-4">
        <form method="GET" action="{{ route('grupos.selecionar') }}" class="d-flex gap-2 mb-3">
            <input name="busca" class="input-telegram" placeholder="Buscar grupo..." value="{{ $busca }}">
            <button class="btn btn-primary" style="border-radius:12px">🔍</button>
        </form>

        @if (! $telegramConfigurado)
            <div class="alert alert-warning">
                Telegram não configurado (<code>TELEGRAM_TOKEN</code> vazio no <code>.env</code>).
                Preencha o token do BotFather e recarregue para listar grupos reais.
            </div>
        @elseif(isset($telegramStatus) && ! $telegramStatus['ok'])
            <div class="alert alert-danger">
                Token do Telegram inválido ou sem conexão: {{ $telegramStatus['erro'] }}.
                Confira o <code>TELEGRAM_TOKEN</code> no <code>.env</code>.
            </div>
        @elseif ($grupos->isEmpty())
            <div class="alert alert-warning">
                Nenhum grupo encontrado. O bot <strong>{{ $telegramBot }}</strong> só enxerga
                grupos onde ele foi <strong>adicionado como membro</strong> e houve
                <strong>mensagem/atividade recente</strong> (limitação da Bot API via
                <code>getUpdates</code> — não é possível listar todos os grupos da sua conta pessoal).
                <ol class="mb-0 mt-2">
                    <li>Adicione o bot ao grupo desejado;</li>
                    <li>Envie qualquer mensagem no grupo (ex.: <code>/start</code>);</li>
                    <li>Recarregue esta página;</li>
                    <li>Ou cadastre manualmente na <a href="{{ route('admin.grupos') }}">área admin</a> com o <code>chat_id</code>.</li>
                </ol>
            </div>
        @endif

        <form method="POST" action="{{ route('grupos.confirmar') }}" id="form-destino">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" for="destino_tipo">Tipo de destino</label>
                <select name="destino_tipo" id="destino_tipo" class="input-telegram" required>
                    <option value="grupo" @selected(old('destino_tipo', 'grupo') === 'grupo')>Grupo do Telegram</option>
                    <option value="pessoa" @selected(old('destino_tipo') === 'pessoa')>Pessoa (chat individual)</option>
                </select>
                @error('destino_tipo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="list-group mb-3" id="lista-grupos">
                @foreach ($grupos as $grupo)
                    <label class="list-group-item d-flex gap-2 align-items-center">
                        <input type="radio" name="grupo_id" value="{{ $grupo->id }}" class="form-check-input grupo-radio"
                            @checked((string) old('grupo_id') === (string) $grupo->id)>
                        <span><strong>{{ $grupo->nome }}</strong><br><small class="text-muted">{{ $grupo->tipo }} · {{ $grupo->chat_id }}</small></span>
                    </label>
                @endforeach
            </div>
            @error('grupo_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <button type="submit" class="btn-telegram">Confirmar grupo →</button>
        </form>

        <script>
            (function () {
                const tipo = document.getElementById('destino_tipo');
                const radios = () => Array.from(document.querySelectorAll('.grupo-radio'));
                function ajustarObrigatoriedade() {
                    const ehGrupo = tipo.value === 'grupo';
                    radios().forEach(r => { r.required = ehGrupo; });
                    document.getElementById('lista-grupos').style.opacity = ehGrupo ? '1' : '0.5';
                }
                tipo.addEventListener('change', ajustarObrigatoriedade);
                ajustarObrigatoriedade();
            })();
        </script>

        <div class="mt-3">{{ $grupos->links() }}</div>
    </div>
</div>
@endsection
