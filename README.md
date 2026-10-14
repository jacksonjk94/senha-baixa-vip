# SenhaBaixa VIP 📋 — Disparo de Senhas via Telegram

Conecta sua conta do Telegram, escolhe o grupo de destino e o sistema envia a mensagem com a **senha** automaticamente no horário definido — como a sua conta, no segundo exato.

---

## ✅ Como Funciona

1. **Telefone** → Informa DDD + número (ou os 4 últimos dígitos, se já agendou)
2. **Código** → Digita o código que chegou no Telegram e confirma **SIM, SOU EU** no aviso de novo login
3. **Horário** → Escolhe data e hora do envio
4. **Grupo** → Seleciona o grupo e define se é **🔓 Aberto** (horário livre) ou **🔒 Fechado** (disparo em bloco)
5. **Confirmar** → Revisa tudo e confirma o agendamento
6. **✅ Pronto!** → A senha chega no grupo automaticamente no horário programado

---

## ✨ Funcionalidades

- ✅ Acesso por telefone com código via Telegram (MTProto)
- ✅ Disparo como a própria conta (sem depender de bot no grupo)
- ✅ Precisão de segundo: antecipação pela latência medida (painel admin)
- ✅ Grupos abertos (minuto agendado) e fechados (bloco + posição por rodada)
- ✅ Tipo do grupo definível no fluxo de agendamento
- ✅ Área administrativa: visão geral em tempo real, disparos, grupos, cadastros, bots, manual
- ✅ Foto do visitante na visão geral
- ✅ Interface responsiva — celular e computador

---

## 🧩 Serviços

| Serviço | O quê | Onde |
|---|---|---|
| Laravel | Fluxo, agendamentos, worker `GET /api/n8n/disparos/processar` | este repo |
| MTProto (`services/telegram-mtproto`) | Auth da conta, lista de grupos, envio como usuário — `127.0.0.1:3037` | `node server.js` |
| Cron (`services/disparos-cron`) | Gatilho a cada minuto no `:00` menos a latência | `node server.js` |

Variáveis principais no `.env`: `APP_NAME`, `TELEGRAM_API_ID`, `TELEGRAM_API_HASH`, `TELEGRAM_MTPROTO_URL`, `TELEGRAM_MTPROTO_SERVICE_TOKEN`, `N8N_WORKER_SECRET`, `DISPARO_ANTECIPACAO_SEGUNDOS` (só local). Latência liga/desliga e valor em **Admin → Config. disparos**.

---

## 🎬 Diferencial

> **Não é envio manual:** você programa uma vez e o sistema executa sozinho. No horário marcado, a senha aparece no grupo do Telegram automaticamente, no segundo exato — antes de qualquer mensagem manual.

---

Feito com 💙 por Jackson Dos Santos
