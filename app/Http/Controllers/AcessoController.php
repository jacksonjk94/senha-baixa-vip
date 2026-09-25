<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Configuracao;
use App\Models\GrupoTelegram;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AcessoController extends Controller
{
    /**
     * Etapa 1 — Tela inicial: últimos 4 dígitos do telefone.
     */
    public function acesso()
    {
        return view('agendafacil.acesso');
    }

    /**
     * Valida os 4 dígitos contra o cadastro e inicia a sessão do fluxo.
     */
    public function validarAcesso(Request $request)
    {
        $dados = $request->validate([
            'digitos' => ['required', 'digits:4'],
        ], [
            'digitos.required' => 'Informe os 4 últimos dígitos do telefone.',
            'digitos.digits' => 'Digite exatamente 4 números.',
        ]);

        $digitos = $dados['digitos'];

        // Busca pelo campo dedicado OU pelo sufixo do telefone.
        // O fallback cobre cadastros onde só o telefone foi preenchido
        // (ex.: "+55 41 98843-2148") e o campo ultimos_digitos_telefone está nulo.
        $usuario = User::where('ultimos_digitos_telefone', $digitos)
            ->orWhere('telefone', 'like', '%' . $digitos)
            ->first();

        if (! $usuario) {
            return back()
                ->withErrors(['digitos' => 'Dígitos não encontrados. Verifique e tente novamente.'])
                ->withInput();
        }

        // Guarda o visitante identificado na sessão do fluxo (sem logar no sistema).
        $request->session()->put('agendafacil.visitante_id', $usuario->id);
        $request->session()->put('agendafacil.digitos_ok', true);

        return redirect()->route('agendafacil.confirmar-dados');
    }

    /**
     * Etapa 2 — Confirmar os dados (conforme print 02.jpeg).
     * Grupo selecionável, mensagem editável (pré-preenchida no padrão
     * "Nome + xNÚMERO") e horário do disparo definível; tipo de disparo
     * e telefone são exibidos somente para conferência.
     */
    public function confirmarDados(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        $agendamento = $this->agendamentoAtual($request, $usuario);
        $grupos = GrupoTelegram::ativos()->ordenados()->get();

        return view('agendafacil.confirmar-dados', [
            'usuario' => $usuario,
            'agendamento' => $agendamento,
            'grupos' => $grupos,
            'mensagemInicial' => $this->mensagemSugerida($usuario, $agendamento),
            'horarioPadrao' => Configuracao::horarioDisparo(),
        ]);
    }

    /**
     * Salva grupo + mensagem + horário confirmados e segue para o código.
     * Conexão do bot/grupos já feita no admin.
     */
    public function salvarConfirmacao(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        $dados = $request->validate([
            'grupo_id' => ['required', 'exists:grupo_telegrams,id'],
            'mensagem' => ['required', 'string', 'max:500', 'regex:/x\s*\d+\s*$/i'],
            'data_envio' => ['required', 'date'],
            'hora_envio' => ['required', 'date_format:H:i'],
        ], [
            'grupo_id.required' => 'Selecione o grupo.',
            'grupo_id.exists' => 'Grupo selecionado inválido.',
            'mensagem.required' => 'Digite a mensagem (nome + senha).',
            'mensagem.regex' => 'A mensagem deve terminar com a senha no padrão xNÚMERO (ex: Jackson Dos Santos x40).',
            'data_envio.required' => 'Informe a data do disparo.',
            'hora_envio.required' => 'Informe o horário do disparo.',
        ]);

        $dataHora = \Carbon\Carbon::parse($dados['data_envio'] . ' ' . $dados['hora_envio']);

        if ($dataHora->isPast()) {
            return back()
                ->withErrors(['data_envio' => 'A data/hora do disparo precisa estar no futuro.'])
                ->withInput();
        }

        $grupo = GrupoTelegram::ativos()->find($dados['grupo_id']);

        if (! $grupo) {
            return back()
                ->withErrors(['grupo_id' => 'Grupo indisponível no momento.'])
                ->withInput();
        }

        $agendamento = $this->agendamentoAtual($request, $usuario);
        $mensagem = trim($dados['mensagem']);

        if (! $agendamento) {
            // O agendamento é definido no momento da confirmação:
            // cria com grupo + mensagem + horário confirmados.
            $agendamento = Agendamento::create([
                'user_id' => $usuario->id,
                'grupo_telegram_id' => $grupo->id,
                'nome_visitante' => $usuario->name,
                'senha_codigo' => static::gerarSenhaCodigo(),
                'texto' => $mensagem,
                'posicao' => Agendamento::proximaPosicao($grupo->id, $dataHora),
                'total_grupo_horario' => Agendamento::totalGrupoHorario($grupo->id, $dataHora) + 1,
                'destino_tipo' => Agendamento::DESTINO_GRUPO,
                'destino_id' => (string) $grupo->chat_id,
                'destino_nome' => $grupo->nome,
                'data_hora_envio' => $dataHora,
                'status' => Agendamento::STATUS_PENDENTE,
                'ip_origem' => $request->ip(),
            ]);
        } else {
            $agendamento->update([
                'grupo_telegram_id' => $grupo->id,
                'destino_tipo' => Agendamento::DESTINO_GRUPO,
                'destino_id' => (string) $grupo->chat_id,
                'destino_nome' => $grupo->nome,
                'texto' => $mensagem,
                'data_hora_envio' => $dataHora,
            ]);
        }

        $request->session()->put('agendafacil.agendamento_id', $agendamento->id);
        // Mantém compat com etapas antigas que liam esta chave.
        $request->session()->put('agendafacil.confirmacao', [
            'nome_visitante' => $agendamento->nome_visitante ?? $usuario->name,
            'destino_tipo' => Agendamento::DESTINO_GRUPO,
        ]);

        return redirect()->route('agendafacil.codigo');
    }

    /**
     * Etapa 3 — Código (print 04.jpeg).
     * O código é enviado diretamente no grupo definido na etapa 2
     * (conexão do bot/grupos já realizada no admin).
     */
    public function codigo(Request $request, TelegramBotService $telegram)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        $agendamento = $this->agendamentoAtual($request, $usuario);
        $grupo = $agendamento?->grupo;
        $chatId = $grupo?->chat_id
            ?? ($agendamento?->destino_tipo === 'grupo' ? $agendamento?->destino_id : null);

        if (! $request->session()->get('agendafacil.codigo')) {
            $codigo = (string) random_int(100000, 999999);
            $request->session()->put('agendafacil.codigo', $codigo);

            $enviado = false;

            if (filled($chatId) && $telegram->configurado()) {
                $envio = $telegram->enviarMensagem(
                    (string) $chatId,
                    "Seu código AgendaFácil: {$codigo}"
                );

                $enviado = (bool) ($envio['ok'] ?? false);

                if (! $envio['ok']) {
                    Log::warning('Falha ao enviar código no grupo via Telegram', ['resposta' => $envio['resposta']]);
                }
            } else {
                Log::warning('Código não enviado no grupo: sem grupo do agendamento ou bot não configurado.', [
                    'tem_agendamento' => (bool) $agendamento,
                    'chat_id' => $chatId,
                    'bot_configurado' => $telegram->configurado(),
                ]);
            }

            $request->session()->put('agendafacil.codigo_enviado_telegram', $enviado);
            $request->session()->put('agendafacil.codigo_grupo_id', $grupo?->id);
        }

        return view('agendafacil.codigo', [
            // Em produção o código chega no grupo; em dev exibimos no log/tela.
            'codigo_debug' => app()->environment('local')
                ? $request->session()->get('agendafacil.codigo')
                : null,
            'codigo_enviado_telegram' => $request->session()->get('agendafacil.codigo_enviado_telegram'),
            'grupo' => $grupo,
            'agendamento' => $agendamento,
        ]);
    }

    /**
     * Descarta o código atual para gerar e reenviar um novo no grupo.
     */
    public function reenviarCodigo(Request $request)
    {
        $request->session()->forget(['agendafacil.codigo', 'agendafacil.codigo_enviado_telegram', 'agendafacil.codigo_grupo_id']);

        return redirect()
            ->route('agendafacil.codigo')
            ->with('sucesso', 'Gerando um novo código e reenviando no grupo do Telegram...');
    }

    public function validarCodigo(Request $request)
    {
        if (! $this->visitanteAtual($request)) {
            return redirect()->route('agendafacil.acesso');
        }

        $dados = $request->validate([
            'codigo' => ['required', 'digits:6'],
        ]);

        $esperado = $request->session()->get('agendafacil.codigo');

        if (! $esperado || $dados['codigo'] !== $esperado) {
            return back()->withErrors(['codigo' => 'Código inválido. Tente novamente.']);
        }

        $request->session()->put('agendafacil.codigo_ok', true);
        $request->session()->forget('agendafacil.codigo');

        return redirect()->route('grupos.selecionar');
    }

    /**
     * Etapa 5 — Concluído (print 08.jpeg). Encerra o fluxo;
     * o restante (disparo) acontece automaticamente no horário.
     */
    public function concluido(Request $request)
    {
        if (! $this->visitanteAtual($request)) {
            return redirect()->route('agendafacil.acesso');
        }

        return view('agendafacil.concluido-unificado');
    }

    /**
     * Mensagem inicial do campo editável: mantém o texto já cadastrado
     * (quando segue o padrão "Nome + xNÚMERO"); senão pré-preenche com
     * o nome + a próxima senha da sequência do grupo (ex: "Nome x41").
     */
    protected function mensagemSugerida(User $usuario, ?Agendamento $agendamento): string
    {
        if ($agendamento && filled($agendamento->texto)
            && preg_match('/x\s*\d+\s*$/i', trim((string) $agendamento->texto))) {
            return (string) $agendamento->texto;
        }

        $nome = $agendamento?->nome_visitante ?: $usuario->name;

        return trim((string) $nome) . ' x' . Agendamento::proximaSenhaNumero($agendamento?->grupo_telegram_id);
    }

    /**
     * Próxima senha da sequência de um grupo (JSON, para atualizar a
     * mensagem ao trocar o grupo na etapa 2 sem recarregar a página).
     */
    public function proximaSenha(Request $request)
    {
        if (! $this->visitanteAtual($request)) {
            return response()->json(['erro' => 'Sessão expirada.'], 403);
        }

        $grupoId = $request->query('grupo_id');

        $grupo = $grupoId ? GrupoTelegram::ativos()->find($grupoId) : null;

        if (! $grupo) {
            return response()->json(['erro' => 'Grupo inválido.'], 422);
        }

        return response()->json(['proxima' => Agendamento::proximaSenhaNumero($grupo->id)]);
    }

    protected function visitanteAtual(Request $request): ?User
    {
        $id = $request->session()->get('agendafacil.visitante_id');

        if (! $id) {
            return null;
        }

        return User::find($id);
    }

    /**
     * Agendamento do visitante exibido no fluxo único (/acesso):
     * o mais recente (pendente primeiro). Guarda o id na sessão.
     */
    protected function agendamentoAtual(Request $request, User $usuario): ?Agendamento
    {
        $idSessao = $request->session()->get('agendafacil.agendamento_id');

        if ($idSessao) {
            $daSessao = Agendamento::with('grupo')->find($idSessao);

            if ($daSessao && (int) $daSessao->user_id === (int) $usuario->id) {
                return $daSessao;
            }
        }

        return Agendamento::with('grupo')
            ->where('user_id', $usuario->id)
            ->orderByRaw("CASE WHEN status = 'pendente' THEN 0 ELSE 1 END")
            ->latest('data_hora_envio')
            ->first();
    }

    /**
     * Gera uma senha curta para o disparo (ex.: AF-4F8K2).
     */
    public static function gerarSenhaCodigo(): string
    {
        return 'AF-' . Str::upper(Str::random(5));
    }
}
