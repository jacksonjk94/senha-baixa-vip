# Relatório — Código Morto e Redundante

**Projeto:** AgendaFácil · **Data:** 28/09/2026 · **Escopo:** `app/` (fluxo + domínio), `routes/` (fluxo + domínio), `resources/views/` (Blade do fluxo), `config/services.php` (chaves do domínio)
**Método:** cruzamento rota → controller → view + busca de chamadores (`grep`) para cada método/atributo/import.
**Legenda:** 🔴 morto (sem rota, sem chamador, sem render) · 🟡 adormecido (com rota, mas fora do fluxo e sem link na UI) · 🔵 redundante (duplicação)

> Nada foi removido — este é só o diagnóstico. Os itens 🟡 podem ser intencionais (fallback/compat); remover exige decisão.
> Scaffold nativo Laravel/Jetstream/Inertia/Sanctum (ex.: `ProfileController` Breeze, `services.php: postmark/resend/ses/slack`, `resources/js/*`, `Layouts/*`, `Welcome/Dashboard/Terms/Privacy/API/Teams`, `app.blade.php`, `GET /api/user`, cluster `login/password/verification/profile/two-factor/api-tokens/teams`) está **fora do escopo** e foi removido deste relatório em 28/09/2026.
> Evolução vs. 25/09/2026: confirmados os itens de domínio anteriores; removidos 10, 11, 14–22 🔴 e D, H 🟡 por serem scaffold nativo.

---

## 🔴 Código morto

| # | Local | Evidência |
|---|---|---|
| 1 | `app/Http/Controllers/DisparoController.php:49` — `processarFila()` | Sem rota (scheduler usa `ProcessarAgendamentos`, `routes/console.php:13`; API usa `N8nDisparoController`, `routes/api.php:16`); citado só no CHANGELOG e em texto da tela de configurações. Se removido, o `use Illuminate\Http\Request` do arquivo também sai. |
| 2 | `app/Services/TelegramBotService.php:109` — `resolverChatIdPorUsername()` | Zero chamadores (vinculação por `@username` retirada do `AcessoController@codigo`). |
| 3 | `app/Services/TelegramBotService.php:35` — `bot()` | Zero chamadores (`grep ->bot(` vazio; `botUsername()`/`testarConexao()` usam `TelegramBot::defaultBot()` direto). |
| 4 | `app/Models/User.php:155` — `telegramConectado()` | Zero chamadores (só citado no CHANGELOG). |
| 5 | `app/Models/User.php:150` — `gruposTelegram()` | Zero chamadores (só citado no CHANGELOG). |
| 6 | `app/Http/Controllers/AgendamentoController.php:166` — `inicio()` | Sem rota (`/` virou closure que redireciona ao `/acesso`, `routes/web.php:29`). Consequência: `resources/views/senhabaixa/inicio.blade.php` órfã por transitividade (só referenciada por este método). |
| 7 | `resources/views/agendafacil/confirmar-grupo.blade.php` | Nenhum controller a renderiza (`GrupoController@confirmar` agora redireciona ao concluído); única referência à rota `grupos.confirmar-final`. View + rota órfãs. |
| 8 | `app/Http/Controllers/HashTestController.php:35,43,51,59` — `store/show/update/destroy` + imports | Stubs vazios de scaffold dev do projeto; `use Request`, `use Inertia`, `use Response` sem uso (só `index` é roteado via `routes/dev.php:9`, que usa só `Hash`). |
| 9 | `app/Http/Controllers/AgendamentoController.php:5` — `use App\Http\Controllers\AcessoController;` | Import redundante: mesma namespace, resolve sem `use`. |
| 10 | `config/services.php:40` — `'client_id' => null` | Chave nunca lida (`grep services.telegram.client_id` vazio). |
| 11 | `app/Models/User.php:145` — `agendamentos()` | Zero chamadores (`grep ->agendamentos(` vazio em `app/`, `resources/views/`, `routes/`; `AdminController` usa `Agendamento::with(['user','grupo'])`, nunca `$user->agendamentos`). |

## 🟡 Adormecido (roteado, mas fora do fluxo)

| # | O que | Situação |
|---|---|---|
| A | Widget/Socialite: `TelegramController@connect/callback`, `conectar-telegram.blade.php`, `POST /telegram/disconnect` | Fluxo único não passa mais por aqui (código vai direto ao grupo via `telegram.confirmar-callback`); rotas ativas mas sem nenhum link. `connect` só redireciona `connect→callback` internamente. |
| B | Fluxo manual legado: `GET /painel`, `GET /agendamento/novo`, `POST /agendamento`, `GET /agendamento/{agendamento}/concluido`, `POST /grupos/confirmar-final`, `DisparoController@dispararAgora` | Sem links na UI (views `dashboard`, `agendar`, `concluido` legadas só referenciadas entre si; `dashboard.painel` linkado só pela própria `concluido.blade.php:13` legada). Inclui `agendafacil/dashboard,agendar,concluido.blade.php` dormentes por transitividade (fluxo único usa `concluido-unificado`). |
| C | SenhaBaixa v2 oculto: `/agendar`, `/agendamento/{id}/confirmacao`, `/meus-agendamentos` + views `senhabaixa/{agendar,confirmacao,meus}` | Intencional por compatibilidade (comentário em `routes/web.php:31-32`); links só internos entre si, isolados do fluxo único. |
| D | `routes/dev.php` incluído **incondicionalmente** (`routes/web.php:129`) | `/test-hash/{password}` (oráculo `Hash::make/check` público, senha na URL), `/clear-cache` (10 `Artisan::call` sem auth) e `/link-storage` expostos em produção sem proteção; `/run-dump-autoload` usa `Auth::user()->is_admin`, atributo que **não existe** no `User` (fillable tem `role,active,can_manage_admins`; padrão real é `EnsureIsAdmin` por `role`) — sempre cai no `abort(403)`, rota quebrada além de indevida. |
| E | `GET /cadastro` (`visitante.cadastro`) | `POST salvar` tem form em `cadastro.blade.php:14`, mas nada linka PARA `/cadastro`; fluxo só faz `cadastro→acesso` (`CadastroVisitanteController:45`). |
| F | `POST /codigo/reenviar` (`agendafacil.codigo.reenviar`) | Controller `reenviarCodigo` existe, mas zero `action="{{ route(...) }}"` nas views. |

