@extends('layouts.telegram')
@section('titulo', 'Confirmar dados')

@section('conteudo')
@include('agendafacil.partials.timeline', ['etapaAtual' => 5])

<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
    <p class="text-xs font-bold tracking-wider uppercase text-blue-700 mb-1">Revisão</p>
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Confira os dados</h1>

    <div class="border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
            <div class="flex items-center gap-3 mb-3">
                <span style="position:relative;width:40px;height:40px;flex-shrink:0">
                    <span class="w-10 h-10 rounded-full bg-blue-900 text-white flex items-center justify-center font-bold" style="position:absolute;inset:0">{{ \Illuminate\Support\Str::substr($telegramUser['firstName'] ?? 'V', 0, 1) }}</span>
                    <img src="{{ route('telegram.foto.usuario') }}" alt="Foto de perfil" loading="lazy"
                         style="position:absolute;inset:0;width:100%;height:100%;border-radius:999px;object-fit:cover;background:#E2E8F0" onerror="this.remove()">
                </span>
                <div>
                    <p class="font-bold text-slate-800 text-sm leading-tight">{{ $telegramUser['firstName'] ?? 'Visitante' }}</p>
                    <p class="text-slate-500 text-xs">{{ ! empty($telegramUser['username']) ? '@' . $telegramUser['username'] : 'Conta Telegram' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mb-3">
                <span style="position:relative;width:40px;height:40px;flex-shrink:0">
                    <span class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold" style="position:absolute;inset:0">{{ \Illuminate\Support\Str::substr($grupo['nome'], 0, 1) }}</span>
                    <img src="{{ route('telegram.foto.grupo', ['chatId' => $grupo['chat_id']]) }}" alt="Foto do grupo {{ $grupo['nome'] }}" loading="lazy"
                         style="position:absolute;inset:0;width:100%;height:100%;border-radius:999px;object-fit:cover;background:#E2E8F0" onerror="this.remove()">
                </span>
                <div>
                    <p class="font-bold text-slate-800 text-sm leading-tight">{{ $grupo['nome'] }}</p>
                    <p class="text-slate-500 text-xs">{{ $grupo['tipo'] === 'supergroup' ? 'Supergrupo' : 'Grupo' }}</p>
                </div>
            </div>
            <p class="text-slate-500 text-xs mb-0">Telefone</p>
            <p class="font-bold mb-2 text-slate-800 text-sm">{{ $telefone }}</p>

            <p class="text-slate-500 text-xs mb-0">Conta Telegram</p>
            <p class="font-bold mb-2 text-slate-800 text-sm">{{ $telegramUser['firstName'] ?? '—' }}{{ ! empty($telegramUser['username']) ? ' (@' . $telegramUser['username'] . ')' : '' }}</p>

            <p class="text-slate-500 text-xs mb-0">Grupo</p>
            <p class="font-bold mb-2 text-slate-800 text-sm">{{ $grupo['nome'] }} <small class="text-slate-500">({{ $grupo['tipo'] === 'supergroup' ? 'Supergrupo' : 'Grupo' }})</small></p>

            <p class="text-slate-500 text-xs mb-0">Data e horário</p>
            @php
                $envioEm = \Carbon\Carbon::parse($rascunho['data_envio'] . ' ' . $rascunho['hora_envio'], 'America/Sao_Paulo');
            @endphp
            <p class="font-bold mb-0 text-slate-800 text-sm">{{ $envioEm->format('d/m/Y \à\s H:i') }} <span class="text-slate-500 font-normal">(horário de Brasília)</span></p>
            <p class="text-xs text-blue-700 mt-1">{{ $envioEm->isFuture() ? 'Envio ' . \Carbon\Carbon::now('America/Sao_Paulo')->diffForHumans($envioEm, ['parts' => 2, 'short' => true]) : 'Data/horário já passou' }}</p>
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
    <div class="mt-4 text-center">
        <a href="{{ route('grupos.selecionar') }}" class="text-sm text-blue-700 hover:text-blue-900 font-medium transition-colors">← Voltar</a>
    </div>
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
