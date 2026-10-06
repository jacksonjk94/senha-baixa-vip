<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use App\Services\TelegramBotService;
use App\Support\PapelHierarquia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Dashboard administrativo com totais.
     */
    public function index()
    {
        return view('admin.inicio', [
            'total' => Agendamento::count(),
            'enviados' => Agendamento::where('status', Agendamento::STATUS_ENVIADO)->count(),
            'pendentes' => Agendamento::where('status', Agendamento::STATUS_PENDENTE)->count(),
            'erros' => Agendamento::where('status', Agendamento::STATUS_ERRO)->count(),
            'recentes' => Agendamento::with(['user', 'grupo'])->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Cadastros: somente visitantes (role user).
     */
    public function cadastros(Request $request)
    {
        $busca = trim((string) $request->query('busca', ''));

        $usuarios = User::query()
            ->whereIn('role', PapelHierarquia::VISITANTES)
            ->when($busca !== '', fn ($q) => $q->where(function ($w) use ($busca) {
                $w->where('name', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%")
                    ->orWhere('telefone', 'like', "%{$busca}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.cadastros', compact('usuarios', 'busca'));
    }

    /**
     * Cadastra um novo visitante (role user, senha aleatória — acesso via 4 dígitos).
     */
    public function armazenarVisitante(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'telefone' => ['required', 'string', 'max:30'],
            'telegram_username' => ['nullable', 'string', 'max:60'],
        ]);

        User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'telefone' => $this->telefoneComDdi($dados['telefone']),
            'telegram_username' => $dados['telegram_username'] ?? null,
            'password' => Hash::make(Str::random(16)),
            'role' => 'user',
            'active' => true,
        ]);

        return back()->with('sucesso', 'Visitante cadastrado com sucesso!');
    }

    /**
     * Atualiza um visitante.
     */
    public function atualizarVisitante(Request $request, User $visitante)
    {
        abort_unless(PapelHierarquia::ehVisitante($visitante->role), 403);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,' . $visitante->id],
            'telefone' => ['required', 'string', 'max:30'],
            'telegram_username' => ['nullable', 'string', 'max:60'],
            'active' => ['nullable', 'boolean'],
        ]);

        $visitante->update([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'telefone' => $this->telefoneComDdi($dados['telefone']),
            'telegram_username' => $dados['telegram_username'] ?? null,
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('sucesso', 'Cadastro atualizado com sucesso!');
    }

    /**
     * Exclui (soft delete) um visitante, com confirmação na view.
     */
    public function excluirVisitante(User $visitante)
    {
        abort_unless(PapelHierarquia::ehVisitante($visitante->role), 403);

        $visitante->delete();

        return back()->with('sucesso', 'Cadastro excluído com sucesso!');
    }

    /**
     * Usuários de sistema, conforme a hierarquia do autenticado.
     */
    public function usuarios(Request $request)
    {
        $ator = $request->user();
        $visiveis = PapelHierarquia::visiveisPara($ator);
        $busca = trim((string) $request->query('busca', ''));

        $usuarios = User::query()
            ->whereIn('role', $visiveis)
            ->when($busca !== '', fn ($q) => $q->where(function ($w) use ($busca) {
                $w->where('name', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%");
            }))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios', [
            'usuarios' => $usuarios,
            'busca' => $busca,
            'atribuiveis' => PapelHierarquia::atribuiveisPara($ator),
            'podeGerenciarPares' => (bool) $ator->can_manage_admins,
        ]);
    }

    /**
     * Cria um usuário de sistema dentro dos papéis permitidos ao ator.
     */
    public function armazenarUsuario(Request $request)
    {
        $ator = $request->user();
        $atribuiveis = PapelHierarquia::atribuiveisPara($ator);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', 'in:' . implode(',', $atribuiveis)],
            'telefone' => ['nullable', 'string', 'max:30'],
            'can_manage_admins' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'password' => Hash::make($dados['password']),
            'role' => $dados['role'],
            'telefone' => filled($dados['telefone'] ?? null) ? $this->telefoneComDdi($dados['telefone']) : null,
            'can_manage_admins' => $ator->can_manage_admins && $request->boolean('can_manage_admins'),
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('sucesso', 'Usuário criado com sucesso!');
    }

    /**
     * Atualiza um usuário de sistema (sem never acima, sem auto-edição de papel).
     */
    public function atualizarUsuario(Request $request, User $usuario)
    {
        $ator = $request->user();

        abort_unless(PapelHierarquia::podeGerenciar($ator, $usuario), 403, 'Sem permissão para gerenciar este usuário.');

        $atribuiveis = PapelHierarquia::atribuiveisPara($ator);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,' . $usuario->id],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'role' => ['required', 'in:' . implode(',', $atribuiveis)],
            'telefone' => ['nullable', 'string', 'max:30'],
            'can_manage_admins' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        $usuario->fill([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'role' => $dados['role'],
            'telefone' => filled($dados['telefone'] ?? null) ? $this->telefoneComDdi($dados['telefone']) : null,
            'active' => $request->boolean('active', true),
        ]);

        if (filled($dados['password'] ?? null)) {
            $usuario->password = Hash::make($dados['password']);
        }

        // Só quem já pode gerenciar admins concede a flag.
        if ($ator->can_manage_admins) {
            $usuario->can_manage_admins = $request->boolean('can_manage_admins');
        }

        $usuario->save();

        return back()->with('sucesso', 'Usuário atualizado com sucesso!');
    }

    /**
     * Exclui (soft delete) um usuário de sistema.
     */
    public function excluirUsuario(Request $request, User $usuario)
    {
        abort_unless(PapelHierarquia::podeGerenciar($request->user(), $usuario), 403, 'Sem permissão para excluir este usuário.');

        $usuario->delete();

        return back()->with('sucesso', 'Usuário excluído com sucesso!');
    }

    /**
     * Disparos com filtros por período, status e destino.
     */
    public function disparos(Request $request)
    {
        $agendamentos = Agendamento::with(['user', 'grupo'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('destino'), fn ($q) => $q->where('destino_tipo', $request->query('destino')))
            ->when($request->filled('de'), fn ($q) => $q->whereDate('data_hora_envio', '>=', $request->query('de')))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('data_hora_envio', '<=', $request->query('ate')))
            ->latest('data_hora_envio')
            ->paginate(15)
            ->withQueryString();

        return view('admin.disparos', compact('agendamentos'));
    }

    /**
     * Gerenciamento dos destinos (grupos) + lista via Bot API para importação.
     */
    public function grupos(Request $request, TelegramBotService $telegram)
    {
        $grupos = GrupoTelegram::with('usuario')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->paginate(15);
        $usuarios = User::whereIn('role', PapelHierarquia::VISITANTES)
            ->orderBy('name')
            ->get(['id', 'name', 'telegram_id', 'telegram_username']);

        return view('admin.grupos', [
            'grupos' => $grupos,
            'usuarios' => $usuarios,
            'telegramConfigurado' => $telegram->configurado(),
            'gruposApi' => $telegram->configurado() ? $telegram->listarGrupos() : [],
        ]);
    }

    public function armazenarGrupo(Request $request)
    {
        $dados = $request->validate($this->regrasGrupo());

        GrupoTelegram::updateOrCreate(
            ['chat_id' => $dados['chat_id'], 'usuario_id' => $dados['usuario_id']],
            $this->atributosGrupo($dados, ['ativo' => true, 'ordem' => $dados['ordem'] ?? 0])
        );

        return back()->with('sucesso', 'Grupo cadastrado com sucesso!');
    }

    /**
     * Edita regras do grupo (modo aberto/fechado, horários, dias, ordem).
     */
    public function atualizarGrupo(Request $request, GrupoTelegram $grupo)
    {
        $dados = $request->validate($this->regrasGrupo());

        $grupo->update($this->atributosGrupo($dados));

        return back()->with('sucesso', 'Grupo atualizado com sucesso!');
    }

    public function alternarGrupo(GrupoTelegram $grupo)
    {
        $grupo->update(['ativo' => ! $grupo->ativo]);

        return back()->with('sucesso', 'Status do grupo atualizado.');
    }

    /**
     * Importa um grupo vindo da Bot API (dialog "Listar do Telegram").
     */
    public function importarGrupo(Request $request)
    {
        $dados = $request->validate([
            'chat_id' => ['required', 'string', 'max:60'],
            'nome' => ['required', 'string', 'max:150'],
            'tipo' => ['required', 'in:group,supergroup,channel'],
            'usuario_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', PapelHierarquia::VISITANTES)),
            ],
        ]);

        GrupoTelegram::updateOrCreate(
            ['chat_id' => $dados['chat_id'], 'usuario_id' => $dados['usuario_id']],
            ['nome' => $dados['nome'], 'tipo' => $dados['tipo'], 'modo' => GrupoTelegram::MODO_ABERTO, 'ativo' => true]
        );

        return back()->with('sucesso', 'Grupo "' . $dados['nome'] . '" cadastrado!');
    }

    public function atualizarStatus(Request $request, Agendamento $agendamento)
    {
        $dados = $request->validate([
            'status' => ['required', 'in:pendente,enviado,erro,cancelado'],
        ]);

        $agendamento->update(['status' => $dados['status']]);

        return back()->with('sucesso', 'Status atualizado com sucesso!');
    }

    /**
     * Cancela um agendamento (não entra mais na fila de disparo)
     * e recalcula as posições dos demais da rodada/horário (Item 5.6).
     */
    public function cancelar(Agendamento $agendamento)
    {
        if ($agendamento->status === Agendamento::STATUS_ENVIADO) {
            return back()->with('aviso', 'Agendamento já enviado não pode ser cancelado.');
        }

        $agendamento->marcarComoCancelado();

        if ($agendamento->grupo_telegram_id && $agendamento->data_hora_envio) {
            Agendamento::recalcularPosicoes(
                $agendamento->grupo_telegram_id,
                $agendamento->data_hora_envio,
                $agendamento->grupo?->modo ?? GrupoTelegram::MODO_ABERTO
            );
        }

        return back()->with('sucesso', 'Agendamento cancelado.');
    }

    /**
     * Validação do cadastro/edição de grupos (Item 2.4 + Item 4.1).
     */
    protected function regrasGrupo(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'chat_id' => ['required', 'string', 'max:60'],
            'usuario_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', PapelHierarquia::VISITANTES)),
            ],
            'tipo' => ['required', 'in:group,supergroup,channel'],
            'modo' => ['required', 'in:aberto,fechado'],
            'horario_abertura' => ['nullable', 'date_format:H:i'],
            'horario_fechamento' => ['nullable', 'date_format:H:i'],
            'horario_disparo' => ['nullable', 'date_format:H:i'],
            'dias_semana' => ['nullable', 'regex:/^[1-7](,[1-7])*$/'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function atributosGrupo(array $dados, array $extras = []): array
    {
        return array_merge([
            'nome' => $dados['nome'],
            'usuario_id' => $dados['usuario_id'],
            'tipo' => $dados['tipo'],
            'modo' => $dados['modo'] ?? GrupoTelegram::MODO_ABERTO,
            'horario_abertura' => $dados['horario_abertura'] ?? null,
            'horario_fechamento' => $dados['horario_fechamento'] ?? null,
            'horario_disparo' => $dados['horario_disparo'] ?? null,
            'dias_semana' => $dados['dias_semana'] ?? null,
        ], $extras);
    }

    /**
     * Normaliza o telefone com o DDI +55 fixo dos formulários.
     */
    protected function telefoneComDdi(string $telefone): string
    {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';

        if (str_starts_with($digitos, '55') && strlen($digitos) > 11) {
            return '+55 ' . substr($digitos, 2);
        }

        return '+55 ' . $digitos;
    }
}