## 🔵 Redundâncias

| # | O que | Sugestão |
|---|---|---|
| R1 | `telefoneComDdi()` byte-idêntico em `AdminController:383-392` e `CadastroVisitanteController:53-62` | Extrair para helper único (ex: método estático no `User` ou `App\Support\Telefone`). |
| R2 | `visitanteAtual()` + `agendamentoAtual()` em `AcessoController:480-520` e `GrupoController:101-125` | Extrair para trait (ex: `HasFluxoVisitante`). Atenção: corpos de `visitanteAtual` **divergiram** — `Acesso` valida expiração 2h (`TelegramValidacaoController::VALIDADE_SEGUNDOS` + `forget`), `Grupo` faz só `User::find` — risco de sessão expirada aceita no fluxo de grupos. Unificar pelo comportamento do `Acesso`. |
| R3 | Bloco `.tg-steps` repetido em 9 views `agendafacil/*` (`acesso`, `confirmar-dados`, `confirmar-resumo`, `codigo`, `selecionar-grupo`, `concluido-unificado` + variantes `conectar-telegram`, `confirmar-grupo`, `agendar`); `layouts/telegram.blade.php:28` só tem o CSS | Componente Blade único (ex: `<x-tg-steps etapa="2" />`). Labels divergem (5 etapas vs 6 etapas) — fluxos antigo/novo convivendo; unificar decide o fluxo canônico. |
| R4 | `config/services.php`: `telegram.token` e `telegram.client_secret` apontam para o mesmo `TELEGRAM_TOKEN` | Padronizar em uma chave só (`TelegramBotService` lê ambas; `TelegramController:26-27` precisa setar ambas manualmente). |
| R5 | Número antigo `+55 41 98843-2148` em comentários (`AcessoController:39-40`, `User.php:101`) | Higiene: atualizar/remover após troca de placeholder. |
| R6 | `confirmar-dados.blade.php` vs `confirmar-resumo.blade.php`, `concluido.blade.php` vs `concluido-unificado.blade.php`, `senhabaixa/*` vs `agendafacil/*` | Duas views para mesma etapa 2 (form vs resumo), duas telas finais, dois fluxos completos paralelos. Escolher canônicos (`confirmar-resumo`? `concluido-unificado`?) e depreciar o outro. |

## Verificados e OK (não são mortos)

- `TelegramBotService::forBot/listarGrupos/testarConexao/botUsername/configurado/enviarMensagem`, `Agendamento::proximaPosicao/totalGrupoHorario/proximaSenhaNumero/textoMensagem`, `DisparoController@reenviar/dispararAgora`, `TelegramTesteController` (4 métodos), `N8nDisparoController@processar`, `TelegramValidacaoController@confirmar/sair/dadosBotValidacao/tokenValidacao/hashValido`, `AcessoController::gerarSenhaCodigo`, `admin.reenviar`, `admin.telegram.*`, `admin.telegram-bots.*` — com rota e link/chamador ativos.
- `GrupoTelegram::diasFuncionamento` (via `funcionaEm`), `etiquetaModo` (via 3 Blades), `TelegramBot::defaultBot/scopeActive/markAsDefault/setUsernameAttribute` (mutator framework), `ProcessarAgendamentos@handle` (via scheduler) — vivos.
- `AgendamentoController@agendar/salvar/confirmacao/meus/index/create/store/concluido`, `AcessoController@*` (10 rotas), `AdminController@*` (17 rotas), `AjudaController@botTelegram`, `CadastroVisitanteController@criar/salvar`, `ConfiguracaoController`, `ManualController`, `TelegramBotsController` — roteados com chamador.
- `telegram.confirmar-callback`/`agendafacil.sair` — ativos via `data-auth-url="{{ $confirmarUrl }}"` em `codigo.blade.php:28` (não aparece em `grep route()`, fácil perder).
- `config/services.php: telegram.bot/token/client_secret/redirect`, `n8n.secret` — lidos em `TelegramBotService`, `TelegramController`, `TelegramBotsController`, `TelegramValidacaoController`, `N8nDisparoController`.
- `GET /api/n8n/disparos/processar` — legítimo (chamada externa n8n via `X-N8N-Secret`, sem link UI).
