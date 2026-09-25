@extends('layouts.telegram')
@section('titulo', 'Concluído')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step done">2. Confirmar os Dados ✓</div>
    <div class="tg-step done">3. Código ✓</div>
    <div class="tg-step done">4. Grupos do Telegram ✓</div>
    <div class="tg-step active">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-1">Concluído</h1>
        <p class="text-muted mb-0">Grupo confirmado. Pode fechar esta janela.</p>
    </div>
</div>
@endsection
