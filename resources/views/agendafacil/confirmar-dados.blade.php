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

        @if (! $agendamento)
            <div class="alert alert-warning">Nenhum agendamento vinculado a este telefone. Fale com a administração.</div>
        @endif

        <form method="POST" action="{{ route('agendafacil.confirmar-dados.salvar') }}">
            @csrf
            <label class="form-label fw-semibold" for="grupo_id">Nome do grupo</label>
            <select id="grupo_id" name="grupo_id" class="input-telegram" required @if(! $agendamento) disabled @endif>
                @foreach ($grupos as $grupo)
                    <option value="{{ $grupo->id }}" @selected((string) old('grupo_id', $agendamento?->grupo_telegram_id) === (string) $grupo->id)>{{ $grupo->nome }}</option>
                @endforeach
            </select>
            @error('grupo_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <label class="form-label fw-semibold mt-3" for="mensagem">Mensagem</label>
            <input id="mensagem" name="mensagem" class="input-telegram" maxlength="500"
                   value="{{ old('mensagem', $mensagemInicial) }}"
                   data-nome="{{ $agendamento?->nome_visitante ?: $usuario->name }}" required @if(! $agendamento) disabled @endif>
            @error('mensagem')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div class="border rounded p-3 my-3" style="border-color:#D8ECF7;background:#F8FBFD">
                <p class="text-muted small mb-0">Horário</p>
                <p class="fw-bold mb-2">{{ $agendamento?->data_hora_envio?->format('H:i') ?? '—' }}</p>

                <p class="text-muted small mb-0">Tipo de disparo</p>
                <p class="fw-bold mb-2">{{ $agendamento?->dados_senha ?? ($agendamento?->destino_tipo === 'pessoa' ? 'Individual' : 'Grupo') }}</p>

                <p class="text-muted small mb-0">Telefone</p>
                <p class="fw-bold mb-0">{{ preg_replace('/\D/', '', (string) ($usuario->telefone ?? '')) ?: '—' }}</p>
            </div>

            <div class="rounded p-3 mb-3" style="background:#FDF3E3;color:#8A5A00">
                Ao confirmar, você declara que é responsável pelos dados confirmados.
            </div>

            <button type="submit" class="btn-telegram" @if(! $agendamento) disabled @endif>Confirmar dados</button>
        </form>
    </div>
</div>

<script>
    (function () {
        const grupo = document.getElementById('grupo_id');
        const mensagem = document.getElementById('mensagem');
        if (!grupo || !mensagem) return;

        grupo.addEventListener('change', async () => {
            const url = @json(route('agendafacil.proxima-senha')) + '?grupo_id=' + encodeURIComponent(grupo.value);
            try {
                const resposta = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!resposta.ok) return;
                const dados = await resposta.json();
                if (!dados.proxima) return;

                const atual = mensagem.value.trim();
                const comSenha = atual.match(/^(.*?)x\s*\d+\s*$/i);
                if (comSenha) {
                    mensagem.value = comSenha[1].trimEnd() + ' x' + dados.proxima;
                } else if (atual === '') {
                    mensagem.value = (mensagem.dataset.nome || '').trim() + ' x' + dados.proxima;
                }
            } catch (e) {
                // Mantém o valor digitado se a consulta falhar.
            }
        });
    })();
</script>
@endsection
