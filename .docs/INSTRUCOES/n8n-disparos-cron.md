# n8n como Cron de Disparos — Instruções

**Rota:** `GET /api/n8n/disparos/processar`
**URL final:** `https://seu-dominio.com/api/n8n/disparos/processar`
**Contexto:** o sistema roda em ambiente serverless/compartilhado (Vercel ou similar), onde `queue:work`/cron nativo não funcionam. O **n8n faz o papel de Worker**: a cada minuto ele chama a rota, e o Laravel busca os pendentes, envia via Telegram e atualiza os status.

```
⏰ n8n (a cada minuto) → HTTP GET → Rota Laravel protegida
     ↓
🌐 Laravel: busca pendentes → envia Telegram → atualiza status → retorna JSON
     ↓
🤖 n8n registra resultado (log/monitoramento)
```

---

## 1. Pré-requisitos no Laravel

| Item | Onde |
|---|---|
| `N8N_WORKER_SECRET` com string longa aleatória | `.env` (ver `.env.example`) |
| `TELEGRAM_TOKEN` do BotFather válido | `.env` |
| Bot cadastrado como padrão | Admin → Bots Telegram |
| Grupos com bot adicionado e `chat_id` vinculado | Admin → Grupos |

Gerar um segredo novo (exemplo):

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

> Sem `N8N_WORKER_SECRET` configurado a rota nega tudo (403) — fail closed.

## 2. Configurar o workflow no n8n

1. Crie um workflow com gatilho **Schedule Trigger**:
   - **Frequência:** a cada minuto (cron `* * * * *`)
   - **Timezone:** `America/Sao_Paulo`
2. Adicione um nó **HTTP Request**:
   - **Method:** `GET`
   - **URL:** `https://seu-dominio.com/api/n8n/disparos/processar`
   - **Header:** `X-N8N-Secret` = valor do `N8N_WORKER_SECRET`
   - **Timeout:** `30000` ms (30 segundos)
3. (Opcional) Adicione tratamento do JSON de resposta para log/alerta.

## 3. Resposta esperada

```json
{
  "executado_em": "2026-09-25 19:58:01",
  "processados": 12,
  "enviados": 11,
  "erros": 1,
  "ignorados": 0
}
```

| Resposta | Significado |
|---|---|
| `401/403` + `{"erro": "Acesso negado"}` | `X-N8N-Secret` ausente, errado ou sem segredo no servidor |
| `processados` | Agendamentos vencidos encontrados no lote (máx. 50/execução) |
| `enviados` | Mensagens entregues (`status=enviado`, com `enviado_em`, `posicao`, `total_grupo_horario`) |
| `erros` | Falhas (`status=erro` + `mensagem_erro` para reenvio manual no admin) |
| `ignorados` | Pulados desta vez: grupo **fechado** cujo `horario_disparo` ainda não chegou (seguem `pendente`) |

## 4. Regras aplicadas pela rota

- **Fuso:** `America/Sao_Paulo` em todas as comparações e carimbos.
- **Ordem:** horário → grupo → confirmação (posição); lote de 50 por execução.
- **Grupo fechado:** só envia após atingir o `horario_disparo` do dia (envio em bloco); antes disso mantém `pendente`.
- **Posição:** recalculada no envio — rodada do dia (fechado) ou mesmo minuto (aberto).
- **Sem grupo/`chat_id`:** marca `erro` (`Grupo/chat_id inválido`).

## 5. Observações

- Os horários de envio são gravados como horário local (parede); as comparações da rota usam `America/Sao_Paulo`.
- `enviado_em` registra o momento do envio; `mensagem_erro` guarda o motivo da falha.
- Falhas podem ser reenviadas em Admin → Disparos (botão Reenviar).
