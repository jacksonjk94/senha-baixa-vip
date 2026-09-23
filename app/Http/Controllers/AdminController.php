<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use Illuminate\Http\Request;

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
     * Cadastros (visitantes/dados).
     */
    public function cadastros(Request $request)
    {
        $busca = trim((string) $request->query('busca', ''));

        $usuarios = User::query()
            ->when($busca !== '', fn ($q) => $q
                ->where('name', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.cadastros', compact('usuarios', 'busca'));
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
     * Gerenciamento dos destinos (grupos).
     */
    public function grupos(Request $request)
    {
        $grupos = GrupoTelegram::with('usuario')
            ->orderBy('nome')
            ->paginate(15);

        return view('admin.grupos', compact('grupos'));
    }

    public function armazenarGrupo(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'chat_id' => ['required', 'string', 'max:60'],
            'tipo' => ['required', 'in:group,supergroup,channel'],
        ]);

        GrupoTelegram::updateOrCreate(
            ['chat_id' => $dados['chat_id'], 'usuario_id' => $request->user()->id],
            ['nome' => $dados['nome'], 'tipo' => $dados['tipo'], 'ativo' => true]
        );

        return back()->with('sucesso', 'Grupo cadastrado com sucesso!');
    }

    public function alternarGrupo(GrupoTelegram $grupo)
    {
        $grupo->update(['ativo' => ! $grupo->ativo]);

        return back()->with('sucesso', 'Status do grupo atualizado.');
    }

    public function atualizarStatus(Request $request, Agendamento $agendamento)
    {
        $dados = $request->validate([
            'status' => ['required', 'in:pendente,enviado,erro'],
        ]);

        $agendamento->update(['status' => $dados['status']]);

        return back()->with('sucesso', 'Status atualizado com sucesso!');
    }
}
