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
    <div><button class="btn btn-primary">Salvar configurações</button></div>
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
