@extends('layouts.telegram')
@section('titulo', 'Meus agendamentos')

@section('conteudo')
<h1 class="h5 mt-3">📅 Meus agendamentos</h1>
<a href="{{ route('agendafacil.acesso') }}" class="btn-telegram mb-3 text-center">+ Novo agendamento</a>

@forelse ($agendamentos as $a)
    <div class="card card-telegram mb-2">
        <div class="card-body p-3 d-flex justify-content-between align-items-center">
            <div>
                <strong>{{ $a->nome_visitante }}</strong> · {{ $a->senha_codigo }}<br>
                <small class="text-muted">{{ $a->destino_nome }} · {{ $a->data_hora_envio?->format('d/m/Y H:i') }}</small>
            </div>
            <span class="badge-tg badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span>
        </div>
    </div>
@empty
    <div class="alert alert-info">Nenhum agendamento ainda.</div>
@endforelse

{{ $agendamentos->links() }}
@endsection
