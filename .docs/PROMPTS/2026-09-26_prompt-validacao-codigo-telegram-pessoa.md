# PROMPT — Alteração: Validação por Código no Telegram da Própria Pessoa

**Arquivo:** 2026-09-26_prompt-validacao-codigo-telegram-pessoa.md
**Data:** 26/09/2026 · Curitiba/PR
**Projeto:** Sistema de Agendamento e Disparo Automático via Telegram (Laravel)
**Tipo:** Alteração incremental — **apenas a etapa de validação**. Todo o restante do fluxo (agendamento, grupos, disparo pelo robô, rota do cron/n8n, área admin) PERMANECE como está.

> **Instrução para o agente (OpenCode):** Leia o projeto existente. Esta é uma alteração pontual na etapa de acesso/validação. NÃO altere o restante do fluxo, NÃO altere a lógica de disparo, NÃO altere a rota do cron. Apenas adicione/ajuste a etapa de validação da pessoa conforme descrito abaixo.

---

## 🎯 OBJETIVO DA ALTERAÇÃO

Separar em duas "pernas" independentes:

| | Como funciona | O que faz |
|---|---|---|
| 🤖 **Robô / Sistema** | Token fixo do Bot (`.env`) — sempre conectado | Envia as mensagens nos grupos no horário programado |
| 👤 **Pessoa / Visitante** | Login no Telegram DELA (código recebido no app dela) | Apenas **confirma identidade** para poder agendar |

> As duas coisas **não se misturam**: o robô continua enviando com o token fixo; a pessoa só valida que é ela mesma usando o próprio Telegram. O sistema **não assume a conta dela**, não lê mensagens, não envia em nome dela — só valida.

---

## ✅ O QUE DEVE MUDAR (apenas a validação)

### 1. Etapa de acesso da pessoa

Fluxo anterior (se existir): acesso por 4 últimos dígitos do telefone / sem validação.

**Novo fluxo de validação:**
```
1. Pessoa acessa o link de agendamento
   ↓
2. Informa o número de telefone (ou já está pré-preenchido)
   ↓
3. Sistema solicita à API do Telegram o envio de um código de verificação
   ↓
4. Telegram envia o código para o APP dela (na própria conta dela)
   ↓
5. Ela digita o código na tela do sistema
   ↓
6. Sistema valida o código com a API do Telegram
   ↓
7. ✅ Código correto → liberada para agendar (sessão temporária)
   ❌ Código errado → pede novamente (com limite de tentativas)
```

### 2. O que NÃO deve acontecer

- ❌ O sistema **não** deve manter a sessão do Telegram da pessoa ativa permanentemente — apenas validar na hora e guardar uma sessão local (ex: `session(['validado' => true, 'telefone' => '...'])`) válida por tempo limitado (ex: 2h ou até fechar o navegador).
- ❌ O sistema **não** deve usar a conta dela para enviar mensagens — o envio continua sendo 100% pelo robô com token fixo.
- ❌ O sistema **não** deve solicitar permissões além da verificação básica (não pedir acesso a contatos, mensagens, etc.).

---

## 🔧 ABORDAGEM TÉCNICA RECOMENDADA

### Opção A — Telegram Login Widget (mais simples e oficial)

O Telegram oferece um widget oficial de login que já cuida de todo o fluxo de código. Recomendado por ser mantido pelo próprio Telegram.

**Como implementar:**
1. Configurar o domínio do site com o **@BotFather** (comando `/setdomain`).
2. Na tela de validação, incluir o widget oficial:
   ```html
   <script async src="https://telegram.org/js/telegram-widget.js?22"
           data-telegram-login="NOME_DO_BOT"
           data-size="large"
           data-auth-url="https://seu-dominio.com/telegram/validar-callback"
           data-request-access="write">
   </script>
   ```
