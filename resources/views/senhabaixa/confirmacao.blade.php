@extends('layouts.telegram')
@section('titulo', 'Confirmação')

@section('conteudo')
<div class="card card-telegram mt-3">
    <div class="card-header-telegram">✅ Agendado!</div>
    <div class="card-body p-4 text-center">
        @if ($agendamento->status === 'enviado')
            <p class="h5">Enviado ✅</p>
        @else
            <p class="h5">Agendado ✅</p>
        @endif
        <p class="text-muted">Envia dia <strong>{{ $agendamento->data_hora_envio?->format('d/m/Y') }}</strong> às <strong>{{ $agendamento->data_hora_envio?->format('H:i') }}h</strong></p>
        <p class="h4">Posição: {{ $agendamento->posicao ?? '—' }} de {{ $total }}</p>
        <div class="alert alert-info text-start">
            <strong>Grupo:</strong> {{ $agendamento->destino_nome }}<br>
            <strong>Texto:</strong> {{ $agendamento->texto }}
        </div>
        @if ($agendamento->status === 'erro')
            <div class="alert alert-danger">Falha no envio: {{ $agendamento->mensagem_erro }}</div>
        @endif
        <a href="{{ route('agendar') }}" class="btn-telegram text-center">Novo agendamento ✈</a>
        <p class="mt-3 mb-0"><a href="{{ route('agendamento.meus') }}">Ver meus agendamentos</a></p>
    </div>
</div>
@endsection
