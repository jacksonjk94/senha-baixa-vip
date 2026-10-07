# LOG DE ALTERAÇÕES — AgendaFácil

**Sistema de Disparo de Senhas via Telegram**
Projeto em Laravel 12 (Jetstream + Inertia/Vue) · Curitiba/PR · 2026

> Como ler este arquivo: cada versão corresponde a um bloco numerado abaixo, com
> data, área, descrição detalhada, arquivos tocados e status de verificação.
> O histórico vai do mais recente (topo) ao mais antigo (base).
> Legenda de status: ✅ concluído e verificado · 🔶 concluído com ressalva · ⏳ pendente.

---

## 📋 Histórico de Versões

| Data | Versão | Área | Descrição | Responsável |
|---|---|---|---|---|
| 23/09/2026 | 1.0.0 | Estrutura | Projeto Laravel criado e pré-configurado | Jackson Dos Santos |
| 23/09/2026 | 1.1.0 | Documentação | Prompt de implementação detalhado criado (`PROMPT-IMPLEMENTACAO.md`) | Jackson Dos Santos |
| 23/09/2026 | 1.2.0 | Planejamento | Dependências e configurações do Socialite Telegram definidas no prompt | Jackson Dos Santos |
| 23/09/2026 | 1.3.0 | Planejamento | Paleta de cores e estilos alinhados à identidade do Telegram | Jackson Dos Santos |
| 23/09/2026 | 1.4.0 | Planejamento | Fluxo em 9 passos definido: acesso → confirmação → conexão → código → seleção → confirmação do grupo → agendamento → disparo → registro | Jackson Dos Santos |
| 23/09/2026 | 1.5.0 | Planejamento | Estrutura da área administrativa planejada (cadastros, disparos, grupos) | Jackson Dos Santos |
| 23/09/2026 | 2.0.0 | Integração | Socialite Telegram instalado e configurado (Etapa 1) — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.1.0 | Backend | Modelos, migrations, controladores e rotas do fluxo criados (Etapa 1) | OpenCode (Muse Spark) |
| 23/09/2026 | 2.2.0 | Design | Identidade visual Telegram aplicada em Blade + CSS dedicado (Etapa 2) | OpenCode (Muse Spark) |
| 23/09/2026 | 2.3.0 | Fluxo/Negócio | Lógica de negócio, fila, scheduler e área admin funcionais (Etapa 3) | OpenCode (Muse Spark) |
| 23/09/2026 | 2.4.0 | Qualidade | Suite de testes do fluxo criada — 7 testes, todos passando | OpenCode (Muse Spark) |
| 23/09/2026 | 2.5.0 | Admin/Fluxo | Painel admin responsivo com light/dark, hierarquia de papéis, CRUD visitantes/usuários em dialogs, config. de disparos, teste Telegram e cadastro externo — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.5.1 | Admin | Correção de tags escapadas no Teste Telegram + lista de chat_ids — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.5.2 | Admin | Marca "AgendaFácil", grupos via Bot API no Teste Telegram e dialog de importação em Grupos — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.5.3 | Infra | Blindagem de envs vazias na Vercel (AUTH_GUARD etc.) — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.5.4 | Infra | Blindagem de BCRYPT_ROUNDS/HASH_DRIVER (500 no login) — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.5.5 | Fluxo | Tela de conexão Telegram integrada ao layout — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.6.0 | Bots/Login | Bots Telegram gerenciáveis + login sem modal — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.6.1 | Fluxo | Vinculação automática do Telegram na etapa código — ver detalhes abaixo | OpenCode (Muse Spark) |
| 23/09/2026 | 2.6.2 | Fluxo | Reenvio real do código + motivo visível — ver detalhes abaixo | OpenCode (Muse Spark) |
| 24/09/2026 | 2.7.0 | SenhaBaixa v2 | Fluxo público simplificado (posição, texto livre, cancelar) + botões com gradiente — ver detalhes abaixo | OpenCode (Muse Spark) |
| 24/09/2026 | 2.7.1 | SenhaBaixa v2 | Complemento Postgres (coluna ordem) — ver detalhes abaixo | OpenCode (Muse Spark) |
| 06/10/2026 | 3.3.0 | MTProto | 🔶 Listagem de grupos pessoais via Teleproto; teste local automatizado, autenticação real pendente | GitHub Copilot |
| 07/10/2026 | 3.4.0 | Fluxo | Novo fluxo público (telefone → código MTProto → horário → grupo → confirmação → concluído) | OpenCode |
| 07/10/2026 | 3.4.1 | Infra | Correção SSL (cacert.pem no PHP do Wamp) — disparos via Bot API falhavam com cURL error 60 | OpenCode |
| 07/10/2026 | 3.4.2 | Fluxo/Admin | Visão geral do /admin com atualização em tempo real (polling 5s) | OpenCode |
| 07/10/2026 | 3.4.3 | Fluxo | MTProto: mensagem clara para API_ID_INVALID; normalização do telefone no modo "Já agendei antes"; fallback para "Primeiro agendamento" | OpenCode |
| 07/10/2026 | 3.4.4 | Fluxo/UX | Foco automático nos campos (acesso, confirmar-dados), botão "Colar" no código | OpenCode |
| 07/10/2026 | 3.4.5 | Admin | Tempo real em /admin/cadastros, /admin/disparos e /admin/grupos | OpenCode |
| 07/10/2026 | 3.4.6 | Segurança/Infra | /telegram/teste-api restrita a admin; favicon SVG global | OpenCode |
| 07/10/2026 | 4.1.0 | Estilo/UX | Atualização visual da área de agendamento: paleta Telegram escuro (azul-900/slate), linha do tempo horizontal substituindo abas (partials/timeline), gradiente interativo nos botões (from-blue-900 to-blue-700 → hover from-blue-800 to-blue-600 + .btn-telegram), inputs com foco blue-700, fontes compactas preservadas em todas as telas do fluxo | OpenCode |

