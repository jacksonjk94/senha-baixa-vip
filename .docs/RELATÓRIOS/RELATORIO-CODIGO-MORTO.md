# Relatório — Código Morto e Redundante

**Projeto:** AgendaFácil · **Data:** 26/09/2026 · **Escopo:** `app/`, `routes/`, `resources/views/`, `resources/js/`, `config/services.php`
**Método:** cruzamento rota → controller → view + busca de chamadores (`grep`) para cada método/atributo/import.
**Legenda:** 🔴 morto (sem rota, sem chamador, sem render) · 🟡 adormecido (com rota, mas fora do fluxo e sem link na UI) · 🔵 redundante (duplicação)

> Nada foi removido — este é só o diagnóstico. Os itens 🟡 podem ser intencionais (fallback/compat); remover exige decisão.
> Evolução vs. 25/09/2026: confirmados todos os 12 🔴 + 5 🟡 + 5 🔵 anteriores; adicionados 13–24 🔴 (ProfileController, `User::agendamentos`, scaffold `services.*`, `resources/js` residual, `team-invitation`) e F–H 🟡 (cadastro sem inbound, `codigo.reenviar` sem form, scaffold Inertia/Jetstream).

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
| 8 | `app/Http/Controllers/HashTestController.php:35,43,51,59` — `store/show/update/destroy` + imports | Stubs vazios de scaffold; `use Request`, `use Inertia`, `use Response` sem uso (só `index` é roteado via `routes/dev.php:9`, que usa só `Hash`). |
| 9 | `app/Http/Controllers/AgendamentoController.php:5` — `use App\Http\Controllers\AcessoController;` | Import redundante: mesma namespace, resolve sem `use`. |
| 10 | `routes/web.php:16,18` — imports + comentários | `use Illuminate\Foundation\Application` (só usado no bloco comentado `Welcome:24`) e `use Inertia\Inertia` (só em comentários `:21` e `:122`) sem uso real; bloco `Welcome` comentado (linhas 20–27) e `// return Inertia::render('Dashboard')` (linha 122). |
| 11 | `app/Models/User.php:5` — `// use Illuminate\Contracts\Auth\MustVerifyEmail;` | Import comentado de scaffold. |
| 12 | `config/services.php:40` — `'client_id' => null` | Chave nunca lida (`grep services.telegram.client_id` vazio). |
| 13 | `app/Models/User.php:145` — `agendamentos()` **(novo)** | Zero chamadores (`grep ->agendamentos(` vazio em `app/`, `resources/views/`, `routes/`; `AdminController` usa `Agendamento::with(['user','grupo'])`, nunca `$user->agendamentos`). |
| 14 | `app/Http/Controllers/ProfileController.php:20,30,46` — `edit/update/destroy` **(novo)** | Sem rota (`grep ProfileController` só acha a definição; nenhum `Route` em `routes/*.php` referencia; `route('profile.*')` nas Vue é Jetstream, não este controller Breeze; `tests/Feature/ProfileTest.php:/profile` dá 404 pré-existente). |
| 15 | `config/services.php:17-19,21-23,25-29,31-35` — `postmark/resend/ses/slack` **(novo)** | Scaffold nunca lido (`grep services\.(postmark\|resend\|ses\|slack)` vazio; `AWS_*` lido via `queue/filesystems/cache.php`, não via `services.ses`). |
| 16 | `resources/js/axios.js` **(novo)** | Zero imports (só `bootstrap.js` importa `axios` direto; interceptor `X-Inertia`/handler `409` nunca executa). Entradas reais do build são só `resources/js/app.js` + `ssr.js` (`vite.config.js:8-9`). |
| 17 | `resources/js/Pages/Dashboard.vue`, `Pages/Welcome.vue` **(novo)** | Sem `Inertia::render` correspondente (`/dashboard` só redireciona a `admin.inicio`, `web.php:121-124`; `Inertia::render('Welcome')` comentado, `web.php:20-27`, `/` redireciona a `agendafacil.acesso`). |
| 18 | `resources/js/Pages/TermsOfService.vue`, `Pages/PrivacyPolicy.vue` **(novo)** | Feature desativada (`config/jetstream.php:61` com `// Features::termsAndPrivacyPolicy()`). |
| 19 | `resources/js/Pages/API/Index.vue` + `Partials/ApiTokenManager.vue`, `Pages/Auth/TwoFactorChallenge.vue`, `Pages/Profile/Show.vue` + Partials (`UpdateProfileInformationForm`, `UpdatePasswordForm`, `TwoFactorAuthenticationForm`, `LogoutOtherBrowserSessionsForm`) **(novo)** | Sem rota/controller que renderize (`ProfileController` renderiza `Profile/Edit`, nunca `Show`; API/2FA sem chamador em `app/`/`routes/`). Correção ao relatório anterior que dava `resources/js/*` como vivo: vivos são só `Auth/Login,Register,ForgotPassword,ResetPassword,ConfirmPassword,VerifyEmail` + `Profile/Edit`. |
| 20 | `resources/js/Layouts/GuestLayout.vue`, `Layouts/AppLayout.vue` (transitivo), `Components/Welcome.vue`, `Banner.vue`, `ApplicationMark.vue` **(novo)** | `GuestLayout` com zero imports; `AppLayout` importado só por `Dashboard/API/Profile/Show` (mortos acima); demais só por `AppLayout`/`Dashboard`. Vivo é só `AuthenticatedLayout.vue` (via `Profile/Edit.vue`). |
| 21 | `resources/views/emails/team-invitation.blade.php` **(novo)** | Só Jetstream Teams, sem uso no fluxo (Teams sem rota ativa no fluxo Blade). |
| 22 | `resources/views/app.blade.php` — root Inertia **(novo, parcial)** | Nenhuma Blade estende; serve só ao scaffold residual `Auth/*` + `Profile/Edit`. Manter enquanto o login Inertia existir; se migrar Auth para Blade, vira órfã total. |

