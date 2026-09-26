# Fluxo do Visitante — Como Cada Etapa Funciona

**Sistema:** AgendaFácil (agendamento e disparo automático via Telegram)
**Data:** 26/09/2026 · **Horário oficial:** Brasília (`America/Sao_Paulo`)
**Entrada:** `/` redireciona para `/acesso` · Telas Blade com identidade Telegram

```
1. Acesso (/acesso)
   ↓ 4 últimos dígitos do telefone
2. Confirmar os Dados (/confirmar-dados → /confirmar-dados/resumo)
   ↓ grupo + mensagem + data/hora → resumo → confirmar
3. Código (/codigo)
   ↓ login no próprio Telegram OU código de 6 dígitos no grupo
4. Grupos do Telegram (/grupos/selecionar)
   ↓ "Eu confirmo que esse grupo é o correto"
5. Concluído (/concluido)
   ↓ disparo automático no horário (worker n8n, 1× por minuto)
```

**Sessão do fluxo** (namespace `agendafacil.*`, válida por **2h** — expirou, volta ao `/acesso`):
`visitante_id`, `digitos_ok` / `telegram_ok`, `acesso_em`, `rascunho`, `agendamento_id`,
`codigo` (+ `codigo_ok`), `grupo` (+ `grupo_ok`). Botão **Sair** (`POST /sair`) limpa tudo.

---

## 1 · Acesso — `GET/POST /acesso`

**O que a pessoa vê:** tela "Desbloquear" pedindo os **4 últimos dígitos do telefone** do agendamento.

**O que acontece:**
1. `POST /acesso` valida `digitos` (obrigatório, exatamente 4 números).
2. Busca o cadastro em `users` pelo campo `ultimos_digitos_telefone` **ou** pelo sufixo do `telefone` (cobre `+55 41 98843-2148` mesmo sem o campo dedicado).
3. Não achou → volta com erro *"Dígitos não encontrados"*.
4. Achou → grava na sessão `visitante_id` + `digitos_ok` + `acesso_em` (relógio da validade de 2h) e redireciona a `/confirmar-dados`.

**Detalhes:** não faz login no sistema (só identifica o visitante); sem `visitante_id` nenhuma etapa seguinte abre.

## 2 · Confirmar os Dados — `GET/POST /confirmar-dados` → `GET/POST /confirmar-dados/resumo`

**O que a pessoa vê:** formulário com **grupo de destino** (select só de grupos ativos, ordenados), **mensagem** editável só com o nome (ex: `Maria`, máx. 200 caracteres — o número `xN` é posto pelo sistema, sem exibir), **data + hora** do envio (sugeridas: agora + 30min arredondado) e, somente leitura, tipo de disparo e telefone.

**O que acontece:**
1. `POST /confirmar-dados` valida tudo (mensagem obrigatória; data/hora no futuro), remove sufixo `xNÚMERO` digitado à mão e aplica a **regra do grupo**:
   - **Aberto:** aceita data/hora livres.
   - **Fechado:** fixa o `horario_disparo`, respeita abertura/fechamento e dias da semana; se a rodada de hoje fechou, joga para a próxima (com aviso).
3. Salva um **rascunho** na sessão e mostra a tela de **resumo** (com tag só da senha + Voltar / Confirmar).
4. `POST /confirmar-dados/confirmar` **recalcula a posição na hora** (a rodada pode ter mudado), **numera a senha** (`xN` = próxima da sequência do grupo, máx. + 1, cancelados fora) e **cria o agendamento** (ou atualiza o existente) como `pendente`, com: grupo, texto final (`Nome xN`), **posição/total** (aberto = por minuto; fechado = por rodada), destino (`chat_id` + nome), `data_hora_envio` e IP de origem. Segue para `/codigo`.

## 3 · Código — `GET/POST /codigo` (+ `POST /codigo/reenviar`)

**O que a pessoa vê:** tela de confirmação com o **Login Widget do Telegram** (caminho principal) e, como alternativa, o campo do código de 6 dígitos.

**Caminho A — login no próprio Telegram (preferencial):**
1. O botão do widget usa o **bot padrão** do admin (ou o do `.env`, com aviso de `/setdomain` no @BotFather se o botão não aparecer).
2. O Telegram devolve os dados em `GET /telegram/confirmar-callback`, que valida o **hash oficial** (`hash_equals`, `auth_date` de no máximo 24h).
3. A conta precisa ser a **vinculada ao cadastro** (`telegram_id`/username); se for, vincula o `telegram_id`, marca `codigo_ok` e vai aos grupos. Se não for, volta com erro orientando a usar o código do grupo.
4. Nada da conta da pessoa é guardado ou usado para enviar mensagens — é só verificação.

**Caminho B — código no grupo (fallback):**
1. Ao abrir a etapa, o sistema gera um código de 6 dígitos e o **envia no grupo** via bot (`"Seu código AgendaFácil: 123456"`).
2. `POST /codigo` confere com o da sessão → `codigo_ok` → grupos. Errou → *"Código inválido"*.
3. `POST /codigo/reenviar` descarta e gera um novo (em dev, o código aparece na tela).

## 4 · Grupos do Telegram — `GET /grupos/selecionar` → `POST /grupos/confirmar`

**O que a pessoa vê:** **somente o grupo do agendamento** + o compromisso *"Eu confirmo que esse grupo é o correto"*.

**O que acontece:**
1. Exige visitante identificado **e** `codigo_ok` (sem isso, volta ao `/codigo`); sem grupo vinculado, volta a confirmar dados com aviso.
2. `POST /grupos/confirmar` valida que o `grupo_id` confirmado **é o do agendamento** (outro grupo é rejeitado), grava o destino na sessão (`grupo_ok`) e vai ao `/concluido`.

## 5 · Concluído — `GET /concluido`

**O que a pessoa vê:** *"Grupo confirmado. Pode fechar esta janela."* + resumo (grupo, data/hora, **posição**, ex: `5ª de 12`) e botão **Sair** (encerra a sessão local).

**Depois disso:** o worker (n8n, `GET /api/n8n/disparos/processar` a cada minuto, auth por `X-N8N-Secret`, lote de 50, ordem horário → grupo → confirmação) envia a mensagem no horário exato; grupo fechado só após o `horario_disparo`. Status evolui `pendente → enviado` (ou `erro`, com `mensagem_erro` e reenvio manual no admin). Cancelar recalcula as posições.

---

## Guardas e erros comuns

| Situação | Comportamento |
|---|---|
| Sessão com +2h ou sem `visitante_id` | Volta ao `/acesso` |
| Abrir `/grupos/selecionar` sem `codigo_ok` | Volta ao `/codigo` |
| Grupo fechado fora do horário/dia | Bloqueio com aviso (ou próxima rodada) |
| Botão do widget não aparece | Falta `/setdomain` no @BotFather / sem bot padrão (ver Admin → Bots) |
| Conta do Telegram sem vínculo | Erro na etapa 3 orientando ao código do grupo |
| Agendamento já enviado | Não pode ser cancelado (proteção) |

*Rotas legadas do fluxo antigo (`/agendar`, `/meus-agendamentos`, `/painel`) seguem existindo por compatibilidade, fora do fluxo único.*
