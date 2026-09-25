# PROMPT — Criar Rota de Disparos para Cron (n8n Worker)

**Arquivo:** 2026-09-25_prompt-rota-disparos-cron.md
**Data:** 25/09/2026 · Curitiba/PR
**Projeto:** Sistema de Agendamento e Disparo Automático via Telegram (Laravel)
**Objetivo:** Criar uma rota protegida no Laravel que será chamada a cada minuto pelo n8n (fazendo o papel de Worker). A rota processa os agendamentos pendentes com horário atingido, envia as mensagens via Telegram Bot API e atualiza os status.

> **Instrução para o agente (OpenCode):** Leia todo o projeto existente antes de implementar. Mantenha os padrões já adotados (models, controllers, rotas, config). Não sobrescreva funcionalidades existentes; adicione a rota e o controller descritos abaixo. Se algum campo/modelo não existir, crie a migration correspondente.

---

## 🎯 CONTEXTO

O sistema roda em ambiente serverless/compartilhado (Vercel ou similar), onde `queue:work` não funciona. Por isso, o **n8n** fará o papel de cron/Worker: a cada minuto ele chama uma rota do Laravel, e o Laravel executa toda a lógica de disparo.

```
⏰ n8n (a cada minuto) → HTTP GET → Rota Laravel protegida
     ↓
🌐 Laravel: busca pendentes → envia Telegram → atualiza status → retorna JSON
     ↓
🤖 n8n registra resultado (log/monitoramento)
```

---

## ✅ TAREFA 1 — Variável de Ambiente e Config

### 1.1 Adicionar ao `.env` e ao `.env.example`
```env
# Chave secreta que o n8n envia no cabeçalho para autorizar a chamada
N8N_WORKER_SECRET=coloque_uma_string_longa_aleatoria_aqui
```

### 1.2 Adicionar em `config/services.php`
```php
'n8n' => [
    'secret' => env('N8N_WORKER_SECRET'),
],
```
> Também confirmar que existe `'telegram' => ['client_secret' => env('TELEGRAM_TOKEN')]` em `config/services.php` (ou equivalente) para o token do bot.

---

## ✅ TAREFA 2 — Rota Protegida

### 2.1 Arquivo: `routes/api.php`
Criar rota **GET** (para facilitar a chamada do n8n):

```php
use App\Http\Controllers\N8nDisparoController;

Route::get('/n8n/disparos/processar', [N8nDisparoController::class, 'processar'])
    ->name('n8n.disparos.processar');
```

> **Importante:** NÃO colocar esta rota dentro de middleware `auth` — o n8n não faz login. A proteção é feita por chave secreta no cabeçalho (ver Tarefa 3).

### 2.2 URL final
`https://seu-dominio.com/api/n8n/disparos/processar`

---

## ✅ TAREFA 3 — Controller com a Lógica Completa

Criar `app/Http/Controllers/N8nDisparoController.php` com o método `processar(Request $request)`.

### 3.1 Fluxo do método

1. **Validar chave secreta** recebida no cabeçalho `X-N8N-Secret`. Se não bater com `config('services.n8n.secret')`, retornar `403 Forbidden` com JSON `{ "erro": "Acesso negado" }`.
2. **Buscar agendamentos pendentes** com `data_hora_envio <= agora` (fuso `America/Sao_Paulo`), ordenados por `data_hora_envio` ASC (ordem de chegada = posição), com `limit(50)` para evitar sobrecarga.
3. **Para cada agendamento:**
   - Carregar o relacionamento com `grupo` (precisa de `chat_id` e `tipo`).
   - **Regra de Grupo Fechado:** se o grupo for `fechado` e ainda não chegou o `horario_disparo` do grupo, **não enviar** — pular para o próximo (mantém `pendente`). Só envia quando o horário de disparo do grupo for atingido.
   - **Enviar mensagem** via Telegram Bot API: `POST https://api.telegram.org/bot{TOKEN}/sendMessage` com `chat_id` (do grupo) e `text` (campo mensagem do agendamento).
   - Se sucesso: calcular `posicao` (contagem de já enviados no mesmo grupo + mesmo `data_hora_envio` + 1) e `total` (contagem total de agendamentos no mesmo grupo + mesmo `data_hora_envio`). Atualizar: `status = 'enviado'`, `enviado_em = agora`, `posicao`, `total`.
   - Se falha: atualizar `status = 'erro'` e `mensagem_erro = mensagem da exceção`.
4. **Retornar JSON** com resumo: `{ executado_em, processados, enviados, erros }`.

### 3.2 Estrutura de referência (adaptar ao padrão do projeto)