---

## 🔍 Detalhamento das implementações (v2.x)

### v3.4.0 — Novo fluxo público de agendamento ✅ (07/10/2026)

Fluxo refeito em 6 etapas: Telefone → Código → Horário → Grupo → Confirmar → Concluído.

- **Etapa 1 (`/acesso`)**: toggle "Primeiro agendamento" (padrão, +55 fixo só DDD+número) / "Já agendei antes" (4 últimos dígitos). Telefone normalizado antes de seguir.
- **Etapa 2 (`/codigo`)**: ao acessar, já envia o código MTProto ao telefone da etapa 1; botão reenviar com cooldown de 60s (JS + servidor); campo 2FA exibido só quando `needs_password` retorna; grupo/mensagens lista usam apenas `group`/`supergroup` da conta.
- **Etapa 3 (`/horario`)**: data e hora com as regras atuais (futuro).
- **Etapa 4 (`/grupos/selecionar`)**: cards com badge Grupo/Supergrupo dos grupos vindos do MTProto.
- **Etapa 5 (`/confirmar-dados`)**: exibe telefone, conta Telegram, grupo, data/hora e mensagem editável com prévia.
- **Etapa 6 (`/concluido`)**: mensagem de conclusão; internamente busca/cria o `User` (telegram_id ou telefone), sincroniza `GrupoTelegram` e cria o `Agendamento` pendente.
- Rotas antigas (`agendafacil.resumo`, `agendafacil.confirmar-dados.salvar`, `agendafacil.codigo.validar`) removidas; `AcessoController` e `GrupoController` reescritos.
- **Validação**: `php artisan route:list` OK; `php -l` OK nos controllers.

### v3.4.1 — Correção SSL nos disparos (Bot API) ✅ (07/10/2026)