## 🟡 Adormecido (roteado, mas fora do fluxo)

| # | O que | Situação |
|---|---|---|
| A | Widget/Socialite: `TelegramController@connect/callback`, `conectar-telegram.blade.php`, `POST /telegram/disconnect` | Fluxo único não passa mais por aqui (código vai direto ao grupo via `telegram.confirmar-callback`); rotas ativas mas sem nenhum link. `connect` só redireciona `connect→callback` internamente. |
| B | Fluxo manual legado: `GET /painel`, `GET /agendamento/novo`, `POST /agendamento`, `GET /agendamento/{agendamento}/concluido`, `POST /grupos/confirmar-final`, `DisparoController@dispararAgora` | Sem links na UI (views `dashboard`, `agendar`, `concluido` legadas só referenciadas entre si; `dashboard.painel` linkado só pela própria `concluido.blade.php:13` legada). Inclui `agendafacil/dashboard,agendar,concluido.blade.php` dormentes por transitividade (fluxo único usa `concluido-unificado`). |
| C | SenhaBaixa v2 oculto: `/agendar`, `/agendamento/{id}/confirmacao`, `/meus-agendamentos` + views `senhabaixa/{agendar,confirmacao,meus}` | Intencional por compatibilidade (comentário em `routes/web.php:31-32`); links só internos entre si, isolados do fluxo único. |
| D | `routes/api.php` — `GET /api/user` (scaffold Sanctum) | Verificar se tokens API são usados antes de remover. `GET /api/n8n/disparos/processar` é legítimo (chamada externa n8n via `X-N8N-Secret`, sem link UI). |
| E | `routes/dev.php` incluído **incondicionalmente** (`routes/web.php:129`) | `/test-hash/{password}` (oráculo `Hash::make/check` público, senha na URL), `/clear-cache` (10 `Artisan::call` sem auth) e `/link-storage` expostos em produção sem proteção; `/run-dump-autoload` usa `Auth::user()->is_admin`, atributo que **não existe** no `User` (fillable tem `role,active,can_manage_admins`; padrão real é `EnsureIsAdmin` por `role`) — sempre cai no `abort(403)`, rota quebrada além de indevida. |
| F | `GET /cadastro` (`visitante.cadastro`) **(novo)** | `POST salvar` tem form em `cadastro.blade.php:14`, mas nada linka PARA `/cadastro`; fluxo só faz `cadastro→acesso` (`CadastroVisitanteController:45`). |
| G | `POST /codigo/reenviar` (`agendafacil.codigo.reenviar`) **(novo)** | Controller `reenviarCodigo` existe, mas zero `action="{{ route(...) }}"` nas views. |
| H | Cluster Inertia/Jetstream residual (`login`, `password.*`, `verification.*`, `profile.show/edit`, `two-factor.*`, `api-tokens.*`, `teams.*`, `terms/policy`, `dashboard`) **(novo)** | Referenciados só em `resources/js/Pages|Layouts/**.vue` (scaffold). Nenhuma Blade do fluxo os usa. Manter enquanto Auth for Inertia; documentar como dependência do scaffold, não do fluxo Blade. |

