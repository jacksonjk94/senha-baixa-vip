    @forelse ($grupos as $g)
        <tr data-row-id="{{ $g->id }}">
            <td data-label="Nome">{{ $g->nome }}</td>
            <td data-label="Chat ID"><code>{{ $g->chat_id }}</code></td>
            <td data-label="Conta vinculada">{{ $g->usuario?->name ?? 'Sem vínculo' }}{{ $g->usuario?->telegram_username ? ' (@'.$g->usuario->telegram_username.')' : ($g->usuario?->telegram_id ? ' (ID '.$g->usuario->telegram_id.')' : '') }}</td>
            <td data-label="Tipo">{{ $g->tipo }}</td>
            <td data-label="Regra"><span class="badge-tg {{ $g->modo === 'fechado' ? 'badge-pendente' : 'badge-enviado' }}">{{ $g->etiquetaModo() }}</span></td>
            <td data-label="Horários"><small>{{ $g->horario_abertura?->format('H:i') ?? '—' }} / {{ $g->horario_fechamento?->format('H:i') ?? '—' }} / {{ $g->horario_disparo?->format('H:i') ?? '—' }}</small></td>
            <td data-label="Ativo">{{ $g->ativo ? '✅' : '⏸' }}</td>
            <td class="text-nowrap af-cell-actions">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarGrupo{{ $g->id }}">Editar</button>
                <form method="POST" action="{{ route('admin.grupos.alternar', $g) }}" class="d-inline">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary">{{ $g->ativo ? 'Desativar' : 'Ativar' }}</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted">Nenhum grupo cadastrado.</td></tr>
    @endforelse
