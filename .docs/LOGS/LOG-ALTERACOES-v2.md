# LOG DE ALTERAÇÕES — SenhaBaixa

**Sistema de Agendamento e Disparo Automático via Telegram**
Projeto em Laravel · Curitiba/PR · 2026

---

## 📋 Histórico de Versões

| Data | Versão | Área | Descrição | Responsável |
|---|---|---|---|---|
| 23/09/2026 | 1.0.0 | Estrutura | Projeto Laravel criado e pré-configurado | Jackson Dos Santos |
| 23/09/2026 | 1.1.0 | Documentação | Prompt de implementação v1 criado (entendimento inicial) | Jackson Dos Santos |
| 24/09/2026 | 2.0.0 | Fluxo | ✅ Atualização conforme diretrizes da cliente: visitante NÃO precisa de conta Telegram; texto livre = "senha"; ordem de envio = posição; disparo automático no horário exato; vários grupos por agendamento | Jackson Dos Santos |
| 24/09/2026 | 2.0.1 | Técnico | Socialite apenas para conta do robô/admin; rotas públicas de agendamento; cálculo de posição por grupo+horário; grupos pré-cadastrados pelo admin; remoção de login do visitante | Jackson Dos Santos |
| 24/09/2026 | 2.1.0 | Implementação | ✅ Fluxo SenhaBaixa v2 implementado: formulário público (grupo+data/hora+texto), posição por grupo+horário, disparo no horário exato, cancelamento, admin (grupos/agendamentos/config), telas Blade e botões com gradiente+interações — ver detalhes abaixo | OpenCode (Muse Spark) |
| 25/09/2026 | 2.2.0 | Fluxo único | ✅ Unificação no AgendaFácil `/acesso` em 5 etapas conforme prints `.docs/REQUISITOS/FLUXO`: 1 Acesso (4 dígitos) → 2 Confirmar os Dados (grupo/mensagem xNº/horário/tipo/telefone, somente leitura) → 3 Código (enviado direto no grupo da etapa 2, sem widget/Socialite no fluxo) → 4 Grupos do Telegram (só o grupo do agendamento + "Eu confirmo que esse grupo é o correto") → 5 Concluído ("Grupo confirmado. Pode fechar esta janela."); etapa de agendamento ocultada; `/` redireciona ao `/acesso`; fluxo `/agendar` separado oculto (rotas mantidas por compat) | OpenCode (Muse Spark) |
| 25/09/2026 | 2.2.1 | Fluxo único | ✅ Etapa 2 com grupo selecionável (select de grupos ativos) e mensagem editável pré-preenchida no padrão `Nome + xNÚMERO` (ex: `Jackson Dos Santos x40`, validado por regex); salvamento atualiza grupo/texto/destino do agendamento. Telefone com +55 fixo: `/cadastro`, novo visitante e novo usuário (admin) com prefixo +55 e placeholder `(13) 98138-8690`; normalização `+55` no servidor (`telefoneComDdi`) | OpenCode (Muse Spark) |
| 25/09/2026 | 2.2.2 | Fluxo único | ✅ Etapa 2: removida a dica de padrão sob a mensagem; senha pré-preenchida automaticamente com a próxima da sequência do grupo (`Agendamento::proximaSenhaNumero`, máx. `xN` + 1, cancelados fora; ex: `Jackson Dos Santos x40`); ao trocar o grupo, o sufixo é atualizado via `GET /proxima-senha` sem recarregar | OpenCode (Muse Spark) |
| 25/09/2026 | 2.2.3 | Fluxo único | ✅ Etapa 2: horário do disparo definível (data + hora editáveis, pré-preenchidos com o agendamento ou padrão do admin); salvamento grava `data_hora_envio` com validação de futuro — nunca mais exibe "—" | OpenCode (Muse Spark) |
| 25/09/2026 | 2.2.4 | Fluxo único | ✅ Etapa 2: sem bloqueio por falta de agendamento — quando não há pré-cadastro, o agendamento é criado na confirmação (grupo + mensagem + horário, status pendente, posição calculada); removido o aviso "Nenhum agendamento vinculado..." e os campos desabilitados | OpenCode (Muse Spark) |
| 25/09/2026 | 2.2.5 | Auth | ✅ Login/logout via Inertia agora navegam de verdade: `AuthenticatedSessionController` usa `Inertia::location()` (409 + recarregamento total) em vez de 302 para destino Blade — antes o POST terminava em HTML e o cliente abria o modal de erro; logout de form HTML segue com 302 direto ao `/acesso` | OpenCode (Muse Spark) |
| 25/09/2026 | 2.3.0 | Ajuda | ✅ Página pública `GET /ajuda/bot-telegram` com passo a passo (criar bot no @BotFather, token, username, cadastro no admin, criar grupos, adicionar o bot, vincular `chat_id`, testar envio, segurança do token) + item "📖 Ajuda: Bot Telegram" no menu Sistema da sidebar admin | OpenCode (Muse Spark) |
| 25/09/2026 | 3.0.0 | Correções fluxo | ✅ Itens 1–6 do `2026-09-25_prompt-correcoes-fluxo.md`: (1) visitante segue sem conta Telegram (etapa Código mantida por decisão posterior dos prints, só via bot do sistema); (2) select de grupos ativos/ordenados + mensagem amigável de lista vazia + validação de grupo ativo; (3/6) rótulos claros ("Mensagem que aparecerá no grupo", "Grupo de destino", "Data do envio", "Horário que será enviado"), placeholder `Ex: Roberta lallison x8`, mensagem máx. 200; (4) grupos ABERTO × FECHADO — migration `000015` (`modo`, `horario_abertura/fechamento/disparo`, `dias_semana`; `tipo` mantido como tipo de chat), admin com regra/horários/dias + edição, etapa 2 com aviso por modo e horário fixo no disparo (fechado), bloqueio antes da abertura, próxima rodada após fechamento, scheduler ordenado por horário→grupo→confirmação (envio em bloco); (5) posição por rodada (fechado) / por minuto (aberto), exibida no resumo e no concluído ("5ª de 12"), recálculo no cancelamento; (6) tela de resumo antes da confirmação final (2º estado da etapa 2, com Voltar/Confirmar). Decisões: coluna `modo` em vez de `tipo` (conflito com tipo de chat); sem botão "Ver meus agendamentos" (fluxo oculto); itens 7–8 NÃO implementados | OpenCode (Muse Spark) |
| 25/09/2026 | 3.1.0 | Worker n8n | ✅ Rota `GET /api/n8n/disparos/processar` (`N8nDisparoController@processar`) para o n8n fazer papel de cron/Worker: auth por `X-N8N-Secret` vs `N8N_WORKER_SECRET` (fail closed, `hash_equals`, 403 `{"erro":"Acesso negado"}`), lote de 50 em fuso `America/Sao_Paulo`, ordem horário→grupo→confirmação, grupo fechado só após `horario_disparo`, envio via `TelegramBotService`, posição recalculada no envio (rodada/minuto), JSON `{executado_em,processados,enviados,erros,ignorados}`. `.env` + `.env.example` + `config/services.php` (`n8n.secret`). Instruções em `.docs/INSTRUCOES/n8n-disparos-cron.md` (link relativo comentado em `routes/api.php`). Mapeamento: `mensagem`→`texto`, `grupo_id`→`grupo_telegram_id`, `total`→`total_grupo_horario`, `tipo`→`modo`. Nenhum campo novo necessário | OpenCode (Muse Spark) |
| 26/09/2026 | 3.1.1 | Deploy Vercel | ✅ Corrigido 500 `database.sqlite does not exist` em produção: `vercel.json` fixava `DB_CONNECTION=sqlite` e o dashboard não o sobrescrevia — removidos `DB_CONNECTION`/`DB_DATABASE`/`CACHE_STORE` fixos (dashboard governa), `APP_DEBUG=false` (estava expondo stack traces), `.env.vercel` virou template pgsql e instruções em `.docs/INSTRUCOES/vercel-pgsql.md` (vars do dashboard + migrate/seed + redeploy) | OpenCode (Muse Spark) |

