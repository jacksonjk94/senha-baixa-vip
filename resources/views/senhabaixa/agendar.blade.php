@extends('layouts.telegram')
@section('titulo', 'Agendar')

@section('conteudo')
<div class="card card-telegram mt-3">
    <div class="card-header-telegram">🗓 Novo agendamento</div>
    <div class="card-body p-4">
        @if (($modoDisparo ?? 'agendado') === 'imediato')
            <div class="alert alert-info">Modo <strong>disparo na hora</strong>: a mensagem será enviada imediatamente após confirmar.</div>
        @endif
        <form method="POST" action="{{ route('agendar.salvar') }}">
            @csrf
            <label class="form-label fw-semibold" for="grupo_telegram_id">Grupo de destino *</label>
            <select name="grupo_telegram_id" id="grupo_telegram_id" class="input-telegram" required>
                <option value="">Selecione o grupo...</option>
                @foreach ($grupos as $grupo)
                    <option value="{{ $grupo->id }}" @selected(old('grupo_telegram_id') == $grupo->id)>{{ $grupo->nome }}</option>
                @endforeach
            </select>
            @error('grupo_telegram_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @if ($grupos->isEmpty())
                <div class="alert alert-warning mt-2">Nenhum grupo ativo no momento. Tente novamente mais tarde.</div>
            @endif

            <div class="row mt-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Data</label>
                    <input type="date" name="data_envio" class="input-telegram" value="{{ old('data_envio', date('Y-m-d')) }}" @if (($modoDisparo ?? 'agendado') !== 'imediato') required @endif>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Hora</label>
                    <input type="time" name="hora_envio" class="input-telegram" value="{{ old('hora_envio', $horarioPadrao ?? '') }}" @if (($modoDisparo ?? 'agendado') !== 'imediato') required @endif>
                </div>
            </div>
            @error('data_envio')<div class="text-danger small">{{ $message }}</div>@enderror
            @error('hora_envio')<div class="text-danger small">{{ $message }}</div>@enderror

            <label class="form-label fw-semibold mt-3" for="texto">Texto que vai aparecer no grupo *</label>
            <textarea name="texto" id="texto" class="input-telegram" rows="3" maxlength="500" placeholder="Ex.: Roberta lallison x8" required>{{ old('texto') }}</textarea>
            @error('texto')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <button type="submit" class="btn-telegram mt-3">Agendar ✈</button>
        </form>
    </div>
</div>
@endsection
