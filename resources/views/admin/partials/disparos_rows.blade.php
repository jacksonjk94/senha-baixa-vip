    @forelse ($agendamentos as $a)
        <tr data-row-id="{{ $a->id }}">
            <td data-label="Visitante">{{ $a->nome_visitante ?? $a->texto }}<br><small class="text-muted">{{ $a->senha_codigo ?? ('posição ' . ($a->posicao ?? '—')) }}</small>
                @if ($a->mensagem_erro)<br><small class="text-danger">{{ \Illuminate\Support\Str::limit($a->mensagem_erro, 80) }}</small>@endif
            </td>
            <td data-label="Destino">{{ $a->destino_nome }} <small class="text-muted">({{ $a->destino_tipo }})</small></td>
            <td data-label="Envio">{{ $a->data_hora_envio?->format('d/m/Y H:i') }}</td>
            <td data-label="Status"><span class="badge-tg badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span></td>
            <td class="d-flex gap-1 af-cell-actions">
                @if (in_array($a->status, ['pendente', 'erro']))
                    <form method="POST" action="{{ route('admin.reenviar', $a) }}">@csrf<button class="btn btn-sm btn-outline-primary">↻ Reenviar</button></form>
                    <form method="POST" action="{{ route('admin.cancelar', $a) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancelar este agendamento?')">✕ Cancelar</button></form>
                @endif
                <form method="POST" action="{{ route('admin.status', $a) }}">@csrf @method('PATCH')
                    <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                        <option value="pendente" @selected($a->status==='pendente')>Pendente</option>
                        <option value="enviado" @selected($a->status==='enviado')>Enviado</option>
                        <option value="erro" @selected($a->status==='erro')>Erro</option>
                        <option value="cancelado" @selected($a->status==='cancelado')>Cancelado</option>
                    </select>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted">Nenhum disparo encontrado.</td></tr>
    @endforelse