---

## 🔄 Próximas Implementações

- [x] Instalação do Socialite e provider Telegram (apenas para o robô) — já existia (v2.0.0)
- [x] Criação de migrations: `grupos`, `agendamentos` — já existiam; v2.1.0 adiciona `texto/posicao/total_grupo_horario/ip_origem/cancelado/ordem` (migration `000024`)
- [x] Models e relacionamentos — `posicao/total`, `textoMensagem()` verbatim, scopes `ativos/ordenados/naoCancelados`
- [x] Rotas públicas (agendamento) e protegidas (admin) — `/`, `/agendar`, `/agendamento/{id}/confirmacao`, `/meus-agendamentos`, `admin.cancelar`
- [x] Controladores: Agendamento (v2), Grupo (ordem), Admin (cancelar), Telegram (robô/admin)
- [x] Job de disparo + Scheduler a cada minuto — `EnviarSenhaTelegram` + `agendafacil:disparar` (já existiam; validados; cancelados excluídos)
- [x] Telas Blade com identidade visual Telegram (responsivas) — `senhabaixa/{inicio,agendar,confirmacao,meus}`
- [x] Cálculo e exibição da posição — `proximaPosicao()` por grupo+minuto, "Posição: X de Y" na confirmação
- [x] Área administrativa completa (grupos, agendamentos, configurações) — + cancelar, ordem, filtro cancelado
- [x] Testes de fluxo completo — 7 checks funcionais v2 (posição, texto verbatim, cancelado fora da fila, job marca erro sem travar)
- [x] Validação e tratamento de erros de envio — `mensagem_erro`, status erro, reenvio manual
- [ ] Teste de disparo real grupo + pessoa com a cliente (link de teste)
- [ ] Worker de fila em produção (`queue:work`) e cron do scheduler (`schedule:run` a cada minuto)

