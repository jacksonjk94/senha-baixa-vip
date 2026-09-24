<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Etapa 2 — Confirmação de dados (nome, grupo, horário, tipo de disparo).
     */
    public function confirmarDados(Request $request)
    {
        $usuario = $this->visitanteAtual($request);

        if (! $usuario) {
            return redirect()->route('agendafacil.acesso');
        }

        return view('agendafacil.confirmar-dados', compact('usuario'));
    }

    /**
     * Salva os dados confirmados na sessão e segue para conexão Telegram.
     */
    public function salvarConfirmacao(Request $request)
    {
        $dados = $request->validate([
            'nome_visitante' => ['required', 'string', 'max:120'],
            'destino_tipo' => ['required', 'in:grupo,pessoa'],
        ]);

        $request->session()->put('agendafacil.confirmacao', $dados);

        $visitante = $this->visitanteAtual($request);

        // Se o usuário do sistema já está logado e com Telegram, pula para o código.
        if (Auth::check() && Auth::user()->telegramConectado()) {
            return redirect()->route('agendafacil.codigo');
        }

        // Se o visitante já tem Telegram vinculado, segue para validação por código.
        if ($visitante && filled($visitante->telegram_id)) {
            return redirect()->route('agendafacil.codigo');
        }

        // Conexão do painel admin: com @username cadastrado, a etapa código
        // vincula e envia sozinha — pula o widget.
        if ($visitante && filled($visitante->telegram_username)) {
            return redirect()->route('agendafacil.codigo');
        }

        return redirect()->route('telegram.connect');
    }

    /**
     * Etapa 4 — Validação por código de confirmação (6 dígitos enviados via sessão).
     */
    public function codigo(Request $request, TelegramBotService $telegram)
    {
        // Vinculação automática: usa a conexão do bot padrão (painel admin)
        // para descobrir o chat_id pelo @username do visitante, sem widget.
        $visitanteSessao = $this->visitanteAtual($request);

        if ($visitanteSessao && ! filled($visitanteSessao->telegram_id) && filled($visitanteSessao->telegram_username) && $telegram->configurado()) {
            $chatId = $telegram->resolverChatIdPorUsername($visitanteSessao->telegram_username);

            if ($chatId) {
                $visitanteSessao->forceFill(['telegram_id' => $chatId])->save();
                $request->session()->put('agendafacil.telegram_ok', true);
                Log::info('Visitante vinculado ao Telegram automaticamente', ['chat_id' => $chatId]);
            }
        }

        if (! $request->session()->get('agendafacil.codigo')) {
            $codigo = (string) random_int(100000, 999999);
            $request->session()->put('agendafacil.codigo', $codigo);

            // Envia o código via bot ao Telegram conectado (visitante ou logado).
            // Sem isso, em produção o usuário nunca receberia o código.
            $destinatario = $this->visitanteAtual($request) ?? Auth::user();

            if ($destinatario && filled($destinatario->telegram_id) && $telegram->configurado()) {
                $envio = $telegram->enviarMensagem(
                    (string) $destinatario->telegram_id,
                    "Seu código AgendaFácil: {$codigo}"
                );

                $request->session()->put('agendafacil.codigo_enviado_telegram', $envio['ok']);

                if (! $envio['ok']) {
                    Log::warning('Falha ao enviar código via Telegram', ['resposta' => $envio['resposta']]);
                }
            } else {
                $request->session()->put('agendafacil.codigo_enviado_telegram', false);
            }
        }

        return view('agendafacil.codigo', [
            // Em produção o código é enviado pelo bot; em dev exibimos mascarado no log.
            'codigo_debug' => app()->environment('local')
                ? $request->session()->get('agendafacil.codigo')
                : null,
            'codigo_enviado_telegram' => $request->session()->get('agendafacil.codigo_enviado_telegram'),
            // Conexão do painel admin para orientar o visitante.
            'botUsername' => $telegram->botUsername(),
            'botConfigurado' => $telegram->configurado(),
            'visitanteTelegram' => $this->visitanteAtual($request)?->telegram_username,
            'visitanteConectado' => filled($this->visitanteAtual($request)?->telegram_id) || filled(Auth::user()?->telegram_id),
        ]);
    }

    public function validarCodigo(Request $request)
    {
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

    protected function visitanteAtual(Request $request): ?User
    {
        $id = $request->session()->get('agendafacil.visitante_id');

        if (! $id) {
            return null;
        }

        return User::find($id);
    }

    /**
     * Gera uma senha curta para o disparo (ex.: AF-4F8K2).
     */
    public static function gerarSenhaCodigo(): string
    {
        return 'AF-' . Str::upper(Str::random(5));
    }
}
