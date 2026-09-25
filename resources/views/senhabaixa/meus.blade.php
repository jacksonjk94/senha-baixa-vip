@extends('layouts.telegram')
@section('titulo', 'Meus agendamentos')

@section('conteudo')
<div class="card card-telegram mt-3">
    <div class="card-header-telegram">📋 Meus agendamentos</div>
    <div class="card-body p-4">
        @forelse ($agendamentos as $a)
            <div class="border rounded p-3 mb-2">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <strong>{{ $a->destino_nome }}</strong>
                    <span class="badge-tg badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span>
                </div>
                <div class="text-muted small">{{ $a->data_hora_envio?->format('d/m/Y H:i') }}h @if ($a->posicao)· posição {{ $a->posicao }}@endif</div>
                <div class="mt-1">{{ $a->texto }}</div>
            </div>
        @empty
            <p class="text-muted text-center">Nenhum agendamento neste aparelho ainda. <a href="{{ route('agendar') }}">Agende agora ✈</a></p>
        @endforelse
        <p class="text-center mt-3 mb-0"><a href="{{ route('agendar') }}">＋ Novo agendamento</a></p>
    </div>
</div>
@endsection
