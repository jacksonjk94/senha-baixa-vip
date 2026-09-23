@extends('layouts.telegram')
@section('titulo', 'Agendamento concluído')

@section('conteudo')
<div class="card card-telegram text-center mt-3">
    <div class="card-header-telegram">🎉 Tudo pronto!</div>
    <div class="card-body p-4">
        <div class="display-4">✅</div>
        <h1 class="h5 mt-2">Senha agendada com sucesso</h1>
        <pre class="text-start bg-light p-3 rounded mt-3" style="white-space:pre-wrap">{{ $agendamento->textoMensagem() }}</pre>
        <p><span class="badge-tg badge-{{ $agendamento->status === 'enviado' ? 'enviado' : ($agendamento->status === 'erro' ? 'erro' : 'pendente') }}">{{ strtoupper($agendamento->status) }}</span></p>
        <p class="text-muted small">Destino: {{ $agendamento->destino_nome }} · Envio em {{ $agendamento->data_hora_envio?->format('d/m/Y H:i') }}</p>
        <a href="{{ route('dashboard.painel') }}" class="btn-telegram text-center">Ver meus agendamentos</a>
        <br><a href="{{ route('agendafacil.acesso') }}" class="btn btn-link mt-2">Novo agendamento</a>
    </div>
</div>
@endsection
