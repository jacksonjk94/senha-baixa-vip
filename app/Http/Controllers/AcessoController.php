<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\GrupoTelegram;
use App\Models\User;
use App\Services\TelegramMtprotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcessoController extends Controller
{
    /**
     * Etapa 1 — Telefone: quem já agendou informa os 4 últimos dígitos;
     * quem é novo informa o telefone completo (+55 fixo, só DDD + número).
     */
    public function acesso()
    {
        return view('agendafacil.acesso');
    }

    /**
     * Valida a identificação e inicia a sessão do fluxo.
     */
    public function validarAcesso(Request $request)
    {
        $modo = $request->input('modo', 'novo');
        $request->session()->forget('agendafacil');

        if ($modo === 'retorno') {
            $dados = $request->validate([
                'digitos' => ['required', 'digits:4'],
            ], [
                'digitos.required' => 'Informe os 4 últimos dígitos do telefone.',
                'digitos.digits' => 'Digite exatamente 4 números.',
            ]);

            $digitos = $dados['digitos'];
            $usuario = User::where('ultimos_digitos_telefone', $digitos)
                ->orWhere('telefone', 'like', '%' . $digitos)
                ->first();

            if (! $usuario) {
                return back()
                    ->withErrors(['digitos' => 'Dígitos não encontrados. Verifique e tente novamente, ou informe o telefone completo em "Primeiro agendamento".'])
                    ->withInput(['modo' => 'retorno']);
            }

            $telefone = $this->normalizarTelefone((string) $usuario->telefone);

            if (! preg_match('/^\+[1-9]\d{7,14}$/', $telefone)) {
                // Cadastro sem telefone válido: volta para o telefone completo.
                return back()
                    ->withErrors(['digitos' => 'Não encontramos um telefone válido neste cadastro. Informe o telefone completo.'])
                    ->withInput(['modo' => 'novo', 'telefone' => '']);
            }

            $request->session()->put('agendafacil.visitante_id', $usuario->id);
            $request->session()->put('agendafacil.telefone', $telefone);
            $request->session()->put('agendafacil.acesso_em', time());

            return redirect()->route('agendafacil.codigo');
        }

        $dados = $request->validate([
            'telefone' => ['required', 'string', 'regex:/^[0-9()\s+.-]{8,20}$/'],
        ], [
            'telefone.regex' => 'Informe apenas o DDD e o número.',
        ]);

        $telefone = $this->normalizarTelefone($dados['telefone']);

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $telefone)) {
            return back()
                ->withErrors(['telefone' => 'Informe DDD + número válido.'])
                ->withInput();
        }

        $usuario = User::where('telefone', $telefone)
            ->orWhere('telefone', 'like', '%' . substr(preg_replace('/\D/', '', $telefone), -8))
            ->first();

        if ($usuario) {
            $request->session()->put('agendafacil.visitante_id', $usuario->id);
        }

        $request->session()->put('agendafacil.telefone', $telefone);
        $request->session()->put('agendafacil.acesso_em', time());

        return redirect()->route('agendafacil.codigo');
    }

    /**
     * Etapa 2 — Código: ao acessar, já dispara o envio do código do
     * Telegram para o telefone informado; 2FA só quando exigido.
     */
    public function codigo(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $telefone = $request->session()->get('agendafacil.telefone');

        if (! $telefone) {
            return redirect()->route('agendafacil.acesso');
        }

        if ($request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.horario');
        }

        $operationId = $request->session()->get('agendafacil.mtproto_operation_id');
        $enviadoEm = (int) $request->session()->get('agendafacil.codigo_enviado_em', 0);
        $expirado = $enviadoEm > 0 && (time() - $enviadoEm) > 600;
        $erroEnvio = null;

        if (\App\Models\Configuracao::modoFluxoAgendamento() === 'teste') {
            if (! $enviadoEm) {
                $enviadoEm = time();
                $request->session()->put('agendafacil.codigo_enviado_em', $enviadoEm);
            }
        } elseif (! $operationId || $expirado) {
            try {
                $resposta = $telegramMtproto->enviarCodigo($telefone);
                $request->session()->put('agendafacil.mtproto_operation_id', $resposta['operationId'] ?? null);
                $request->session()->put('agendafacil.codigo_enviado_em', time());
                $request->session()->forget('agendafacil.needs_password');
                $enviadoEm = time();
            } catch (ValidationException $e) {
                $erroEnvio = collect($e->errors())->flatten()->first();
            }
        }

        $erroEnvio = $erroEnvio ?? session('erroEnvio');

        return view('agendafacil.codigo', [
            'telefone' => $telefone,
            'needsPassword' => (bool) $request->session()->get('agendafacil.needs_password'),
            'enviadoEm' => $enviadoEm,
            'erroEnvio' => $erroEnvio,
        ]);
    }

    /**
     * Reenvio manual, respeitando 60s entre envios.
     */
    public function reenviarCodigo(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $telefone = $request->session()->get('agendafacil.telefone');

        if (! $telefone) {
            return redirect()->route('agendafacil.acesso');
        }

        $enviadoEm = (int) $request->session()->get('agendafacil.codigo_enviado_em', 0);

        if (time() - $enviadoEm < 60) {
            return redirect()->route('agendafacil.codigo')
                ->with('aviso', 'Aguarde alguns segundos antes de reenviar.');
        }

        if (\App\Models\Configuracao::modoFluxoAgendamento() === 'teste') {
            $request->session()->put('agendafacil.codigo_enviado_em', time());

            return redirect()->route('agendafacil.codigo')->with('sucesso', 'Novo código enviado (modo teste).');
        }

        try {
            $resposta = $telegramMtproto->enviarCodigo($telefone);
            $request->session()->put('agendafacil.mtproto_operation_id', $resposta['operationId'] ?? null);
            $request->session()->put('agendafacil.codigo_enviado_em', time());
            $request->session()->forget('agendafacil.needs_password');

            return redirect()->route('agendafacil.codigo')->with('sucesso', 'Novo código enviado pelo Telegram.');
        } catch (ValidationException $e) {
            return redirect()->route('agendafacil.codigo')
                ->with('erroEnvio', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Verifica o código (e a senha 2FA, quando exigida).
     */
    public function verificarCodigo(Request $request, TelegramMtprotoService $telegramMtproto)
    {
        $operationId = $request->session()->get('agendafacil.mtproto_operation_id');
        $needsPassword = (bool) $request->session()->get('agendafacil.needs_password');

        if (! $operationId) {
            return redirect()->route('agendafacil.codigo')
                ->withErrors(['telegram' => 'Solicite um novo código.']);
        }

        $dados = $request->validate($needsPassword ? [
            'senha_2fa' => ['required', 'string', 'max:256'],
        ] : [
            'codigo' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ], [
            'codigo.regex' => 'Informe o código numérico recebido do Telegram.',
        ]);

        if (\App\Models\Configuracao::modoFluxoAgendamento() === 'teste') {
            $request->session()->forget(['agendafacil.mtproto_operation_id', 'agendafacil.needs_password']);
            $request->session()->put('agendafacil.telegram_ok', true);
            $request->session()->put('agendafacil.acesso_em', time());
            $request->session()->put('agendafacil.telegram_user', [
                'id' => '0',
                'username' => 'usuario_teste',
                'firstName' => 'Usuário Teste',
            ]);
            $request->session()->put('agendafacil.telegram_grupos', [
                ['chat_id' => '-1001000000001', 'nome' => 'Grupo de Teste', 'tipo' => 'group'],
                ['chat_id' => '-1001000000002', 'nome' => 'Supergrupo de Teste', 'tipo' => 'supergroup'],
            ]);

            return redirect()->route('agendafacil.horario');
        }

        try {
            $resultado = $telegramMtproto->verificarCodigo(
                $operationId,
                (string) ($dados['codigo'] ?? ''),
                $needsPassword ? (string) ($dados['senha_2fa'] ?? '') : null
            );
        } catch (ValidationException $e) {
            return back()->withErrors(['telegram' => collect($e->errors())->flatten()->first()]);
        }

        if (! empty($resultado['needsPassword'])) {
            $request->session()->put('agendafacil.needs_password', true);

            return redirect()->route('agendafacil.codigo')
                ->with('telegram_2fa_required', true);
        }

        $request->session()->forget(['agendafacil.mtproto_operation_id', 'agendafacil.needs_password']);
        $request->session()->put('agendafacil.telegram_ok', true);
        $request->session()->put('agendafacil.acesso_em', time());
        $request->session()->put('agendafacil.telegram_user', [
            'id' => (string) ($resultado['user']['id'] ?? ''),
            'username' => $resultado['user']['username'] ?? null,
            'firstName' => $resultado['user']['firstName'] ?? null,
        ]);
        $request->session()->put('agendafacil.telegram_grupos', collect($resultado['groups'] ?? [])
            ->filter(fn ($grupo) => is_array($grupo)
                && in_array($grupo['tipo'] ?? null, ['group', 'supergroup'], true)
                && filled($grupo['chat_id'] ?? null)
                && filled($grupo['nome'] ?? null))
            ->values()
            ->all());

        return redirect()->route('agendafacil.horario');
    }

    /**
     * Etapa 3 — Horário: data e hora com as regras atuais (futuro).
     */
    public function horario(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $rascunho = $request->session()->get('agendafacil.rascunho');
        $sugestao = $this->sugestaoEnvio();

        return view('agendafacil.horario', [
            'dataMinima' => now()->format('Y-m-d'),
            'sugestaoData' => $sugestao->format('Y-m-d'),
            'sugestaoHora' => $sugestao->format('H:i'),
            'rascunho' => is_array($rascunho) ? $rascunho : [],
        ]);
    }

    public function salvarHorario(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $dados = $request->validate([
            'data_envio' => ['required', 'date'],
            'hora_envio' => ['required', 'date_format:H:i'],
        ], [
            'data_envio.required' => 'Informe a data do envio.',
            'hora_envio.required' => 'Informe o horário que será enviado.',
        ]);

        $dataHora = \Carbon\Carbon::parse($dados['data_envio'] . ' ' . $dados['hora_envio']);

        if ($dataHora->isPast()) {
            return back()->withErrors(['data_envio' => 'A data/hora do envio precisa estar no futuro.'])->withInput();
        }

        $rascunho = $request->session()->get('agendafacil.rascunho', []);
        $request->session()->put('agendafacil.rascunho', array_merge(is_array($rascunho) ? $rascunho : [], [
            'data_envio' => $dados['data_envio'],
            'hora_envio' => $dados['hora_envio'],
        ]));

        return redirect()->route('grupos.selecionar');
    }

    /**
     * Etapa 5 — Confirmação: exibe todos os dados de forma elegante.
     */
    public function confirmarDados(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $rascunho = $request->session()->get('agendafacil.rascunho');
        $grupo = $request->session()->get('agendafacil.grupo');

        if (! is_array($rascunho) || empty($rascunho['data_envio']) || ! is_array($grupo)) {
            return redirect()->route('grupos.selecionar');
        }

        return view('agendafacil.confirmar-dados', [
            'telefone' => $request->session()->get('agendafacil.telefone'),
            'telegramUser' => $request->session()->get('agendafacil.telegram_user'),
            'grupo' => $grupo,
            'rascunho' => $rascunho,
            'mensagemInicial' => $rascunho['mensagem'] ?? ($request->session()->get('agendafacil.telegram_user.firstName') ?: 'Visitante'),
        ]);
    }

    /**
     * Confirmação final: verifica/cadastra o visitante, sincroniza os
     * grupos e cria o agendamento pendente.
     */
    public function confirmarResumo(Request $request)
    {
        if (! $request->session()->get('agendafacil.telegram_ok')) {
            return redirect()->route('agendafacil.codigo');
        }

        $rascunho = $request->session()->get('agendafacil.rascunho');
        $grupoSel = $request->session()->get('agendafacil.grupo');
        $telegramUser = $request->session()->get('agendafacil.telegram_user');

        if (! is_array($rascunho) || empty($rascunho['data_envio']) || ! is_array($grupoSel)) {
            return redirect()->route('grupos.selecionar');
        }

        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'max:200'],
        ], [
            'mensagem.required' => 'Digite a mensagem que aparecerá no grupo.',
            'mensagem.max' => 'A mensagem deve ter no máximo 200 caracteres.',
        ]);

        $telegramId = (string) ($telegramUser['id'] ?? '');
        $telefone = (string) $request->session()->get('agendafacil.telefone');
        $nome = trim((string) ($telegramUser['firstName'] ?? 'Visitante'));

        if (\App\Models\Configuracao::modoFluxoAgendamento() === 'teste') {
            $dataHora = \Carbon\Carbon::parse($rascunho['data_envio'] . ' ' . $rascunho['hora_envio']);
            $request->session()->put('agendafacil.agendamento_teste', [
                'destino_nome' => $grupoSel['nome'] ?? 'Grupo de Teste',
                'data_hora_envio' => $dataHora->toDateTimeString(),
                'mensagem' => trim($dados['mensagem']),
            ]);

            return redirect()->route('agendafacil.concluido');
        }

        $usuario = User::where('telegram_id', $telegramId)->first()
            ?? User::where('telefone', $telefone)->first()
            ?? User::where('telefone', 'like', '%' . substr((string) preg_replace('/\D/', '', $telefone), -8))->first();

        if (! $usuario) {
            $usuario = User::create([
                'name' => $nome ?: 'Visitante',
                'telefone' => $telefone,
                'telegram_id' => $telegramId !== '' ? $telegramId : null,
                'telegram_username' => $telegramUser['username'] ?? null,
            ]);
        } else {
            $usuario->fill([
                'telefone' => $usuario->telefone ?: $telefone,
                'telegram_id' => $usuario->telegram_id ?: ($telegramId !== '' ? $telegramId : null),
            ])->save();
        }

        $this->sincronizarGrupos($usuario, $request->session()->get('agendafacil.telegram_grupos', []));

        $grupo = GrupoTelegram::where('usuario_id', $usuario->id)
            ->where('chat_id', (string) $grupoSel['chat_id'])
            ->first();

        if (! $grupo) {
            return redirect()->route('grupos.selecionar')
                ->withErrors(['grupo_id' => 'Grupo indisponível no momento.']);
        }

        $dataHora = \Carbon\Carbon::parse($rascunho['data_envio'] . ' ' . $rascunho['hora_envio']);

        if ($dataHora->isPast()) {
            return redirect()->route('agendafacil.horario')
                ->withErrors(['data_envio' => 'A data/hora do envio precisa estar no futuro.']);
        }

        $posicao = Agendamento::proximaPosicao($grupo->id, $dataHora);
        $total = Agendamento::totalGrupoHorario($grupo->id, $dataHora) + 1;

        $agendamento = Agendamento::create([
            'user_id' => $usuario->id,
            'grupo_telegram_id' => $grupo->id,
            'nome_visitante' => $usuario->name,
            'senha_codigo' => static::gerarSenhaCodigo(),
            'texto' => trim($dados['mensagem']),
            'posicao' => $posicao,
            'total_grupo_horario' => $total,
            'destino_tipo' => Agendamento::DESTINO_GRUPO,
            'destino_id' => (string) $grupo->chat_id,
            'destino_nome' => $grupo->nome,
            'data_hora_envio' => $dataHora,
            'status' => Agendamento::STATUS_PENDENTE,
            'ip_origem' => $request->ip(),
        ]);

        $request->session()->put('agendafacil.visitante_id', $usuario->id);
        $request->session()->put('agendafacil.agendamento_id', $agendamento->id);
        $request->session()->forget(['agendafacil.rascunho', 'agendafacil.grupo']);

        return redirect()->route('agendafacil.concluido');
    }

    /**
     * Sincroniza os grupos da conta Telegram na tabela do visitante
     * (mesma lógica do fluxo antigo, mantém config existente).
     */
    protected function sincronizarGrupos(User $usuario, array $grupos): void
    {
        $validos = collect($grupos)
            ->filter(fn ($grupo) => is_array($grupo)
                && in_array($grupo['tipo'] ?? null, ['group', 'supergroup'], true)
                && filled($grupo['chat_id'] ?? null)
                && filled($grupo['nome'] ?? null))
            ->keyBy(fn ($grupo) => (string) $grupo['chat_id']);

        $usuario->gruposTelegram()
            ->whereNotIn('chat_id', $validos->keys()->all())
            ->update(['ativo' => false]);

        foreach ($validos as $grupo) {
            $chatId = (string) $grupo['chat_id'];
            $existente = $usuario->gruposTelegram()->where('chat_id', $chatId)->first();

            if ($existente) {
                $existente->update([
                    'nome' => mb_substr((string) $grupo['nome'], 0, 150),
                    'tipo' => $grupo['tipo'],
                    'ativo' => true,
                ]);

                continue;
            }

            $configuracao = GrupoTelegram::where('chat_id', $chatId)->orderBy('id')->first();

            $usuario->gruposTelegram()->create([
                'nome' => mb_substr((string) $grupo['nome'], 0, 150),
                'chat_id' => mb_substr($chatId, 0, 60),
                'tipo' => $grupo['tipo'],
                'modo' => $configuracao?->modo ?? GrupoTelegram::MODO_ABERTO,
                'horario_abertura' => $configuracao?->horario_abertura?->format('H:i'),
                'horario_fechamento' => $configuracao?->horario_fechamento?->format('H:i'),
                'horario_disparo' => $configuracao?->horario_disparo?->format('H:i'),
                'dias_semana' => $configuracao?->dias_semana,
                'ordem' => $configuracao?->ordem ?? 0,
                'ativo' => true,
            ]);
        }
    }

    /**
     * Etapa 6 — Concluído.
     */
    public function concluido(Request $request)
    {
        $agendamentoId = $request->session()->get('agendafacil.agendamento_id');
        $agendamento = $agendamentoId ? Agendamento::find($agendamentoId) : null;

        if (! $agendamento && \App\Models\Configuracao::modoFluxoAgendamento() === 'teste') {
            $teste = $request->session()->get('agendafacil.agendamento_teste');
            if (is_array($teste)) {
                $agendamento = new Agendamento([
                    'destino_nome' => $teste['destino_nome'] ?? 'Grupo de Teste',
                    'data_hora_envio' => $teste['data_hora_envio'] ?? null,
                    'texto' => $teste['mensagem'] ?? null,
                ]);
            }
        }

        return view('agendafacil.concluido-unificado', compact('agendamento'));
    }

    protected function normalizarTelefone(string $telefone): string
    {
        $digitos = preg_replace('/\D/', '', $telefone);

        if (strlen($digitos) <= 11 && ! str_starts_with($digitos, '55')) {
            $digitos = '55' . $digitos;
        }

        return '+' . $digitos;
    }

    protected function sugestaoEnvio(): \Carbon\Carbon
    {
        $base = now()->addMinutes(30)->startOfMinute();
        $resto = ((int) $base->format('i')) % 10;

        if ($resto !== 0) {
            $base->addMinutes(10 - $resto);
        }

        return $base;
    }

    public static function gerarSenhaCodigo(): string
    {
        return 'AF-' . Str::upper(Str::random(5));
    }
}
