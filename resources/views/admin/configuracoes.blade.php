@extends('layouts.admin')
@section('titulo', 'Config. disparos')

@section('conteudo')
<h1 class="h4 mb-3">⚙️ Configuração dos disparos</h1>

<div class="card stat-card"><div class="card-body">
<form method="POST" action="{{ route('admin.configuracoes.salvar') }}" class="d-grid gap-3" style="max-width:560px">
    @csrf
    <div>
        <label class="form-label fw-semibold">Modo de disparo *</label>
        <select name="disparo_modo" class="form-select" required>
            <option value="agendado" @selected($modo === 'agendado')>Agendado — envia na data/hora marcada (fila + scheduler)</option>
            <option value="imediato" @selected($modo === 'imediato')>Na hora — dispara imediatamente ao agendar</option>
        </select>
        <small class="text-muted">No modo "na hora", o agendamento é criado com o horário atual e enviado na mesma requisição.</small>
    </div>
    <div>
        <label class="form-label fw-semibold">Horário padrão dos disparos *</label>
        <input type="time" name="disparo_horario" class="form-control" value="{{ $horario }}" required>
        <small class="text-muted">Sugerido como hora padrão na tela de agendamento.</small>
    </div>
    <div>
        <label class="form-label fw-semibold">Modo do fluxo de agendamento *</label>
        <select name="agendamento_modo" class="form-select" required>
            <option value="producao" @selected($modoFluxo === 'producao')>Produção — validações e autenticação reais via Telegram</option>
            <option value="teste" @selected($modoFluxo === 'teste')>Teste — permite percorrer as etapas sem Telegram; nenhum dado é gravado</option>
        </select>
        <small class="text-muted">No modo teste, o código pode ser qualquer número, os grupos são fictícios e nada é salvo no final.</small>
    </div>
    <div class="af-subcard p-3">
        <div class="form-check form-switch">
            <input type="checkbox" name="disparo_latencia_ativo" value="1" class="form-check-input" id="latenciaAtivo" @checked($latenciaAtiva ?? true)>
            <label class="form-check-label fw-semibold" for="latenciaAtivo">Disparos com latência/antecedência</label>
        </div>
        <small class="text-muted d-block mb-2">Quando ligado, o disparo inicia antes do :00s.000ms pelo tempo da latência medida, para a mensagem chegar no Telegram exatamente no segundo 00 (meta: 00s.00x ms).</small>
        <label class="form-label fw-semibold" for="latenciaMs">Latência medida (ms) *</label>
        <input type="number" name="disparo_latencia_ms" id="latenciaMs" class="form-control" value="{{ $latenciaMs ?? 250 }}" min="0" max="10000" step="50" required>
        <small class="text-muted">Tempo entre iniciar o envio e a chegada no Telegram. Medido local (MTProto): ~250–400ms. Se chegar no 59s, diminua; se chegar após 00.5s, aumente.</small>
    </div>
    <div>
        <button class="btn btn-primary">Salvar configurações</button>
    </div>
</form>
</div></div>

<div class="card stat-card mt-3"><div class="card-body">
    <h2 class="h6">Como funciona</h2>
    <ul class="mb-0 small text-muted">
        <li><strong>Agendado:</strong> o visitante escolhe data/hora; o scheduler enfileira os vencidos (<code>processarFila</code>).</li>
        <li><strong>Na hora:</strong> ignora a data/hora informada, usa o momento atual e dispara via Bot API imediatamente.</li>
    </ul>
</div></div>
@endsection
