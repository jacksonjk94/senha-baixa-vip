@extends('layouts.telegram')
@section('titulo', 'Agendar disparo')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1 ✓</div>
    <div class="tg-step done">2 ✓</div>
    <div class="tg-step done">3 ✓</div>
    <div class="tg-step done">4 ✓</div>
    <div class="tg-step done">5 ✓</div>
    <div class="tg-step active">6. Agendar</div>
</div>

<div class="card card-telegram">
    <div class="card-header-telegram">🗓 Agendamento do disparo</div>
    <div class="card-body p-4">
        <p class="text-muted">Destino: <strong>{{ $grupoSessao['destino_nome'] ?? '—' }}</strong> ({{ $grupoSessao['destino_tipo'] ?? 'grupo' }})</p>
        <form method="POST" action="{{ route('agendamento.salvar') }}">
            @csrf
            <label class="form-label fw-semibold">Nome do visitante</label>
            <input name="nome_visitante" class="input-telegram" value="{{ old('nome_visitante', $confirmacao['nome_visitante'] ?? '') }}" required>
            <input type="hidden" name="destino_tipo" value="{{ $grupoSessao['destino_tipo'] ?? 'grupo' }}">
            @if (! empty($grupoSessao['grupo_id']))
                <input type="hidden" name="grupo_telegram_id" value="{{ $grupoSessao['grupo_id'] }}">
            @endif

            <div class="row mt-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Data</label>
                    <input type="date" name="data_envio" class="input-telegram" value="{{ old('data_envio') }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Hora</label>
                    <input type="time" name="hora_envio" class="input-telegram" value="{{ old('hora_envio') }}" required>
                </div>
            </div>
            @error('data_envio')<div class="text-danger small">{{ $message }}</div>@enderror
            @error('hora_envio')<div class="text-danger small">{{ $message }}</div>@enderror

            <label class="form-label fw-semibold mt-3">Observações (opcional)</label>
            <textarea name="dados_senha" class="input-telegram" rows="2" maxlength="500">{{ old('dados_senha') }}</textarea>

            <button type="submit" class="btn-telegram mt-3">Agendar disparo ✈</button>
        </form>
    </div>
</div>
@endsection
