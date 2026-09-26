@extends('layouts.telegram')
@section('titulo', 'Confirmar dados')

@section('conteudo')
<div class="tg-steps">
    <div class="tg-step done">1. Acesso ✓</div>
    <div class="tg-step active">2. Confirmar os Dados</div>
    <div class="tg-step">3. Código</div>
    <div class="tg-step">4. Grupos do Telegram</div>
    <div class="tg-step">5. Concluído</div>
</div>

<div class="card card-telegram">
    <div class="card-body p-4">
        <p class="text-uppercase fw-bold small mb-1" style="color:#0B6B4F;letter-spacing:.04em">Login Telegram</p>
        <h1 class="h4 mb-3">Confirmar dados</h1>

        @if ($grupos->isEmpty())
            <div class="alert alert-warning">Os grupos ainda estão sendo configurados. Tente novamente em instantes ou entre em contato.</div>
        @else
        <form method="POST" action="{{ route('agendafacil.confirmar-dados.salvar') }}">
            @csrf
            <label class="form-label fw-semibold" for="grupo_id">Grupo de destino</label>
            <select id="grupo_id" name="grupo_id" class="input-telegram" required>
                @foreach ($grupos as $grupo)
                    <option value="{{ $grupo->id }}"
                        @selected((string) old('grupo_id', $rascunho['grupo_id'] ?? $agendamento?->grupo_telegram_id) === (string) $grupo->id)
                        data-modo="{{ $grupo->modo }}"
                        data-abertura="{{ $grupo->horario_abertura?->format('H:i') }}"
                        data-fechamento="{{ $grupo->horario_fechamento?->format('H:i') }}"
                        data-disparo="{{ $grupo->horario_disparo?->format('H:i') }}">{{ $grupo->nome }} {{ $grupo->etiquetaModo() }}</option>
                @endforeach
            </select>
            @error('grupo_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div id="aviso-grupo" class="rounded p-3 mt-2 small" style="background:#EAF6FD;color:#0B5A86"></div>

            <label class="form-label fw-semibold mt-3" for="mensagem">Mensagem que aparecerá no grupo</label>
            <input id="mensagem" name="mensagem" class="input-telegram" maxlength="200"
                   placeholder="Ex: Maria Silva"
                   value="{{ old('mensagem', $rascunho['mensagem'] ?? $mensagemInicial) }}"
                   data-nome="{{ $agendamento?->nome_visitante ?: $usuario->name }}" required>
            @error('mensagem')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <p class="text-muted small mb-1 mt-3">Prévia de como chegará no grupo</p>
            <div class="tg-preview">
                <div class="tg-bubble">
                    <div class="tg-sender">{{ $agendamento?->nome_visitante ?: $usuario->name }}</div>
                    <div class="tg-text"><span id="prevTexto"></span><span class="tg-meta"><span id="prevHora"></span> <span class="tg-checks">✓✓</span></span></div>
                </div>
            </div>
            <style>
                .tg-preview { display: flex; }
                .tg-bubble {
                    background: #fff; border-radius: 4px 16px 16px 16px;
                    box-shadow: 0 1px 2px rgba(16, 35, 47, .18);
                    padding: 8px 12px 6px; max-width: 85%; min-width: 180px;
                }
                .tg-sender { color: #2AABEE; font-weight: 700; font-size: 13px; margin-bottom: 2px; }
                .tg-text { font-size: 15px; color: #111; overflow-wrap: anywhere; }
                .tg-meta { float: right; font-size: 11px; color: #8A9BA8; margin: 8px 0 0 8px; }
                .tg-checks { color: #2AABEE; }
            </style>

            <label class="form-label fw-semibold mt-3">Horário que será enviado</label>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small text-muted" for="data_envio">Data do envio</label>
                    <input id="data_envio" type="date" name="data_envio" class="input-telegram"
                           value="{{ old('data_envio', $rascunho['data_envio'] ?? $agendamento?->data_hora_envio?->format('Y-m-d') ?? $sugestaoData) }}"
                           required>
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted" for="hora_envio">Horário que será enviado</label>
                    <input id="hora_envio" type="time" name="hora_envio" class="input-telegram"
                           value="{{ old('hora_envio', $rascunho['hora_envio'] ?? $agendamento?->data_hora_envio?->format('H:i') ?? $sugestaoHora) }}"
                           required>
                </div>
            </div>
            @error('data_envio')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @error('hora_envio')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div class="border rounded p-3 my-3" style="border-color:#D8ECF7;background:#F8FBFD">
                <p class="text-muted small mb-0">Tipo de disparo</p>
                <p class="fw-bold mb-2">{{ $agendamento?->dados_senha ?? ($agendamento?->destino_tipo === 'pessoa' ? 'Individual' : 'Grupo') }}</p>

                <p class="text-muted small mb-0">Telefone</p>
                <p class="fw-bold mb-0">{{ preg_replace('/\D/', '', (string) ($usuario->telefone ?? '')) ?: '—' }}</p>
            </div>

            <div class="rounded p-3 mb-3" style="background:#FDF3E3;color:#8A5A00">
                Ao confirmar, você declara que é responsável pelos dados confirmados.
            </div>

            <button type="submit" class="btn-telegram">Avançar →</button>
        </form>
        @endif
    </div>
</div>

<script>
    (function () {
        const grupo = document.getElementById('grupo_id');
        const mensagem = document.getElementById('mensagem');
        const aviso = document.getElementById('aviso-grupo');
        const hora = document.getElementById('hora_envio');
        const prevTexto = document.getElementById('prevTexto');
        const prevHora = document.getElementById('prevHora');
        if (!grupo) return;

        function atualizarPrevia() {
            if (!prevTexto || !mensagem) return;
            prevTexto.textContent = mensagem.value.trim() || (mensagem.dataset.nome || '').trim() || 'Sua mensagem';
            if (prevHora) {
                const agora = new Date();
                prevHora.textContent = String(agora.getHours()).padStart(2, '0') + ':' + String(agora.getMinutes()).padStart(2, '0');
            }
        }

        function atualizarAviso() {
            const opt = grupo.options[grupo.selectedIndex];
            if (!opt || !aviso) return;
            const modo = opt.dataset.modo;
            if (modo === 'fechado') {
                aviso.style.display = '';
                aviso.textContent = 'Grupo fechado — abre às ' + (opt.dataset.abertura || '—')
                    + ', fecha às ' + (opt.dataset.fechamento || '—')
                    + ', envia às ' + (opt.dataset.disparo || '—')
                    + '. Sua mensagem sairá no envio em bloco.';
                if (hora && opt.dataset.disparo) {
                    hora.value = opt.dataset.disparo;
                    hora.readOnly = true;
                }
            } else {
                aviso.style.display = '';
                aviso.textContent = 'Sua mensagem será enviada exatamente neste horário.';
                if (hora) hora.readOnly = false;
            }
        }

        grupo.addEventListener('change', atualizarAviso);
        if (mensagem) mensagem.addEventListener('input', atualizarPrevia);

        atualizarAviso();
        atualizarPrevia();
    })();
</script>
@endsection
