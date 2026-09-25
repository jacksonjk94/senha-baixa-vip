# PROMPT DE CORREÇÕES — Ajustes no Fluxo do Sistema

**Arquivo:** 2026-09-25_prompt-correcoes-fluxo.md
**Data:** 25/09/2026 · Curitiba/PR
**Projeto:** Sistema de Agendamento e Disparo Automático via Telegram (Laravel)
**Versão:** Correções v1 — baseado no teste com a cliente em 24/09/2026
**Escopo:** Apenas os itens 1 a 6. Itens 7 (renomeação da marca) e 8 (mensagem de convite) estão PENDENTES de confirmação com a cliente e NÃO devem ser implementados neste prompt.

---

## 🎯 OBJETIVO

Aplicar 6 correções identificadas no teste com a cliente, ajustando o fluxo de agendamento, validações, regras de disparo por tipo de grupo e clareza das telas. Manter tudo o que já funciona; apenas corrigir e melhorar os pontos listados.

> **Instrução para o agente (OpenCode):** Leia todo o projeto existente antes de alterar. Mantenha os padrões já adotados. Não remova funcionalidades em produção; apenas ajuste o que está descrito abaixo.

---

## ✅ ITEM 1 — Remover exigência de login do Telegram para o visitante

### Problema identificado
O fluxo atual pede conexão/código do Telegram para quem está agendando. Pela regra confirmada, **o visitante NÃO precisa ter conta no Telegram** — apenas o robô/sistema tem conta e faz os envios.

### O que fazer
1. **Rotas públicas:** As telas de agendamento (`/agendar`, formulário, confirmação, histórico) devem ser acessíveis **sem middleware de auth** e sem qualquer passo de conexão com Telegram.
2. **Remover do fluxo do visitante:** qualquer tela de "conectar Telegram", "digite o código", "autorizar app" para quem está agendando.
3. **Manter apenas no admin:** a conexão via Socialite Telegram existe **somente** para a conta do robô/administrador, em área protegida.
4. **Fluxo ideal do visitante:** Abre o link → preenche formulário → confirma → agendado. Sem login, sem código, sem instalar nada.

### Validação
- [ ] Visitante consegue agendar sem estar logado e sem conta Telegram
- [ ] Nenhuma tela do fluxo público pede conexão com Telegram
- [ ] Conexão Telegram existe apenas na área administrativa/robô

---

## ✅ ITEM 2 — Garantir carregamento da lista de grupos

### Problema identificado
No teste, o campo de grupo apresentou o erro *"Selecione um item da lista"* — o dropdown não carregou os grupos cadastrados.

### O que fazer
1. **Garantir carregamento:** o select de grupos deve listar todos os grupos com `ativo = true`, ordenados por `ordem` e depois por `nome`.
2. **Tratamento de lista vazia:** se não houver grupos ativos, exibir mensagem amigável em vez de erro de validação:
   > *"Os grupos ainda estão sendo configurados. Tente novamente em instantes ou entre em contato."*
3. **Validação no `store`:** o grupo selecionado deve existir e estar ativo; caso contrário, retornar erro claro no formulário.
4. **Área admin:** manter (ou criar, se ainda não existir) tela de cadastro/edição/ativação de grupos, com os campos: nome, chat_id do Telegram, tipo (aberto/fechado — ver Item 4), ativo, ordem.
5. **Cache leve (opcional):** cachear a lista de grupos ativos por alguns minutos para evitar consultas repetidas; invalidar ao salvar/alterar grupo.

### Validação
- [ ] Select sempre carrega os grupos ativos no formulário
- [ ] Sem grupos → mensagem amigável, sem quebrar o fluxo
- [ ] Admin consegue cadastrar e ativar/desativar grupos

---

## ✅ ITEM 3 — Simplificar o formulário de agendamento

### Problema identificado
O formulário pede nome completo, e-mail e telefone como obrigatórios, mas na prática o essencial é: **mensagem (texto que aparece no grupo) + grupo + data/horário**. Campos extras geram atrito e confusão.

### O que fazer
1. **Campos OBRIGATÓRIOS:**
   - Mensagem/texto que aparecerá no grupo (ex: `Roberta lallison x8`)
   - Grupo de destino
   - Data e horário do disparo (conforme regra do tipo de grupo — ver Item 4)
2. **Campos OPCIONAIS:**
   - Nome completo → opcional (se preenchido, fica no registro)
   - Telefone → opcional, com rótulo claro: *"Telefone para contato (opcional)"*
   - E-mail → **opcional** (não é usado no fluxo; pode ser removido dos obrigatórios)
3. **Destaque visual:** os 3 campos principais devem aparecer primeiro e em destaque; campos opcionais ficam abaixo, em bloco separado com título *"Dados para contato (opcional)"*.
4. **Placeholder no campo de mensagem:** exemplo do formato real: *"Ex: Roberta lallison x8"*.
5. **Validação:** apenas os 3 campos principais são obrigatórios; validar tamanho máximo da mensagem (ex: 200 caracteres).