3. Criar rota de callback `GET /telegram/validar-callback` que recebe os dados (`id`, `first_name`, `username`, `photo_url`, `auth_date`, `hash`), **valida o hash** com o token do bot (conforme documentação oficial do Telegram) e, se válido, cria a sessão local e redireciona para o agendamento.
4. Validar hash:
   ```php
   // Verificação oficial do Telegram
   $data = $request->all();
   $hash = $data['hash'];
   unset($data['hash']);
   ksort($data);
   $checkString = collect($data)->map(fn($v, $k) => "$k=$v")->implode("\n");
   $secretKey = hash('sha256', config('services.telegram.client_secret'), true);
   $hmac = hash_hmac('sha256', $checkString, $secretKey);
   if (hash_equals($hmac, $hash)) {
       // ✅ Válido — criar sessão
   }
   ```

### Opção B — Fluxo manual de código (enviar código via API)

Se preferir o fluxo manual "digite o código que recebeu":
1. Usar a **Telegram API** (MTProto), por exemplo via biblioteca `danog/madelineproto` ou similar, para enviar o código de login para o número da pessoa.
2. Tela para digitar o código → validar via API → criar sessão local.
3. ⚠️ Esta opção é mais complexa e requer manter uma sessão MTProto. **Recomenda-se a Opção A (Login Widget)** por ser mais simples, oficial e segura.

> **Decisão:** preferir a **Opção A (Login Widget)**. Só usar a Opção B se a cliente exigir o fluxo manual de digitar código.

---

## ✅ O QUE PERMANECE INTACTO (não alterar)

- 🤖 **Robô com token fixo** no `.env` — continua sendo ele quem envia todas as mensagens.
- 📋 **Formulário de agendamento** (mensagem, grupo, data/horário) — igual.
- 🗂️ **Grupos aberto/fechado** e regras de horário — igual.
- ⏰ **Rota do cron/n8n** (`/api/n8n/disparos/processar`) — igual.
- 📊 **Área administrativa** — igual.
- 🎨 **Identidade visual** — igual.
- 🔢 **Cálculo de posição** — igual.

---

## 🔐 SEGURANÇA — Pontos obrigatórios

1. **Valide sempre o hash** (não confie apenas nos dados retornados pelo widget sem verificar).
2. **Sessão temporária:** após validação, guardar apenas `telefone` e `validado = true` na sessão, com expiração (ex: 2 horas). Não guardar token/sessão do Telegram da pessoa.
3. **Limite de tentativas:** no fluxo manual de código, limitar a 5 tentativas por número/IP antes de bloquear temporariamente.
4. **Aviso claro:** na tela, explicar que receberá um código no próprio Telegram e que isso **não dá acesso do sistema à conta dela** — é só verificação.
5. **Logout:** botão "Sair" que limpa a sessão local.

---

## ✅ CRITÉRIOS DE CONCLUSÃO

- [ ] Pessoa consegue validar identidade via próprio Telegram (widget ou código)
- [ ] Após validação, sessão local liberada para agendar (com tempo limite)
- [ ] Hash validado corretamente (segurança)
- [ ] Sistema **não** mantém sessão do Telegram da pessoa ativa
- [ ] Envio de mensagens continua 100% pelo robô com token fixo (inalterado)
- [ ] Todo o restante do fluxo continua funcionando sem alteração
- [ ] Tela explica claramente o propósito da validação (não assusta a pessoa)
- [ ] `LOG-ALTERACOES.md` atualizado com esta alteração

---

## ❌ NÃO fazer neste prompt

- ❌ Não alterar lógica de disparo/envio
- ❌ Não alterar rota do cron/n8n
- ❌ Não alterar regras de grupo aberto/fechado
- ❌ Não alterar formulário de agendamento
- ❌ Não alterar nome/marca do sistema (pendente de confirmação com a cliente)
- ❌ Não guardar credenciais/sessão do Telegram da pessoa

---

*Fim do Prompt — 2026-09-26*
