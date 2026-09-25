# Relatório — Código Morto e Redundante

**Projeto:** AgendaFácil · **Data:** 25/09/2026 · **Escopo:** `app/`, `routes/`, `resources/views/`, `config/services.php`
**Método:** cruzamento rota → controller → view + busca de chamadores (`grep`) para cada método/atributo.
**Legenda:** 🔴 morto (sem rota, sem chamador, sem render) · 🟡 adormecido (com rota, mas fora do fluxo e sem link na UI) · 🔵 redundante (duplicação)

> Nada foi removido — este é só o diagnóstico. Os itens 🟡 podem ser intencionais (fallback/compat); remover exige decisão.

---

## 🔴 Código morto

| # | Local | Evidência |
|---|---|---|
| 1 | `app/Http/Controllers/DisparoController.php:49` — `processarFila()` | Sem rota (o scheduler usa o comando `ProcessarAgendamentos`, `routes/console.php:13`); citado só no CHANGELOG e num texto da tela de configurações. Se removido, o `use Illuminate\Http\Request` do arquivo também sai. |
| 2 | `app/Services/TelegramBotService.php:109` — `resolverChatIdPorUsername()` | Zero chamadores (a vinculação automática por `@username` foi retirada do `AcessoController@codigo`). |
| 3 | `app/Services/TelegramBotService.php:36` — `bot()` | Zero chamadores (só `botUsername()` e `testarConexao()` são usados). |
| 4 | `app/Models/User.php:155` — `telegramConectado()` | Zero chamadores (só citado no CHANGELOG). |
| 5 | `app/Models/User.php:150` — `gruposTelegram()` | Zero chamadores (só citado no CHANGELOG). |
| 6 | `app/Http/Controllers/AgendamentoController.php:166` — `inicio()` | Sem rota (`/` virou closure que redireciona ao `/acesso`, `routes/web.php:26`). Consequência: `resources/views/senhabaixa/inicio.blade.php` está órfã. |
| 7 | `resources/views/agendafacil/confirmar-grupo.blade.php` | Nenhum controller a renderiza mais (`GrupoController@confirmar` agora redireciona ao concluído); é a única referência à rota `grupos.confirmar-final`. View + rota órfãs. |
| 8 | `app/Http/Controllers/HashTestController.php` — `store/show/update/destroy` + imports | Stubs vazios de scaffold; `use Request`, `use Inertia`, `use Response` sem uso no arquivo (só `index` é roteado, via `routes/dev.php:9`). |
| 9 | `app/Http/Controllers/AgendamentoController.php:5` — `use App\Http\Controllers\AcessoController;` | Import redundante: a classe já está no mesmo namespace. |
| 10 | `routes/web.php` — imports + comentários | `use Illuminate\Foundation\Application` e `use Inertia\Inertia` sem uso; bloco `Welcome` comentado (linhas 17–24) e `// return Inertia::render('Dashboard')` (linha 107). |
| 11 | `app/Models/User.php:5` — `// use Illuminate\Contracts\Auth\MustVerifyEmail;` | Import comentado de scaffold. |
| 12 | `config/services.php:40` — `'client_id' => null` | Chave nunca lida por nenhum código. |

## 🟡 Adormecido (roteado, mas fora do fluxo)

| # | O que | Situação |
|---|---|---|
| A | Widget/Socialite: `TelegramController@connect/callback`, `conectar-telegram.blade.php`, `POST /telegram/disconnect` | O fluxo único não passa mais por aqui (código vai direto ao grupo); rotas seguem ativas mas sem nenhum link. |
| B | Fluxo manual legado: `GET /painel`, `GET /agendamento/novo`, `POST /agendamento`, `GET /agendamento/{agendamento}/concluido`, `POST /grupos/confirmar-final`, `DisparoController@dispararAgora` | Sem links na UI (views `dashboard`, `agendar`, `concluido` legadas só referenciadas entre si). |
| C | SenhaBaixa v2 oculto: `/agendar`, `/agendamento/{id}/confirmacao`, `/meus-agendamentos` + views `senhabaixa/{agendar,confirmacao,meus}` | Intencional por compatibilidade (comentário em `routes/web.php:28-29`); sem links. |
| D | `routes/api.php` — `GET /api/user` (scaffold Sanctum) | Verificar se tokens API são usados antes de remover. |
| E | `routes/dev.php` incluído **incondicionalmente** (`routes/web.php:114`) | `/test-hash/{password}`, `/clear-cache` e `/link-storage` expostos em produção sem proteção; `/run-dump-autoload` usa `Auth::user()->is_admin`, atributo que **não existe** no `User` (sempre cai no `abort(403)`) — rota quebrada além de indevida. |

## 🔵 Redundâncias

| # | O que | Sugestão |
|---|---|---|
| R1 | `telefoneComDdi()` idêntico em `AdminController` e `CadastroVisitanteController` | Extrair para um helper único (ex: método estático no `User` ou `App\Support\Telefone`). |
| R2 | `visitanteAtual()` + `agendamentoAtual()` idênticos em `AcessoController` e `GrupoController` | Extrair para trait (ex: `HasFluxoVisitante`) ou método compartilhado. |
| R3 | Bloco `.tg-steps` repetido nas 5 views do fluxo (`acesso`, `confirmar-dados`, `codigo`, `selecionar-grupo`, `concluido-unificado`) | Componente Blade único (ex: `<x-tg-steps etapa="2" />`). |
| R4 | `config/services.php`: `telegram.token` e `telegram.client_secret` apontam para o mesmo `TELEGRAM_TOKEN` | Padronizar em uma chave só (o `TelegramBotService` já lê ambas). |
| R5 | Número antigo `+55 41 98843-2148` restante em comentários (`AcessoController:39-40`, `User.php:101`) | Higiene: atualizar/remover após a troca de placeholder. |

## Verificados e OK (não são mortos)

- `TelegramBotService::forBot/listarGrupos/testarConexao/botUsername`, `Agendamento::proximaPosicao/totalGrupoHorario/proximaSenhaNumero/textoMensagem`, `DisparoController@reenviar`, `TelegramTesteController` (todos os 4 métodos), `admin.reenviar`, `admin.telegram.*` — com rota e link/chamador ativos.
- `resources/js/*` + `views/app.blade.php` — vivos via autenticação Inertia (`Auth/*`, `ProfileController`).
- `AgendamentoController@agendar/salvar/confirmacao/meus` — roteados (fluxo oculto intencional).