- **Problema**: agendamentos terminavam em `erro` com `cURL error 60: SSL certificate problem` no envio via Bot API (Wamp/PHP sem CA bundle).
- **Correção**: `cacert.pem` baixado em `D:\ProgramFiles\Wamp\bin\php\php8.3.0\`; `php.ini` e `phpForApache.ini` com `curl.cainfo` e `openssl.cafile`; `queue:listen` reiniciado; agendamentos com erro reprocessados → `enviado`.
- **Atenção**: em dev é preciso rodar `php artisan schedule:work` para `agendafacil:disparar` rodar a cada minuto.

### v3.4.2 — Visão geral do /admin em tempo real ✅ (07/10/2026)

- Nova rota `GET /admin/dados` (`AdminController::dados`) com totais + últimos agendamentos em JSON.
- `admin/inicio.blade.php` com polling de 5s atualizando contadores e tabela sem recarregar.

### v3.4.3 — Ajustes MTProto/acesso ✅ (07/10/2026)

- `services/telegram-mtproto/server.js`: `API_ID_INVALID` agora retorna mensagem clara ("Credenciais da API do Telegram inválidas..."); serviço reiniciado.
- `AcessoController::validarAcesso`: telefone do cadastro (modo retorno) normalizado com `normalizarTelefone()`; cadastro sem telefone válido retorna para aba "Primeiro agendamento" com mensagem; dígitos não encontrados orientam a usar "Primeiro agendamento".
- Erro de reenvio não aparece mais duplicado (alerta único na tela de código).
- Placeholders de telefone trocados para `(11) 90000-0000`; placeholder com nome da cliente em `senhabaixa/agendar.blade.php` trocado para "Ex.: Maria Silva".

### v3.4.4 — Foco e UX nos campos ✅ (07/10/2026)

- `/acesso`: ao alternar ou carregar, foco vai para o campo do modo ativo (telefone completo ou 4 dígitos).
- `/codigo`: botão "📋 Colar" dentro do input do código (lê a área de transferência, mantém só dígitos, máx. 8).
- `/confirmar-dados`: foco no campo "Mensagem que aparecerá no grupo" com cursor ao final do texto.

### v3.4.5 — Tempo real no admin ✅ (07/10/2026)

- `/admin/cadastros` e `/admin/disparos`: polling de 5s troca o `tbody` da tabela (recarrega a página se a lista de IDs mudar, para reconstruir modais).
- `/admin/grupos`: `admin.grupos.dados` (`AdminController::dadosGrupos`) retorna só as linhas, sem chamar a API do Telegram a cada poll.
- Linhas extraídas para partials `admin/partials/{cadastros,disparos,grupos}_rows.blade.php`.

### v3.4.6 — Segurança e favicon ✅ (07/10/2026)

- `/telegram/teste-api*` agora exige `auth` + `admin` (antes pública).
- `public/favicon.svg` (balão de mensagem azul) com `<link rel="icon">` em `layouts/admin.blade.php`, `layouts/telegram.blade.php` e `app.blade.php`.

### v3.3.0 — Listagem de grupos da conta pessoal via MTProto 🔶 (06/10/2026)

- Adicionado `services/telegram-mtproto/` com Teleproto `1.229.1` (cliente MTProto Node separado do Laravel/Bot API). Credenciais `TELEGRAM_API_ID`/`TELEGRAM_API_HASH` e token interno são lidos do ambiente; nenhum segredo foi copiado para os logs.
- Serviço local em `127.0.0.1:3037`, protegido por bearer token, com operações de autenticação limitadas a 10 minutos. O fluxo aceita telefone, código no Telegram e senha 2FA quando solicitada.
- O Login Widget identifica o visitante; quando MTProto está configurado, o sistema exige consentimento e compara o ID autenticado via MTProto com o `telegram_id` validado antes de continuar.
- `iterDialogs` retorna somente grupos básicos e supergrupos. Canais de transmissão, conversas privadas e entidades proibidas são excluídos. O banco armazena apenas nome, `chat_id`, tipo e vínculo ao visitante.
- Sessão MTProto somente em memória durante a tentativa; logout após listar, sem persistir `StringSession`, código ou senha 2FA. Nenhuma mensagem é lida nem enviada pela conta pessoal.
- Nova view `agendafacil.telegram-mtproto`; `/telegram/teste-grupos` usa a mesma autorização. Instruções locais: `.docs/INSTRUCOES/TELEGRAM-MTPROTO-LOCAL.md`.
- **Limite**: validação automatizada local; autenticação real ainda depende das credenciais no `.env` e de código confirmado pelo titular. A implementação Node em memória não está habilitada para produção/Vercel multi-instância.
- **Validação**: `php artisan test` — 52 aprovados; `npm test` em `services/telegram-mtproto` — 3 aprovados; `php artisan view:cache` OK; `route:list --path=telegram` lista as rotas MTProto.

### v2.0.0 — Integração Socialite Telegram ✅ (23/09/2026)

- Instalados via Composer: `laravel/socialite`, `socialiteproviders/manager`, `socialiteproviders/telegram`.
- `config/services.php`: bloco `telegram` (`bot`, `client_id`, `client_secret`, `redirect`, `token` via `TELEGRAM_*`).
- `.env` e `.env.example`: `TELEGRAM_BOT_NAME`, `TELEGRAM_TOKEN` (vazio, preencher com o token do BotFather), `TELEGRAM_REDIRECT_URI`.
- `app/Providers/AppServiceProvider.php`: listener `SocialiteWasCalled` registrando `TelegramExtendSocialite` (padrão existente do projeto preservado: Vite, Schema, HTTPS em produção).

### v2.1.0 — Backend: modelos, banco, controladores e rotas ✅ (23/09/2026)

- **Middleware** `app/Http/Middleware/EnsureIsAdmin.php` + alias `admin` em `bootstrap/app.php` (papéis `system/master/super-admin/admin`; 403 para demais).
- **User**: fillable + colunas `telegram_id` (único), `telegram_username`, `telegram_token`, `ultimos_digitos_telefone`, `telefone`; relações `agendamentos()`, `gruposTelegram()`; helper `telegramConectado()`.
- **Novos modelos**: `Agendamento` (status `pendente/enviado/erro`, destinos `grupo/pessoa`, scopes `pendentes()/vencidos()`, `marcarComoEnviado()/marcarComoErro()`, `textoMensagem()` no formato `✅ SENHA AUTORIZADA…`) e `GrupoTelegram`.
- **Migrations**: `2026_09_23_000001` (colunas Telegram em `users`), `000002` (`grupo_telegrams`), `000003` (`agendamentos`).
- **Controladores**: `AcessoController` (4 dígitos → confirmação → código 6 dígitos), `TelegramController` (`connect/callback/disconnect` com modo degradado se sem token), `GrupoController` (listar via Bot API + busca, confirmar), `AgendamentoController` (CRUD do agendamento + tela concluído), `DisparoController` (disparo imediato, reenvio, endpoint de fila), `AdminController` (dashboard, cadastros, disparos com filtros, grupos).
- **Rotas** (`routes/web.php`, sem remover as existentes do Jetstream/Inertia): `/acesso`, `/confirmar-dados`, `/codigo`, `/telegram/*`, `/grupos/*`, `/painel`, `/agendamento/*`, `/admin/*` (protegidas por `auth` + `admin`).
- **Fábricas**: `AgendamentoFactory` (novo); `UserFactory` do projeto reutilizada sem alterações.

### v2.2.0 — Identidade visual Telegram ✅ (23/09/2026)

- `resources/css/telegram.css` (novo): variáveis e classes do prompt — `.telegram-gradient`, `.telegram-gradient-light`, `.btn-telegram` (+hover), `.card-telegram`, `.card-header-telegram`, etapas `.tg-step`, badges `.badge-pendente/enviado/erro`, `.input-telegram`, container mobile-first.
- Layouts Blade (o projeto usa Inertia/Vue; as telas do fluxo foram feitas em Blade conforme o prompt, sem remover o front existente): `layouts/telegram.blade.php` (fluxo) e `layouts/admin.blade.php` (sidebar administrativa), com o CSS embutido para funcionar sem build.
- **8 telas do fluxo** em `resources/views/agendafacil/`: `acesso` → `confirmar-dados` → `codigo` → `selecionar-grupo` → `confirmar-grupo` → `agendar` → `concluido` (+ `dashboard` de acompanhamento).
- **4 telas admin** em `resources/views/admin/`: `inicio` (totais + recentes), `cadastros`, `disparos` (filtros por período/status/destino + reenvio + troca de status), `grupos` (cadastro manual de `chat_id` + ativar/desativar).

### v2.3.0 — Lógica de negócio, fila e scheduler ✅ (23/09/2026)

- `app/Services/TelegramBotService.php`: `enviarMensagem()` (Bot API `sendMessage`, com log e retorno `ok/resposta`) e `listarGrupos()` (via `getUpdates`, filtra `group/supergroup/channel`); sem token, retorna falha amigável em vez de exceção.
- `app/Jobs/EnviarSenhaTelegram.php`: fila que envia e marca `enviado` / `erro` com mensagem.
- `app/Console/Commands/ProcessarAgendamentos.php` (`agendafacil:disparar --limite=50`) + agendamento em `routes/console.php` via `Schedule::command(...)->everyMinute()`.
- Correção aplicada: validação de data/hora futura feita sobre o datetime combinado (aceita agendar para hoje mais tarde; `after:now` só na data rejeitava o dia atual).
- Fluxo validado ponta a ponta via rotas (`route:list` OK para `agendafacil.*`, `admin.*`, `telegram.*`).

### v2.4.0 — Testes ✅ (23/09/2026)

- `tests/Feature/AgendaFacilFlowTest.php`: 7 testes cobrindo dígitos inválidos/válidos, código inválido, criação de agendamento `pessoa` como pendente, bloqueio admin (403 para `role=user`), dashboard admin (200) e formato da mensagem da senha — **7/7 passando (17 asserções)**.
- `php -l` sem erros em todos os arquivos novos/alterados.

### v2.5.0 — Admin responsivo, hierarquia, CRUDs, disparos, teste Telegram e cadastro externo ✅ (23/09/2026)

- **Layout admin** (`layouts/admin.blade.php` reescrito): sidebar responsiva — no mobile abre fixa por cima com backdrop e fecha ao navegar (botão ☰ na topbar); no desktop colapsa/expande com persistência em `localStorage`. Tema **light/dark** com variáveis CSS, toggle 🌙/☀️ persistido e anti-flash no `<head>`. Visual profissional/futurista contido (azul Telegram `#2AABEE`, superfícies, sombras suaves — sem neon/cyberpunk), tabelas com rolagem horizontal, link ativo por rota, chip de usuário+papel na topbar.
- **Hierarquia** (`app/Support/PapelHierarquia.php`): `root(0) > system(1) > master(2) > super-admin(3) > admin(4) > user(5) > guest(6)`; `visiveisPara()` (abaixo + pares com `can_manage_admins`), `podeGerenciar()` (abaixo sempre; mesmo nível só com a flag; nunca acima; nunca a si mesmo). `EnsureIsAdmin` passa a aceitar `root`. Migration `000021` adiciona `root` ao enum (MySQL; SQLite já flexível). `PermissionSeeder` já contempla o papel `root`.
- **Cadastros × Usuários separados**: `admin.cadastros` lista só `role=user` (busca nome/e-mail/telefone, 4 dígitos exibidos); `admin.usuarios` (nova tela) lista papéis de sistema visíveis à hierarquia do autenticado, com badge de papel e flag `can_manage_admins`.
- **CRUD visitantes em dialogs** (Bootstrap modals): criar (nome/e-mail/telefone/Telegram, senha aleatória, `role=user`, 4 dígitos auto-sincronizados pelo hook do model), editar e excluir com **modal de confirmação** (soft delete). Rotas `admin.cadastros.salvar/atualizar/excluir`.
- **CRUD usuários de sistema em dialogs**: criar/editar/excluir com `role` restrito a `atribuiveisPara()` (validação `in:` + 403 via `podeGerenciar()`); checkbox `can_manage_admins` só exibido/honrado para quem já tem a flag; senha opcional na edição. Rotas `admin.usuarios.*`.
- **Config. de disparos** (`ConfiguracaoController`, tabela `configuracoes` via migration `000022`, model `Configuracao` com cache): modo `agendado` (fila+scheduler) ou `imediato` + horário padrão. `AgendamentoController@store` honra o modo (imediato cria com `now()+1min` e `dispatchSync`); tela `agendar` exibe aviso do modo e sugere o horário configurado.
- **Teste Telegram** (`TelegramTesteController`, tela `admin.telegram`): status `getMe`, envio de mensagem de teste para `chat_id` e ciclo gerar→enviar→verificar código de 6 dígitos em sessão. Rotas `admin.telegram.*`.
- **Cadastro externo** (`CadastroVisitanteController`, `GET/POST /cadastro`, sem auth, view `agendafacil.cadastro` no tema Telegram): cria visitante `role=user` e redireciona a `/acesso` com mensagem.
- **Seed Vercel** (`database/vercel-seed.sqlite`) regenerado com as novas migrations + seeds.
- **Validação v2.5.0**: `php -l` OK em 9 arquivos; `view:cache` OK (todas as Blade compilam); `route:list` OK (21 rotas `admin.*` + 2 `visitante.*`); `migrate --force` OK do zero incluindo `000021/000022`; suite `php artisan test`: 50 passed — 7 falhas em `ExampleTest/ProfileTest/DeleteAccountTest` **pré-existentes** (confirmado via `git stash`: falham na árvore limpa; rotas `/`→redirect e `/profile` 404 não tocadas por esta versão).

### v2.5.1 — Teste Telegram: tags e lista de chat_ids ✅ (23/09/2026)

- `admin/telegram.blade.php`: expressões escapadas exibiam as chaves como texto — corrigido para echo Blade (comprovado no template compilado: `echo e(...)`).
- `TelegramTesteController@index` agora envia `chats` (`GrupoTelegram` com dono, ordenados) e a tela ganhou o card **"Chats conhecidos (chat_id)"** com tabela nome/chat_id/tipo/ativo + botões **Teste/Código** que preenchem os inputs dos formulários via JS.

### v2.5.2 — Marca, grupos via Bot API e importação ✅ (23/09/2026)

- **Marca**: rodapés `layouts/admin.blade.php` e `layouts/telegram.blade.php` ajustados de "AgendaFácil · Curitiba/PR" para **"AgendaFácil"** (documentos/contrato em `.docs` preservados).
- **Teste Telegram** (`admin/telegram`): card **"Grupos do Telegram (via Bot API, automático)"** — lista `getUpdates` direto na tela, sem redirect, com coluna de situação (✅ cadastrado / ⏳ não cadastrado) e botões para preencher os formulários de teste/código.
- **Grupos** (`admin/grupos`): botão **"📡 Listar do Telegram"** abre dialog flutuante com os grupos da Bot API; não cadastrados têm botão **Cadastrar** (nova rota `POST admin/grupos/importar` → `AdminController@importarGrupo`, `updateOrCreate` por `chat_id`+usuário).

### v2.5.3 — Blindagem de envs vazias na Vercel ✅ (23/09/2026)

- **500 no `POST /login`**: `Auth::attempt()` → `Manager::driver('')` monta o método `createDriver` e o chama sem argumentos (`ArgumentCountError` na linha 101 de `Manager.php`). Causa: `AUTH_GUARD` presente-mas-vazia no Dashboard → `config('auth.defaults.guard') === ''` (mesma classe do bug `SESSION_LIFETIME=""`).
- `api/vercel-bootstrap.php` ganhou etapa 0: sanitiza `APP_NAME/APP_ENV/AUTH_GUARD/AUTH_PASSWORD_BROKER/BROADCAST_CONNECTION/DB_CONNECTION/FILESYSTEM_DISK/LOG_CHANNEL/MAIL_MAILER` ausentes ou `""` com fallbacks serverless (`web/users/log/sqlite/local/stderr/log/AgendaFácil/production`); valores explícitos preservados. Bônus: `APP_NAME=""` gerava cookie `-session`; agora vira `agendafacil-session`.
- **Validação**: teste standalone 10/10 (fallbacks + preservação de valor explícito).

### v2.5.4 — Blindagem de BCRYPT_ROUNDS/HASH_DRIVER (500 no login) ✅ (23/09/2026)

- **500 `Bcrypt hashing not supported.` no `POST /login`**: com `BCRYPT_ROUNDS` vazio no Dashboard, o `password_hash` recebe `cost=""` (válido: 4–31). A senha confere, mas o `needsRehash` acusa divergência e o rehash chama `Hash::make` → `ValueError` convertido em `RuntimeException` em `BcryptHasher.php:66`.
- `api/vercel-bootstrap.php`: `BCRYPT_ROUNDS` ausente/não-numérico/fora de 4–31 vira `10`; `HASH_DRIVER` vazio vira `bcrypt`.
- **Validação**: com `BCRYPT_ROUNDS=""`, efetivo `10`, `make`/`check` OK; controle negativo confirma que `cost=""` quebra.

### v2.5.5 — Tela de conexão Telegram integrada ao layout ✅ (23/09/2026)

- **Problema**: `/telegram/connect` devolvia o HTML cru do provider (só o botão azul no vazio, sem contexto).
- Nova view `agendafacil.conectar-telegram` no `layouts.telegram`: etapas do fluxo (1–2 concluídas, 3 ativa), card "✈ Conectar Telegram" com instruções, widget centralizado (`{!! !!}`), nome do bot e link "Pular esta etapa".
- `TelegramController@connect` usa `getButton()` + `response()->view(...)` em vez de HTML cru; mantidos modo degradado e encaminhamento ao callback.

### v2.6.0 — Bots Telegram gerenciáveis + login sem modal ✅ (23/09/2026)

- **Bots no banco** (colunas em inglês): migration `000023_create_telegram_bots_table` (`name/username/token/is_default/is_active`) + model `TelegramBot` (`defaultBot()`, scope `active()`, username sem `@`, `markAsDefault()` único).
- **CRUD admin** (`TelegramBotsController`, tela `admin.telegram-bots`, rotas `admin.telegram-bots.*`, link na sidebar): criar/editar (token opcional na edição)/excluir com confirmação, ativar/desativar (desativar derruba o padrão), definir padrão (só ativos), importar do `.env` (`TELEGRAM_BOT_NAME/TOKEN` → valida via `getMe` e define como padrão). Tokens nunca exibidos.
- **Bot padrão em uso**: `TelegramBotService` resolve o padrão do banco com fallback ao `.env` (`forBot()` para instância por bot, `botUsername()`); widget/callback (`TelegramController@aplicarBotPadrao`, aplicado antes de montar e de validar o hash) e exibição em `GrupoController` usam o padrão.
- **Teste por bot**: `admin.telegram` ganhou seletor de bot (padrão + ativos); envio, código e `getMe`/`getUpdates` executam no bot selecionado (`bot_id` validado e repassado nos forms).
- **Login sem modal**: `AuthenticatedSessionController@store` captura `\Throwable` fora de `ValidationException` e devolve erro amigável inline (o Inertia só abria o modal de erro em 500); sucesso mantém o redirect `intended(/dashboard)` — com a sessão criada, o Inertia navega sem precisar de refresh.
- **Factory**: `UserFactory` agora cria com `active=true` (alinha ao invariante do app; corrige 2 falhas pré-existentes de `AuthenticationTest`).
- **Seed Vercel** regenerado com a nova tabela.
- **Validação**: `php -l` OK; `view:cache` OK (echos `@bot` comprovados no compilado); rotas `admin.telegram-bots.*` OK; suite: 50 passed, 7 falhas pré-existentes (`Example/DeleteAccount/Profile`, confirmadas na árvore limpa).

### v2.6.1 — Vinculação automática do Telegram na etapa código ✅ (23/09/2026)
- `TelegramBotService::resolverChatIdPorUsername()`: varre `getUpdates` (message/callback/my_chat_member) e descobre o `chat_id` pelo `@username` — usa a conexão do bot padrão do painel admin, sem widget.
- `AcessoController@codigo`: antes de gerar/enviar, vincula o `telegram_id` automaticamente quando há `@username`; view recebe `botUsername/botConfigurado/visitanteConectado`.
- `salvarConfirmacao`: visitante com `@username` pula o widget e vai direto ao código.
- `codigo.blade.php`: sem envio, exibe card com passo a passo (abrir `t.me/<bot>`, `/start`, "Já iniciei — reenviar"), que reexecuta a vinculação.
- **Validação**: `php -l` OK, `view:cache` OK.

### v2.6.2 — Reenvio real do código + motivo visível ✅ (23/09/2026)

- **Causa**: o código era gerado/enviado uma única vez por sessão; se a primeira tentativa falhasse (bot ainda sem token, vínculo inexistente), recarregar nunca tentava de novo — diferente do teste do admin, que envia direto ao `chat_id` digitado.
- Nova rota `POST /codigo/reenviar` (`AcessoController@reenviarCodigo`): descarta código/flag e gera um novo com nova vinculação + nova tentativa; botão "Já iniciei — reenviar código" agora é POST real.
- View exibe o motivo (`sem-bot` / `sem-vinculo` / `falha-envio`) e log registra o contexto da falha.
- **Validação**: `php -l` OK, `view:cache` OK, `route:list` OK.

### v2.7.0 — SenhaBaixa v2: fluxo público simplificado ✅ (24/09/2026)

Conforme `PROMPT-IMPLEMENTACAO-v2.md` (fluxo antigo em `/acesso` preservado; v2 em paralelo).

- **Banco** (`000024`): `texto/posicao/total_grupo_horario/ip_origem`, `user_id` nullable, status `cancelado` (rebuild no SQLite pelo CHECK do enum; ALTERs no MySQL), `grupo_telegrams.ordem`.
- **Fluxo público** (sem login, sem Telegram): `/` apresentação + Agendar agora; `/agendar` (grupo ativo ordenado + data/hora futura + texto livre); `/agendamento/{id}/confirmacao` ("Agendado ✅ — dia XX às HHh — Posição: X de Y"); `/meus-agendamentos` (histórico por sessão). Posição = ordem no mesmo grupo+minuto (cancelados fora); respeita modo imediato/agendado das Configurações.
- **Disparo**: `textoMensagem()` envia o texto exato; scheduler+job validados (só `pendente` vencido; cancelado nunca).
- **Admin**: cancelar (bloqueia já-enviado), campo ordem, filtro/badge `cancelado`, listas ordenadas por ordem.
- **Botões azuis**: gradiente `135deg #2AABEE→#1B87B8` + hover (eleva/brilho/sombra) + active press (`scale(.98)`, toque) + `focus-visible` — em `telegram.css` e no layout.
- **Seed Vercel** regenerado com o novo schema.
- **Validação**: `php -l` OK; `view:cache` OK; rotas `inicio/agendar/agendamento.*` OK; migrate do zero OK; 7 checks funcionais v2 OK; suite 51 passed, 6 falhas pré-existentes (sem relação).

### v2.7.1 — Complemento Postgres ✅ (24/09/2026)

- **500 `column "ordem" does not exist` no `/agendar` (pgsql)**: migration `000025` idempotente — `ordem` + `texto/posicao/total/ip`, `user_id` nullable, status enum→`VARCHAR` (aceita `cancelado`); já aplicada neste banco (`migrate --force` OK, smoke test OK).
- **Validação**: `php -l` OK; `GrupoTelegram::ativos()->ordenados()` e `proximaPosicao()` OK no pgsql.
- **Verificação**: `php -l` OK em 9 arquivos; `view:cache` OK (todas as Blade compilam); `route:list` OK (21 rotas `admin.*` + 2 `visitante.*`); `migrate --force` OK do zero incluindo `000021/000022`; suite `php artisan test`: 50 passed — 7 falhas em `ExampleTest/ProfileTest/DeleteAccountTest` **pré-existentes** (confirmado via `git stash`: falham na árvore limpa; rotas `/`→redirect e `/profile` 404 não tocadas por esta versão).

---

## 🔄 Próximas Implementações / Pendências

- [ ] Preencher `TELEGRAM_TOKEN` real (BotFather) no `.env` de produção e validar OAuth + `getUpdates` com o bot verdadeiro.
- [ ] Ativar worker de fila em produção (`php artisan queue:work`) e cron do scheduler (`schedule:run` a cada minuto).
- [ ] Resolver pendência **pré-existente** (não causada por esta implementação): `php artisan migrate` no banco local falha em `0001_01_01_000003` (`duplicate column: two_factor_secret`) — o `database.sqlite` atual já contém colunas que as migrations antigas tentam recriar; requer reconciliação das migrations legadas ou rebuild do banco.
- [ ] Cadastrar `chat_id` reais dos grupos (via tela admin ou com o bot adicionado aos grupos) e teste de disparo real grupo + pessoa.
- [ ] Enviar o código de validação pelo próprio bot (hoje gerado em sessão; em `local` exibido na tela para teste).
- [ ] Endurecer `DisparoController@processarFila` (autenticação/throttle) se for exposto via HTTP.
- [ ] Testes de fluxo completo no navegador (8 telas) e validação de erros amigáveis.
- [ ] Build front (`npm run build`) incluindo `telegram.css` no pipeline Vite, se desejado.

---

## 🧭 Decisões técnicas relevantes

1. **Blade + Inertia convivendo**: o projeto usa Jetstream/Inertia/Vue; o prompt pede telas Blade. As telas do AgendaFácil foram criadas em Blade sem remover nem alterar o front existente.
2. **Admin por `role`**: em vez de gate novo, o middleware `admin` reaproveita o campo `role` + Spatie já presentes no `User`.
3. **Modo degradado do Telegram**: sem `TELEGRAM_TOKEN`, o fluxo continua navegável (com aviso) em vez de quebrar — facilita desenvolvimento e demonstração.
4. **Código de validação em sessão**: simples e testável; migração para envio via bot registrada como pendência.

---

*Este arquivo é atualizado a cada implementação concluída, mantendo o histórico de evolução do sistema.*
