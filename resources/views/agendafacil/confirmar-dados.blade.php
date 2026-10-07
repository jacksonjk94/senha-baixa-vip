@extends('layouts.telegram')
@section('titulo', 'Confirmar dados')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 5])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Revisão</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Confira os dados</h1>

    <div class="border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
            <p class="text-slate-500 text-xs mb-0">Telefone</p>
            <p class="font-bold mb-2 text-slate-800 text-sm">{{ $telefone }}</p>

            <p class="text-slate-500 text-xs mb-0">Conta Telegram</p>
            <p class="font-bold mb-2 text-slate-800 text-sm">{{ $telegramUser['firstName'] ?? '—' }}{{ ! empty($telegramUser['username']) ? ' (@' . $telegramUser['username'] . ')' : '' }}</p>

            <p class="text-slate-500 text-xs mb-0">Grupo</p>
            <p class="font-bold mb-2 text-slate-800 text-sm">{{ $grupo['nome'] }} <small class="text-slate-500">({{ $grupo['tipo'] === 'supergroup' ? 'Supergrupo' : 'Grupo' }})</small></p>

            <p class="text-slate-500 text-xs mb-0">Data e horário</p>
            <p class="font-bold mb-0 text-slate-800 text-sm">{{ \Carbon\Carbon::parse($rascunho['data_envio'] . ' ' . $rascunho['hora_envio'])->format('d/m/Y \à\s H:i') }}</p>
        </div>

        <form method="POST" action="{{ route('agendafacil.resumo.confirmar') }}">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="mensagem">Mensagem que aparecerá no grupo</label>
            <input id="mensagem" name="mensagem" class="w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200" maxlength="200"
                   value="{{ old('mensagem', $rascunho['mensagem'] ?? $mensagemInicial) }}"
                   data-nome="{{ $telegramUser['firstName'] ?? 'Visitante' }}" required>
            @error('mensagem')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

            <p class="text-slate-500 text-xs mb-1 mt-3">Prévia de como chegará no grupo</p>
            <div style="display:flex">
                <div style="background:#fff;border-radius:4px 16px 16px 16px;box-shadow:0 1px 2px rgba(30,58,138,.15);padding:8px 12px 6px;max-width:85%;min-width:180px;border:1px solid #E2E8F0">
                    <div style="color:#1D4ED8;font-weight:700;font-size:13px;margin-bottom:2px">{{ $telegramUser['firstName'] ?? 'Visitante' }}</div>
                    <div style="font-size:15px;color:#111;overflow-wrap:anywhere"><span id="prevTexto"></span> <span style="float:right;font-size:11px;color:#8A9BA8;margin:8px 0 0 8px">{{ \Carbon\Carbon::parse($rascunho['data_envio'] . ' ' . $rascunho['hora_envio'])->format('H:i') }} ✓✓</span></div>
                </div>
            </div>

            <div class="rounded-lg p-3 my-3 bg-amber-50 text-amber-800 text-xs">
                Ao confirmar, você declara que é responsável pelos dados confirmados.
            </div>

            <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-blue-900 to-blue-700 hover:from-blue-800 hover:to-blue-600 text-white font-semibold transition-all duration-200 shadow-sm active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed">Confirmar agendamento</button>
        </form>
</div>

<script>
    (function () {
        var mensagem = document.getElementById('mensagem');
        var prevTexto = document.getElementById('prevTexto');
        function atualizar() {
            prevTexto.textContent = (mensagem.value.trim() || mensagem.dataset.nome || 'Sua mensagem');
        }
        mensagem.addEventListener('input', atualizar);
        atualizar();
        mensagem.focus();
        mensagem.setSelectionRange(mensagem.value.length, mensagem.value.length);
    })();
</script>
@endsection
