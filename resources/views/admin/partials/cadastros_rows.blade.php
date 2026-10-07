    @forelse ($usuarios as $u)
        <tr data-row-id="{{ $u->id }}">
            <td data-label="Nome">{{ $u->name }}</td>
            <td data-label="E-mail">{{ $u->email }}</td>
            <td data-label="Telefone">{{ $u->telefone ?? '—' }}@if ($u->ultimos_digitos_telefone)<br><small class="text-muted">4 dígitos: {{ $u->ultimos_digitos_telefone }}</small>@endif</td>
            <td data-label="Telegram">{{ $u->telegram_username ?? '—' }}</td>
            <td data-label="Ativo">{{ $u->active ? '✅' : '⏸' }}</td>
            <td class="text-end text-nowrap af-cell-actions">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarVisitante{{ $u->id }}">Editar</button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluirVisitante{{ $u->id }}">Excluir</button>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted">Nenhum visitante cadastrado.</td></tr>
    @endforelse