```php
<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class N8nDisparoController extends Controller
{
    public function processar(Request $request)
    {
        // 🔒 Validação de segurança
        if ($request->header('X-N8N-Secret') !== config('services.n8n.secret')) {
            return response()->json(['erro' => 'Acesso negado'], 403);
        }

        $agora = now('America/Sao_Paulo');
        $processados = 0; $enviados = 0; $erros = 0;

        $agendamentos = Agendamento::with('grupo')
            ->where('status', 'pendente')
            ->where('data_hora_envio', '<=', $agora)
            ->orderBy('data_hora_envio', 'asc')
            ->limit(50)
            ->get();

        foreach ($agendamentos as $ag) {
            $processados++;
            $grupo = $ag->grupo;

            if (!$grupo || !$grupo->chat_id) {
                $ag->update(['status' => 'erro', 'mensagem_erro' => 'Grupo/chat_id inválido']);
                $erros++; continue;
            }

            // ⏰ Grupo fechado: só envia no horário de disparo do grupo
            if (($grupo->tipo ?? 'aberto') === 'fechado' && $grupo->horario_disparo) {
                $horaDisparoHoje = $ag->data_hora_envio->copy()->setTimeFromTimeString($grupo->horario_disparo);
                if ($agora->lt($horaDisparoHoje)) {
                    continue; // ainda não chegou a hora do disparo em bloco
                }
            }

            try {
                $token = config('services.telegram.client_secret');
                $resp = Http::timeout(15)->post(
                    "https://api.telegram.org/bot{$token}/sendMessage",
                    ['chat_id' => $grupo->chat_id, 'text' => $ag->mensagem]
                );

                if ($resp->successful()) {
                    $posicao = Agendamento::where('grupo_id', $ag->grupo_id)
                        ->where('data_hora_envio', $ag->data_hora_envio)
                        ->where('status', 'enviado')->count() + 1;

                    $total = Agendamento::where('grupo_id', $ag->grupo_id)
                        ->where('data_hora_envio', $ag->data_hora_envio)->count();

                    $ag->update([
                        'status' => 'enviado',
                        'enviado_em' => $agora,
                        'posicao' => $posicao,
                        'total' => $total,
                    ]);
                    $enviados++;
                } else {
                    throw new \Exception('Telegram API respondeu com erro: ' . $resp->body());
                }
            } catch (\Exception $e) {
                $ag->update(['status' => 'erro', 'mensagem_erro' => $e->getMessage()]);
                $erros++;
            }
        }

        return response()->json([
            'executado_em' => $agora->toDateTimeString(),
            'processados'  => $processados,
            'enviados'     => $enviados,
            'erros'        => $erros,
        ]);
    }
}
```

---

## ✅ TAREFA 4 — Confirmação/Criação de Campos

Verificar (e criar migration se faltar) os seguintes campos:

### Tabela `agendamentos`
| Campo | Tipo | Observação |
|---|---|---|
| `mensagem` | string | Texto que aparece no grupo |
| `data_hora_envio` | datetime | Horário programado |
| `status` | enum | `pendente` / `enviado` / `erro` / `cancelado` |
| `enviado_em` | datetime nullable | Preenchido no disparo |
| `posicao` | integer nullable | Ordem na rodada |
| `total` | integer nullable | Total da rodada |
| `mensagem_erro` | text nullable | Detalhe da falha |
| `grupo_id` | foreign key | Relacionamento com grupos |

### Tabela `grupos`
| Campo | Tipo | Observação |
|---|---|---|
| `nome` | string | Nome exibido |
| `chat_id` | string | ID do grupo no Telegram |
| `tipo` | enum | `aberto` / `fechado` |
| `horario_disparo` | time nullable | Ex: `19:58:00` (grupo fechado) |
| `ativo` | boolean | Se pode receber agendamentos |

> Relacionamento: `Agendamento` belongsTo `Grupo` (método `grupo()`).

---

## ✅ TAREFA 5 — Instruções para o n8n (documentar no código)

Adicionar um comentário no controller ou um bloco no README com os dados que o n8n precisa:

- **URL:** `GET https://seu-dominio.com/api/n8n/disparos/processar`
- **Cabeçalho obrigatório:** `X-N8N-Secret: {valor de N8N_WORKER_SECRET}`
- **Frequência:** a cada minuto (cron `* * * * *`), timezone `America/Sao_Paulo`
- **Resposta esperada:** JSON com `processados`, `enviados`, `erros`
- **Timeout sugerido:** 30 segundos

---

## ✅ CRITÉRIOS DE CONCLUSÃO

- [ ] Rota `GET /api/n8n/disparos/processar` criada e acessível
- [ ] Sem chave secreta no cabeçalho → retorna 403
- [ ] Com chave correta → processa pendentes e retorna JSON
- [ ] Mensagens enviadas via Telegram Bot API com `chat_id` do grupo
- [ ] Status atualizado: `enviado` (com `enviado_em`, `posicao`, `total`) ou `erro` (com `mensagem_erro`)
- [ ] Grupo fechado só envia após atingir `horario_disparo`
- [ ] Limit de 50 por execução para evitar timeout
- [ ] Fuso horário `America/Sao_Paulo` aplicado
- [ ] Migration criada se algum campo faltar
- [ ] `LOG-ALTERACOES.md` atualizado com esta implementação

---

## ❌ NÃO fazer neste prompt

- Não criar o workflow do n8n (apenas documentar o que ele precisa chamar)
- Não alterar o fluxo de agendamento pelo visitante
- Não implementar `queue:work` ou Jobs do Laravel (esta rota substitui)
- Não alterar nome/marca do sistema (pendente de confirmação com a cliente)

---

*Fim do Prompt — 2026-09-25*