## 🔵 Redundâncias

| # | O que | Sugestão |
|---|---|---|
| R1 | `telefoneComDdi()` byte-idêntico em `AdminController:383-392` e `CadastroVisitanteController:53-62` | Extrair para helper único (ex: método estático no `User` ou `App\Support\Telefone`). |
| R2 | `visitanteAtual()` + `agendamentoAtual()` em `AcessoController:480-520` e `GrupoController:101-125` | Extrair para trait (ex: `HasFluxoVisitante`). Atenção: corpos de `visitanteAtual` **divergiram** — `Acesso` valida expiração 2h (`TelegramValidacaoController::VALIDADE_SEGUNDOS` + `forget`), `Grupo` faz só `User::find` — risco de sessão expirada aceita no fluxo de grupos. Unificar pelo comportamento do `Acesso`. |
| R3 | Bloco `.tg-steps` repetido em 9 views `agendafacil/*` (`acesso`, `confirmar-dados`, `confirmar-resumo`, `codigo`, `selecionar-grupo`, `concluido-unificado` + variantes `conectar-telegram`, `confirmar-grupo`, `agendar`); `layouts/telegram.blade.php:28` só tem o CSS | Componente Blade único (ex: `<x-tg-steps etapa="2" />`). Labels divergem (5 etapas vs 6 etapas) — fluxos antigo/novo convivendo; unificar decide o fluxo canônico. |
| R4 | `config/services.php`: `telegram.token` e `telegram.client_secret` apontam para o mesmo `TELEGRAM_TOKEN` | Padronizar em uma chave só (`TelegramBotService` lê ambas; `TelegramController:26-27` precisa setar ambas manualmente). |
| R5 | Número antigo `+55 41 98843-2148` em comentários (`AcessoController:39-40`, `User.php:101`) | Higiene: atualizar/remover após troca de placeholder. |
| R6 | `confirmar-dados.blade.php` vs `confirmar-resumo.blade.php`, `concluido.blade.php` vs `concluido-unificado.blade.php`, `senhabaixa/*` vs `agendafacil/*` **(novo)** | Duas views para mesma etapa 2 (form vs resumo), duas telas finais, dois fluxos completos paralelos. Escolher canônicos (`confirmar-resumo`? `concluido-unificado`?) e depreciar o outro. |

## Verificados e OK (não são mortos)

- `TelegramBotService::forBot/listarGrupos/testarConexao/botUsername/configurado/enviarMensagem`, `Agendamento::proximaPosicao/totalGrupoHorario/proximaSenhaNumero/textoMensagem`, `DisparoController@reenviar/dispararAgora`, `TelegramTesteController` (4 métodos), `N8nDisparoController@processar`, `TelegramValidacaoController@confirmar/sair/dadosBotValidacao/tokenValidacao/hashValido`, `AcessoController::gerarSenhaCodigo`, `admin.reenviar`, `admin.telegram.*`, `admin.telegram-bots.*` — com rota e link/chamador ativos.
- `GrupoTelegram::diasFuncionamento` (via `funcionaEm`), `etiquetaModo` (via 3 Blades), `TelegramBot::defaultBot/scopeActive/markAsDefault/setUsernameAttribute` (mutator framework), `ProcessarAgendamentos@handle` (via scheduler) — vivos.
- `resources/js/Pages/Auth/Login,Register,ForgotPassword,ResetPassword,ConfirmPassword,VerifyEmail` + `Profile/Edit` + `AuthenticatedLayout` + `vendor/ziggy` + `bootstrap.js` + `app.js`/`ssr.js` — vivos via autenticação Inertia.
- `AgendamentoController@agendar/salvar/confirmacao/meus/index/create/store/concluido`, `AcessoController@*` (10 rotas), `AdminController@*` (17 rotas), `AjudaController@botTelegram`, `CadastroVisitanteController@criar/salvar`, `ConfiguracaoController`, `ManualController`, `TelegramBotsController` — roteados com chamador.
- `telegram.confirmar-callback`/`agendafacil.sair` — ativos via `data-auth-url="{{ $confirmarUrl }}"` em `codigo.blade.php:28` (não aparece em `grep route()`, fácil perder).
- `config/services.php: telegram.bot/token/client_secret/redirect`, `n8n.secret` — lidos em `TelegramBotService`, `TelegramController`, `TelegramBotsController`, `TelegramValidacaoController`, `N8nDisparoController`.
