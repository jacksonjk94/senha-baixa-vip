<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use App\Services\TelegramMtprotoService;
use Illuminate\Http\Request;
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

        // Uma nova identificação não pode herdar autenticação ou agendamento
        // de outro visitante que tenha usado a mesma sessão do navegador.
        $request->session()->forget('agendafacil');
        // Guarda o visitante identificado na sessão do fluxo (sem logar no sistema).
        $request->session()->put('agendafacil.visitante_id', $usuario->id);
        $request->session()->put('agendafacil.digitos_ok', true);
        $request->session()->put('agendafacil.acesso_em', time());

        return redirect()->route('agendafacil.codigo');
    }

    /**
    * Etapa 3 — Confirmar os dados (conforme print 02.jpeg).
     * Grupo selecionável, mensagem editável e horário do disparo
     * definível; tipo de disparo e telefone são exibidos somente
     * para conferência.
     */
    public function confirmarDados(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $agendamento = $this->agendamentoAtual($request, $usuario);
        $grupos = GrupoTelegram::where('usuario_id', $usuario->id)
            ->ativos()
            ->ordenados()
            ->get();
        $sugestao = $this->sugestaoEnvio();
        $rascunho = $this->rascunhoLimpo($request);

        return view('agendafacil.confirmar-dados', [
            'usuario' => $usuario,
            'agendamento' => $agendamento,
            'grupos' => $grupos,
            'mensagemInicial' => $this->mensagemSugerida($usuario, $agendamento),
            'sugestaoData' => $sugestao->format('Y-m-d'),
            'sugestaoHora' => $sugestao->format('H:i'),
            // Data mínima selecionável (hoje): dias anteriores ficam
            // indisponíveis no input date.
            'dataMinima' => now()->format('Y-m-d'),
            'rascunho' => $rascunho,
        ]);
    }

    /**
     * Rascunho da sessão com a mensagem sem o sufixo legado "xN"
     * (ex.: "Jackson x1" → "Jackson"). Cura rascunhos gravados antes
     * da remoção do parâmetro.
     */
    protected function rascunhoLimpo(Request $request): ?array
    {
        $rascunho = $request->session()->get('agendafacil.rascunho');

        if (is_array($rascunho) && isset($rascunho['mensagem'])) {
            $rascunho['mensagem'] = $this->semSufixoSenha((string) $rascunho['mensagem']);
        }

        return $rascunho;
    }

    /**
     * Sugestão inicial de envio: agora + 30min, arredondado para cima até
     * a próxima dezena de minuto (ex.: 16:56 → 17:30). Minutagem inteira
     * e sempre no futuro (atravessa a virada do dia).
     */
    protected function sugestaoEnvio(): \Carbon\Carbon
    {
        $base = now()->addMinutes(30)->startOfMinute();
        $resto = ((int) $base->format('i')) % 10;

        if ($resto !== 0) {
            $base->addMinutes(10 - $resto);
        }

        return $base;
    }

    /**
    * Etapa 3 (passo 1) — Valida grupo + mensagem + data/hora e exibe a
     * tela de resumo (Item 6.2) antes da confirmação final.
     * Regras por modo do grupo (Item 4.4): fechado fixa o horário de
     * envio e respeita abertura/fechamento; aberto usa data/hora livres.
     */
    public function salvarConfirmacao(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        // O parâmetro xN foi removido: "Jackson x1" digitado ou vindo de
        // rascunho legado vira "Jackson".
        $request->merge([
            'mensagem' => $this->semSufixoSenha((string) $request->input('mensagem', '')),
        ]);

        $dados = $request->validate([
            'grupo_id' => ['required', 'exists:grupo_telegrams,id'],
            'mensagem' => ['required', 'string', 'max:200'],
            'data_envio' => ['required', 'date'],
            'hora_envio' => ['required', 'date_format:H:i'],
        ], [
            'grupo_id.required' => 'Selecione o grupo.',
            'grupo_id.exists' => 'Grupo selecionado inválido.',
            'mensagem.required' => 'Digite a mensagem que aparecerá no grupo.',
            'mensagem.max' => 'A mensagem deve ter no máximo 200 caracteres.',
            'data_envio.required' => 'Informe a data do envio.',
            'hora_envio.required' => 'Informe o horário que será enviado.',
        ]);

        $grupo = GrupoTelegram::where('usuario_id', $usuario->id)
            ->ativos()
            ->find($dados['grupo_id']);

        if (! $grupo) {
            return back()
                ->withErrors(['grupo_id' => 'Grupo indisponível no momento.'])
                ->withInput();
        }

        $resolucao = $this->resolverEnvio($grupo, $dados['data_envio'], $dados['hora_envio']);

        if (isset($resolucao['erro'])) {
            return back()->withErrors($resolucao['erro'])->withInput();
        }

        $agendamento = $this->agendamentoPendenteParaData($request, $usuario, $resolucao['data_hora']);
        $excetoId = $agendamento?->id;

        if ($grupo->ehFechado()) {
            $posicao = Agendamento::proximaPosicaoRodada($grupo->id, $resolucao['data_hora'], $excetoId);
            $total = Agendamento::totalRodada($grupo->id, $resolucao['data_hora'], $excetoId) + 1;
        } else {
            $posicao = Agendamento::proximaPosicao($grupo->id, $resolucao['data_hora'], $excetoId);
            $total = Agendamento::totalGrupoHorario($grupo->id, $resolucao['data_hora'], $excetoId) + 1;
        }

        $request->session()->put('agendafacil.rascunho', [
            'grupo_id' => $grupo->id,
            'mensagem' => trim($dados['mensagem']),
            'data_envio' => $dados['data_envio'],
            'hora_envio' => $dados['hora_envio'],
            'data_hora_envio' => $resolucao['data_hora']->format('Y-m-d H:i:s'),
            'posicao' => $posicao,
            'total' => $total,
            'proxima_rodada' => $resolucao['proxima_rodada'],
        ]);

        return redirect()->route('agendafacil.resumo');
    }

    /**
    * Etapa 3 (passo 2) — Tela de resumo antes da confirmação final.
     */
    public function resumo(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $rascunho = $this->rascunhoLimpo($request);

        if (! $rascunho) {
            return redirect()->route('agendafacil.confirmar-dados');
        }

        $grupo = GrupoTelegram::where('usuario_id', $usuario->id)
            ->ativos()
            ->find($rascunho['grupo_id']);

        if (! $grupo) {
            return redirect()->route('agendafacil.confirmar-dados')
                ->withErrors(['grupo_id' => 'Grupo indisponível no momento.']);
        }

        return view('agendafacil.confirmar-resumo', compact('usuario', 'grupo', 'rascunho'));
    }

    /**
    * Etapa 3 (passo 2) — Confirmação final: cria/atualiza o agendamento
    * (definido no momento da confirmação) e segue para a seleção de grupos.
     */
    public function confirmarResumo(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $rascunho = $this->rascunhoLimpo($request);

        if (! $rascunho) {
            return redirect()->route('agendafacil.confirmar-dados');
        }

        $grupo = GrupoTelegram::where('usuario_id', $usuario->id)
            ->ativos()
            ->find($rascunho['grupo_id']);

        if (! $grupo) {
            return redirect()->route('agendafacil.confirmar-dados')
                ->withErrors(['grupo_id' => 'Grupo indisponível no momento.']);
        }

        // Recalcula no momento da confirmação (a rodada pode ter mudado).
        $resolucao = $this->resolverEnvio($grupo, $rascunho['data_envio'], $rascunho['hora_envio']);

        if (isset($resolucao['erro'])) {
            return redirect()->route('agendafacil.confirmar-dados')
                ->withErrors($resolucao['erro']);
        }

        $agendamento = $this->agendamentoPendenteParaData($request, $usuario, $resolucao['data_hora']);
        $excetoId = $agendamento?->id;

        if ($grupo->ehFechado()) {
            $posicao = Agendamento::proximaPosicaoRodada($grupo->id, $resolucao['data_hora'], $excetoId);
            $total = Agendamento::totalRodada($grupo->id, $resolucao['data_hora'], $excetoId) + 1;
        } else {
            $posicao = Agendamento::proximaPosicao($grupo->id, $resolucao['data_hora'], $excetoId);
            $total = Agendamento::totalGrupoHorario($grupo->id, $resolucao['data_hora'], $excetoId) + 1;
        }

        // A mensagem vai sem o sufixo legado xN.
        $textoFinal = $this->semSufixoSenha((string) $rascunho['mensagem']);

        if (! $agendamento) {
            $agendamento = Agendamento::create([
                'user_id' => $usuario->id,
                'grupo_telegram_id' => $grupo->id,
                'nome_visitante' => $usuario->name,
                'senha_codigo' => static::gerarSenhaCodigo(),
                'texto' => $textoFinal,
                'posicao' => $posicao,
                'total_grupo_horario' => $total,
                'destino_tipo' => Agendamento::DESTINO_GRUPO,
                'destino_id' => (string) $grupo->chat_id,
                'destino_nome' => $grupo->nome,
                'data_hora_envio' => $resolucao['data_hora'],
                'status' => Agendamento::STATUS_PENDENTE,
                'ip_origem' => $request->ip(),
            ]);
        } else {
            $agendamento->update([
                'grupo_telegram_id' => $grupo->id,
                'destino_tipo' => Agendamento::DESTINO_GRUPO,
                'destino_id' => (string) $grupo->chat_id,
                'destino_nome' => $grupo->nome,
                'texto' => $textoFinal,
                'posicao' => $posicao,
                'total_grupo_horario' => $total,
                'data_hora_envio' => $resolucao['data_hora'],
            ]);
        }

        if ($resolucao['proxima_rodada']) {
            $request->session()->flash(
                'aviso',
                'Este grupo já fechou hoje. Você estará na 1ª posição da próxima rodada (' . $resolucao['data_hora']->format('d/m') . ').'
            );
        }

        $request->session()->forget('agendafacil.rascunho');
        $request->session()->put('agendafacil.agendamento_id', $agendamento->id);
        // Mantém compat com etapas antigas que liam esta chave.
        $request->session()->put('agendafacil.confirmacao', [
            'nome_visitante' => $agendamento->nome_visitante ?? $usuario->name,
            'destino_tipo' => Agendamento::DESTINO_GRUPO,
        ]);

        return redirect()->route('grupos.selecionar');
    }

    /**
     * Resolve data/hora final do envio conforme o modo do grupo.
     * Retorna ['data_hora' => Carbon, 'proxima_rodada' => bool]
     * ou ['erro' => [campo => mensagem]].
     */
    protected function resolverEnvio(GrupoTelegram $grupo, string $dataEnvio, string $horaEnvio): array
    {
        $agora = now();

        if ($grupo->ehFechado()) {
            $disparo = $grupo->disparoEm($agora);

            if (! $disparo) {
                return ['erro' => ['grupo_id' => 'Este grupo ainda não tem horário de envio configurado. Entre em contato.']];
            }

            $hoje = $agora->copy()->startOfDay();
            $escolhido = \Carbon\Carbon::parse($dataEnvio)->startOfDay();

            if ($escolhido->lt($hoje)) {
                return ['erro' => ['data_envio' => 'A data do envio precisa ser hoje ou futura.']];
            }

            if (! $grupo->funcionaEm($escolhido)) {
                return ['erro' => ['data_envio' => 'Este grupo não funciona neste dia. Escolha outro dia.']];
            }

            $proximaRodada = $escolhido->gt($hoje);

            if (! $proximaRodada) {
                $abertura = $grupo->aberturaEm($hoje);
                $fechamento = $grupo->fechamentoEm($hoje);

                if ($abertura && $agora->lt($abertura)) {
                    return ['erro' => ['grupo_id' => 'Este grupo abre às ' . $abertura->format('H:i') . '. Volte neste horário para agendar.']];
                }

                if ($fechamento && $agora->gt($fechamento)) {
                    $escolhido = $grupo->proximoDiaValido($hoje->copy()->addDay());
                    $proximaRodada = true;
                }
            }

            return [
                'data_hora' => $grupo->disparoEm($escolhido),
                'proxima_rodada' => $proximaRodada,
            ];
        }

        $dataHora = \Carbon\Carbon::parse($dataEnvio . ' ' . $horaEnvio);

        if ($dataHora->isPast()) {
            return ['erro' => ['data_envio' => 'A data/hora do envio precisa estar no futuro.']];
        }

        if (! $grupo->funcionaEm($dataHora)) {
            return ['erro' => ['data_envio' => 'Este grupo não funciona neste dia. Escolha outro dia.']];
        }

        return ['data_hora' => $dataHora, 'proxima_rodada' => false];
    }

    /**
    * Etapa 2 — Confirmação pelo próprio Telegram (Login Widget).
     * O código de 6 dígitos no grupo segue disponível no backend como
     * fallback desativado (validar/reenviar), sem envio automático.
     */
    public function codigo(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        $agendamento = $this->agendamentoAtual($request, $usuario);
        $grupo = $agendamento?->grupo;

        // Confirmação pelo próprio Telegram (Login Widget, preferencial).
        $bot = TelegramValidacaoController::dadosBotValidacao();

        return view('agendafacil.codigo', [
            'grupo' => $grupo,
            'agendamento' => $agendamento,
            'botUsername' => $bot['username'],
            'temBot' => $bot['temBot'],
            'usandoEnv' => $bot['usandoEnv'],
            'dominio' => $bot['dominio'],
            'confirmarUrl' => route('telegram.confirmar-callback'),
            'mtprotoConfigurado' => $telegramMtproto->configurado(),
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
     * Etapa 5 — Concluído (print 08.jpeg). Encerra o fluxo com o resumo
     * do envio + posição; o restante acontece automaticamente no horário.
     */
    public function concluido(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        $agendamento = $this->agendamentoAtual($request, $usuario);

        return view('agendafacil.concluido-unificado', compact('agendamento'));
    }

    /**
     * Mensagem inicial do campo editável: texto já cadastrado sem o
     * sufixo legado "xN" (ex.: "Jackson x1" → "Jackson");
     * senão pré-preenche com o nome do visitante.
     */
    protected function mensagemSugerida(User $usuario, ?Agendamento $agendamento): string
    {
        if ($agendamento && filled($agendamento->texto)) {
            return $this->semSufixoSenha((string) $agendamento->texto);
        }

        $nome = $agendamento?->nome_visitante ?: $usuario->name;

        return $this->semSufixoSenha((string) $nome);
    }

    /**
     * Remove o sufixo legado de senha ("xNÚMERO" ao final, ex.: " x1").
     */
    protected function semSufixoSenha(string $mensagem): string
    {
        return trim((string) preg_replace('/\s*x\s*\d+\s*$/i', '', trim($mensagem)));
    }

    protected function visitanteAtual(Request $request): ?User
    {
        $id = $request->session()->get('agendafacil.visitante_id');

        if (! $id) {
            return null;
        }

        // Sessão local do fluxo vale 2h (validação por widget ou 4 dígitos).
        $acessoEm = (int) $request->session()->get('agendafacil.acesso_em', 0);
        if ($acessoEm > 0 && (time() - $acessoEm) > TelegramValidacaoController::VALIDADE_SEGUNDOS) {
            $request->session()->forget('agendafacil');

            return null;
        }

        return User::find($id);
    }

    /**
     * Agendamento do visitante exibido no fluxo (/acesso, /codigo,
     * /concluido): somente pendentes. Nunca reaproveita agendamento já
     * disparado (enviado), com erro ou cancelado — um novo fluxo parte
     * de sugestão nova (hoje + 30min) e só atualiza pendente do mesmo dia.
     */
    protected function agendamentoAtual(Request $request, User $usuario): ?Agendamento
    {
        $idSessao = $request->session()->get('agendafacil.agendamento_id');

        if ($idSessao) {
            $daSessao = Agendamento::with('grupo')->find($idSessao);

            if ($daSessao
                && (int) $daSessao->user_id === (int) $usuario->id
                && $daSessao->status === Agendamento::STATUS_PENDENTE) {
                return $daSessao;
            }
        }

        return Agendamento::with('grupo')
            ->where('user_id', $usuario->id)
            ->where('status', Agendamento::STATUS_PENDENTE)
            ->latest('data_hora_envio')
            ->first();
    }

    /**
     * Regra de criação vs. atualização:
     * - mesmo dia (data do envio) + pendente do mesmo visitante → atualiza
     *   o horário (reaproveita o registro);
     * - data diferente → retorna null para cadastrar um novo agendamento;
     * - enviado/erro/cancelado nunca são reaproveitados.
     */
    protected function agendamentoPendenteParaData(Request $request, User $usuario, \DateTimeInterface $dataHora): ?Agendamento
    {
        $dia = \Carbon\Carbon::parse($dataHora)->toDateString();

        $idSessao = $request->session()->get('agendafacil.agendamento_id');

        if ($idSessao) {
            $daSessao = Agendamento::find($idSessao);

            if ($daSessao
                && (int) $daSessao->user_id === (int) $usuario->id
                && $daSessao->status === Agendamento::STATUS_PENDENTE
                && \Carbon\Carbon::parse($daSessao->data_hora_envio)->toDateString() === $dia) {
                return $daSessao->load('grupo');
            }
        }

        return Agendamento::with('grupo')
            ->where('user_id', $usuario->id)
            ->where('status', Agendamento::STATUS_PENDENTE)
            ->whereDate('data_hora_envio', $dia)
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