### Validação
- [ ] Formulário envia com apenas mensagem + grupo + data/hora preenchidos
- [ ] E-mail e telefone não bloqueiam o envio se vazios
- [ ] Campos principais em destaque, opcionais claramente marcados

---

## ✅ ITEM 4 — Regras de disparo: Grupo ABERTO vs Grupo FECHADO (detalhado)

### Problema identificado
A cliente trabalha com dois modelos de grupo com regras de horário completamente diferentes. O sistema atual trata todos os grupos da mesma forma. É preciso implementar a distinção e suas regras.

### 4.1 — Estrutura de dados (tabela `grupos`)

Adicionar/ajustar colunas:

| Coluna | Tipo | Descrição |
|---|---|---|
| `tipo` | enum('aberto','fechado') | Tipo de regra do grupo |
| `horario_abertura` | time | (fechado) horário que começa a aceitar agendamentos |
| `horario_fechamento` | time | (fechado) horário que para de aceitar para a rodada |
| `horario_disparo` | time | (fechado) horário do envio em bloco |
| `dias_semana` | string/json | (opcional) dias da semana em que o grupo funciona |
| `ativo` | boolean | Se aparece na lista |
| `ordem` | integer | Ordem de exibição |

### 4.2 — Grupo FECHADO (regras rígidas por horário)

Modelo com etapas fixas — **não envia antes, não aceita depois do fechamento**:

| Etapa | Horário (exemplo) | Regra |
|---|---|---|
| 📝 Abre para agendamento (rascunho) | 19:50 | A partir deste horário, o sistema aceita novos agendamentos para a rodada do dia |
| 🔒 Fecha o grupo | 19:55 | Após este horário, **novos agendamentos NÃO entram** no disparo do dia — são direcionados para a próxima rodada/dia útil |
| 📤 Disparo em bloco | 19:58 | Todas as mensagens da rodada são enviadas **em sequência**, na ordem em que foram confirmadas |
| ✅ Rodada concluída | 20:00 | Grupo fecha; novos agendamentos só na próxima abertura |

**Regras a implementar:**
1. **Fora do horário de abertura:** se a pessoa tentar agendar antes de `horario_abertura`, exibir: *"Este grupo abre às XXh. Volte neste horário para agendar."* — bloquear o envio.
2. **Após o fechamento:** se tentar agendar após `horario_fechamento`, exibir: *"Este grupo já fechou hoje (XXh). Seu agendamento será para a próxima rodada."* — oferecer agendamento para o próximo dia útil de funcionamento.
3. **Disparo:** no `horario_disparo`, o scheduler envia **todos os agendamentos pendentes daquele grupo** em sequência, na ordem de criação/confirmação. Não enviar antes.
4. **Posição:** para grupo fechado, a posição = **ordem de confirmação dentro da rodada** (contagem de agendamentos do mesmo grupo no mesmo dia, entre abertura e fechamento).
5. **Aviso de "queimada":** deixar claro na tela que agendamentos feitos após o fechamento não valem para a rodada atual.
6. **Dias de funcionamento:** se `dias_semana` preenchido, validar se o dia atual é permitido; senão, considerar todos os dias.

### 4.3 — Grupo ABERTO (sem horário fixo)

Modelo livre — cada pessoa escolhe quando quer receber:

1. **Sem horário de abertura/fechamento:** a pessoa seleciona **data e horário** de envio livremente.
2. **Disparo individual:** cada agendamento é enviado **exatamente no seu horário** (scheduler a cada minuto), não em bloco.
3. **Sem "queimada":** não existe limite de entrada — qualquer horário futuro é válido.
4. **Posição:** contagem de agendamentos para o **mesmo grupo + mesmo horário (minuto a minuto)**.
5. **Validação:** data/hora deve ser futura (pelo menos 1 minuto à frente).

### 4.4 — Tela de agendamento conforme o tipo

- Ao selecionar o grupo no formulário, o sistema deve:
  - **Fechado:** mostrar aviso: *"Grupo fechado — abre às XXh, fecha às XXh, envia às XXh. Sua mensagem sairá no disparo das XXh."* O campo de data/hora pode ser fixo (o dia da rodada) ou permitir escolher o dia, mas o horário de envio é sempre o `horario_disparo` do grupo.
  - **Aberto:** mostrar campo de **data e horário livres** para a pessoa escolher. Aviso: *"Sua mensagem será enviada exatamente neste horário."*
- Exibir o tipo do grupo ao lado do nome no select: `[Fechado]` ou `[Aberto]`.

### Validação
- [ ] Grupo fechado bloqueia agendamento fora do horário de abertura
- [ ] Após fechamento, agendamento vai para próxima rodada com aviso claro
- [ ] Disparo do grupo fechado acontece em bloco no horário definido, na ordem de confirmação
- [ ] Grupo aberto permite escolher data/hora livre e dispara individualmente
- [ ] Tipo do grupo exibido na tela de agendamento com seus horários
- [ ] Posição calculada conforme a regra de cada tipo

---

## ✅ ITEM 5 — Posição: exibição e cálculo corretos

