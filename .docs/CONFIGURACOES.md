# Configurações — Bot, Grupos e Disparos

**Sistema:** AgendaFácil · **Data:** 26/09/2026 · **Horário oficial:** Brasília (`America/Sao_Paulo`)
Onde configurar: **Admin** (telas) ou **ambiente** (`.env` / Dashboard da Vercel).

---

## 1 · Bot do Telegram

### 1.1 Variáveis de ambiente

| Var | Para que serve | Exemplo |
|---|---|---|
| `TELEGRAM_BOT_NAME` | Username do bot **sem @** (fallback quando não há bot padrão no banco; usado no widget) | `AgendaFacil26_bot` |
| `TELEGRAM_TOKEN` | Token do @BotFather (fallback + validação do hash do widget) | `123456789:ABCdef...` |
| `TELEGRAM_REDIRECT_URI` | Callback do Socialite (conta do robô/admin) | `${APP_URL}/telegram/callback` |
| `N8N_WORKER_SECRET` | Chave do worker de disparos (header `X-N8N-Secret`) | string longa aleatória |

### 1.2 Cadastro de bots (Admin → Bots Telegram)

Tabela `telegram_bots`: `name`, `username` (sem @, único), `token` (**nunca exibido na tela**), `is_default`, `is_active`.

- **Bot padrão ativo** = usado em tudo: envios, widget de validação, teste. Sem ele, vale o `.env`.
- Ações: cadastrar, editar, excluir, ativar/desativar, **marcar padrão**, **importar do .env** (cria/atualiza a partir de `TELEGRAM_BOT_NAME`/`TELEGRAM_TOKEN` e valida).
- Resolução no código: `TelegramBot::defaultBot()` → `TELEGRAM_TOKEN` do ambiente.

### 1.3 Requisitos no Telegram

1. Bot criado no **@BotFather** (`/newbot`); `/setdomain` com o domínio do site (senão o widget não renderiza).
2. Bot **dentro dos grupos** (recomendado como administrador) + ao menos 1 mensagem recente (senão o sistema não o enxerga).
3. Validação em **Teste Telegram**: `getMe`, envio de teste, gerar/verificar código.

---

## 2 · Grupos (Admin → Grupos)

Tabela `grupo_telegrams`. Listagem: só **ativos**, ordenados por `ordem` → nome.

| Campo | Regra | Observação |
|---|---|---|
| `nome` | obrigatório, máx. 150 | Exibido no fluxo e no admin |
| `chat_id` | obrigatório, máx. 60 | ID do grupo (ex: `-100123...`); via **listar do Telegram** ou `@getmyid_bot` / `getUpdates` |
| `tipo` | `group` \| `supergroup` \| `channel` | Tipo de chat do Telegram |
| `modo` | `aberto` \| `fechado` | Regra de envio (padrão: `aberto`) |
| `horario_abertura` / `horario_fechamento` | `H:i`, opcional | Só relevantes no modo fechado |
| `horario_disparo` | `H:i`, opcional | Fechado: envio em bloco neste horário |
| `dias_semana` | `1-7` separados por vírgula, opcional | ISO (1=seg … 7=dom); vazio = todos os dias |
| `ordem` | inteiro 0–9999 (padrão 0) | Sequência de exibição |
| `ativo` | sim/não | Inativo some do fluxo (botão Ativar/Desativar) |

**Modo aberto:** horário livre; posição por minuto.
**Modo fechado:** agendamento só dentro da janela (abertura→fechamento, nos dias); fora dela vale a próxima rodada; disparo em bloco no `horario_disparo`; posição por rodada.
**Importar:** `updateOrCreate` por (`chat_id` + usuário); novos entram `aberto` + ativos.

---

## 3 · Disparos

### 3.1 Configurações (Admin → Config. disparos, tabela `configuracoes`, com cache)

| Chave | Valores | Padrão |
|---|---|---|
| `disparo_modo` | `agendado` \| `imediato` | `agendado` |
| `disparo_horario` | `H:i` | `08:00` |

### 3.2 Worker automático (n8n)

- `GET /api/n8n/disparos/processar`, **1× por minuto**, cron com timezone `America/Sao_Paulo`.
- Auth: header `X-N8N-Secret` = `N8N_WORKER_SECRET` (`hash_equals`; sem segredo → 403 `{"erro":"Acesso negado"}`).
- Lote de **50**, ordem horário → grupo → confirmação; grupo fechado só após o `horario_disparo` (senão fica pendente = `ignorados`).
- Envio via Bot API com o **texto exato**; posição **recalculada no envio** (rodada/minuto).
- Resposta: `{executado_em, processados, enviados, erros, ignorados}`.
- Detalhes em `.docs/INSTRUCOES/n8n-disparos-cron.md`. Scheduler artisan (`schedule:run`) existe como alternativa fora do serverless.

### 3.3 Ciclo de vida e ações manuais (Admin → Disparos)

Status: `pendente` → `enviado` | `erro` (`mensagem_erro` gravada) | `cancelado`.
Filtros por status, destino e data. Ações por linha:

| Ação | Regra |
|---|---|
| ↻ Reenviar | Só `pendente`/`erro` |
| ✕ Cancelar | Bloqueado se já `enviado`; recalcula posições da rodada/horário |
| Troca direta de status | `pendente`/`enviado`/`erro`/`cancelado` (select na linha) |

---

## Referência rápida

| Preciso... | Onde |
|---|---|
| Criar/configurar bot | @BotFather + Admin → Bots Telegram |
| Liberar widget no domínio | @BotFather → `/setdomain` |
| Cadastrar grupo / regra / horário | Admin → Grupos |
| Ver/forçar disparos | Admin → Disparos (filtros + reenviar) |
| Mudar modo/horário padrão | Admin → Config. disparos |
| Testar bot/código | Admin → Teste Telegram |
| Monitorar worker | n8n (JSON-resumo) + Runtime Logs da Vercel |