---

## ✅ v2.2.0 — Fluxo único /acesso (25/09/2026)

Dois fluxos paralelos unificados num só (`/acesso`), conforme imagens `01–08.jpeg`:

- **1. Acesso**: textos do print 01 — "Desbloquear / Informe os 4 últimos dígitos do telefone do agendamento / Continuar".
- **2. Confirmar os Dados**: somente leitura do agendamento cadastrado — Nome do grupo, Mensagem (nome + senha `xNÚMERO`, ex: `Roberta lallison x8`), Horário (H:i), Tipo de disparo (`dados_senha`, ex: `Aberto SEM OK`), Telefone + aviso de responsabilidade + botão "Confirmar dados". Sem edição, sem redirect ao `telegram.connect`.
- **3. Código**: 6 dígitos enviados **direto no grupo da etapa 2** via bot do admin; tela do print 04 — "Código Telegram / Enviar / Reenviar código". Tela "Quase lá / direcionando Robô" (03.jpeg) eliminada.
- **4. Grupos do Telegram**: SOMENTE o grupo do agendamento + "Eu confirmo que esse grupo é o correto" (prints 06/07); exige `codigo_ok`.
- **5. Concluído** (`GET /concluido`): "Concluído / Grupo confirmado. Pode fechar esta janela." (print 08); restante é o disparo automático.
- **Sem agendar (oculto)**: etapa "6. Agendar" removida; `/` redireciona a `/acesso`; `/agendar` separado oculto (rotas preservadas); link "Meus agendamentos" removido do topo.
- **Testes**: `AgendaFacilFlowTest` atualizado (12 passando).

---

## ✅ v2.1.0 — Implementação SenhaBaixa (24/09/2026)

Fluxo antigo preservado (`/acesso` e demais rotas intactas); v2 adicionado em paralelo.

- **Banco** (`000024`): `agendamentos.texto/posicao/total_grupo_horario/ip_origem`, `user_id` nullable, status +`cancelado` (rebuild no SQLite por causa do CHECK do enum; ALTERs no MySQL), `grupo_telegrams.ordem`.
- **Negócio**: `proximaPosicao()`/`totalGrupoHorario()` por grupo+minuto (cancelados fora); `textoMensagem()` devolve o texto exato; scheduler+job existentes validados (só `pendente`+vencido dispara).
- **Rotas/views**: `/` (apresentação + Agendar agora), `/agendar` (grupo ordenado + data/hora + texto), `/agendamento/{id}/confirmacao` (posição X de Y), `/meus-agendamentos` (histórico por sessão).
- **Admin**: cancelar (com proteção p/ já-enviado), campo ordem, filtro/badge cancelado, listas ordenadas.
- **Botões**: gradiente `135deg #2AABEE→#1B87B8` + hover (eleva, brilho, sombra) + active (pressiona, `scale(.98)`, toque sem highlight) + `focus-visible` — em `telegram.css` e no layout.

---

## 📌 Decisões Confirmadas com a Cliente (24/09/2026)

1. **Visitante não precisa de conta no Telegram** — acessa por link, agenda pelo navegador
2. **Texto livre** — o que a pessoa digita é o que aparece no grupo (ex: `Roberta lallison x8`)
3. **Ordem = posição** — quem aparece primeiro no grupo é a primeira senha
4. **Disparo no horário exato** — envios antes do horário "queimam" e não valem
5. **Vários grupos** — a pessoa escolhe o grupo de destino a cada agendamento
6. **Sem apresentação na porta** — a equipe organiza o caderno pela ordem no grupo
7. **Link de convite** — a pessoa pode encaminhar o link para outras agendarem

---

*Este arquivo será atualizado a cada implementação concluída, mantendo o histórico de evolução do sistema.*