### Problema identificado
A posição aparece (`8 de 85`), mas a cliente tem dúvida de como é definida, e a regra difere entre grupo aberto e fechado.

### O que fazer
1. **Cálculo — Grupo Fechado:** posição = ordem de confirmação dentro da rodada (mesmo grupo, mesmo dia, entre abertura e fechamento).
2. **Cálculo — Grupo Aberto:** posição = contagem de agendamentos para o mesmo grupo + mesmo horário (mesmo minuto).
3. **Exibição na confirmação:** mostrar em destaque:
   > *"Sua posição: 5ª de 12 — sua mensagem será a 5ª a aparecer no grupo."*
4. **Aviso de queimada (grupo fechado):** se a pessoa agendar após o fechamento, exibir: *"Este grupo já fechou hoje. Você estará na 1ª posição da próxima rodada (dia XX)."*
5. **Histórico:** manter posição registrada no agendamento para exibição posterior.
6. **Recalculo:** se um agendamento for cancelado, as posições dos demais da mesma rodada/horário são recalculadas.

### Validação
- [ ] Posição exibida claramente na tela de confirmação
- [ ] Cálculo segue regra diferente por tipo de grupo
- [ ] Cancelamento recalcula posições dos demais

---

## ✅ ITEM 6 — Clareza dos rótulos e tela de resumo de confirmação

### Problema identificado
A cliente perguntou várias vezes o que cada campo significava. Os rótulos estão genéricos e não há um resumo visual claro antes de confirmar.

### O que fazer
1. **Rótulos dos campos (alterar):**
   - "Mensagem" → **"Mensagem que aparecerá no grupo"** (com placeholder: *Ex: Roberta lallison x8*)
   - "Nome do grupo" / "Grupo" → **"Grupo de destino"**
   - "Horário do disparo" → **"Horário que será enviado"**
   - "Data" → **"Data do envio"**
2. **Tela de resumo ANTES de confirmar:** após preencher e clicar em "Avançar", mostrar uma tela com todos os dados em formato de cartão, por exemplo:
   ```
   ✅ Confirme seu agendamento:
   ─────────────────────────────
   Grupo: SENHA NORMAL P1 E ALA (Fechado)
   Mensagem: Roberta lallison x8
   Envio: 25/09/2026 às 19:58
   Sua posição: 5ª de 12
   ─────────────────────────────
   [ Voltar e editar ]  [ Confirmar agendamento ]
   ```
3. **Tipo de grupo visível:** sempre que o grupo for exibido, mostrar se é aberto ou fechado e seu horário de disparo.
4. **Tela de sucesso:** após confirmar, exibir:
   > *"Agendado com sucesso! ✅ Sua mensagem será enviada no grupo X no dia XX às XXh. Posição: 5ª de 12."*
   + botão "Ver meus agendamentos".
5. **Linguagem simples:** evitar termos técnicos ("disparo", "trigger", "job"). Usar: "envio", "mensagem", "horário que será enviado".

### Validação
- [ ] Rótulos descritivos e sem jargão
- [ ] Tela de resumo com todos os dados antes da confirmação final
- [ ] Tela de sucesso clara com posição e horário de envio

---

## 📋 Resumo das alterações técnicas

| Área | Alteração |
|---|---|
| Rotas | Agendamento público sem auth; conexão Telegram só no admin |
| Tabela `grupos` | + colunas: `tipo`, `horario_abertura`, `horario_fechamento`, `horario_disparo`, `dias_semana` |
| Tabela `agendamentos` | garantir `posicao`, `total_rodada`, `rodada_data` |
| Formulário | e-mail/telefone opcionais; 3 campos principais obrigatórios |
| Validação | regra diferente por tipo de grupo (aberto/fechado) |
| Scheduler | grupo fechado: disparo em bloco no horário; grupo aberto: disparo individual por horário |
| Telas | rótulos descritivos + tela de resumo + tela de sucesso |
| Posição | cálculo e recálculo por tipo de grupo |

---

## ❌ NÃO implementar neste prompt (pendentes de confirmação com a cliente)

- **Item 7:** Renomeação do sistema / marca (nome e domínio oficiais) — aguardando confirmação.
- **Item 8:** Mensagem padrão de convite/compartilhamento — aguardando confirmação.

---

## ✅ Critérios de conclusão

- [ ] Visitante agenda sem login Telegram (Item 1)
- [ ] Lista de grupos sempre carrega; lista vazia tem tratamento amigável (Item 2)
- [ ] Formulário simplificado: e-mail/telefone opcionais (Item 3)
- [ ] Grupo fechado: regras de abertura/fechamento/disparo em bloco implementadas (Item 4)
- [ ] Grupo aberto: horário livre e disparo individual implementados (Item 4)
- [ ] Posição calculada e exibida corretamente por tipo de grupo (Item 5)
- [ ] Rótulos claros + tela de resumo + tela de sucesso (Item 6)
- [ ] Nenhuma alteração de marca ou mensagem de convite (itens 7 e 8 mantidos como estão)
- [ ] `LOG-ALTERACOES.md` atualizado com registro destas correções

---

*Fim do Prompt — 2026-09-25*
