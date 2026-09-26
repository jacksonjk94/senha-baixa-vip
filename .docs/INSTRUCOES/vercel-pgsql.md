# Deploy na Vercel com PostgreSQL — Instruções

## O que quebrou e por quê

O erro `Database file at path [/tmp/database.sqlite] does not exist` acontecia em **todas** as rotas (inclusive `/admin`) porque:

1. O `vercel.json` fixava `DB_CONNECTION=sqlite` + `DB_DATABASE=/tmp/database.sqlite`. As variáveis do dashboard **só valem para as chaves que você define lá** — como `DB_CONNECTION` não estava entre elas, o ativo continuava `sqlite`.
2. No serverless o `/tmp` é efêmero: o arquivo nunca existe → a primeira query do request (checagem de maintenance mode via cache `database`) já estourava 500.
3. Pelo mesmo motivo, `CACHE_STORE=array` do `vercel.json` também não estava valendo (valia o default `database`).

## Correção aplicada no código

- `vercel.json`: removidos `DB_CONNECTION`, `DB_DATABASE` e `CACHE_STORE` fixos — agora **o dashboard governa** banco e cache. `APP_DEBUG` foi para `false` (estava `true` em produção, expondo stack traces como o colado no chamado).
- `.env.vercel`: virou template pgsql (referência do que replicar no dashboard).

## Variáveis obrigatórias no dashboard da Vercel

Vercel → Projeto → Settings → Environment Variables (marcar **Production**):

| Chave | Valor |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | host do Postgres |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | nome do banco |
| `DB_USERNAME` | usuário |
| `DB_PASSWORD` | senha (secret, sem aspas) |
| `DB_SSLMODE` | `require` (se o provedor exigir SSL — Supabase/Neon/Render exigem) |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` (sessões em cookie são instáveis no serverless e causavam 419 no login — a tabela `sessions` já vem nas migrations) |
| `SESSION_LIFETIME` | `120` — **obrigatório e nunca vazio**: valor vazio/0 faz os cookies saírem com `Max-Age=0` (descartados na hora), cada request cria sessão nova e **todo POST dá 419** |
| `APP_KEY` | `base64:...` válido (gerar com `php artisan key:generate --show`) |
| `N8N_WORKER_SECRET` | mesma chave do worker de disparos |
| `TELEGRAM_TOKEN` | token do BotFather |
| `TELEGRAM_BOT_NAME` | username do bot (sem @) |

> Não precisa definir `DB_DATABASE=/tmp/...` nem nada de sqlite — qualquer resto de `DB_*` sqlite no dashboard deve ser **removido**.

## Banco: criar as tabelas no Postgres

O banco pgsql está vazio — rode as migrations **apontando para ele** (de qualquer máquina com acesso):

```bash
DB_CONNECTION=pgsql DB_HOST=... DB_PORT=5432 DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... \
php artisan migrate --force
```

E os seeds iniciais (admin, permissões):

```bash
# mesmas vars de cima...
php artisan db:seed --force
```

Confira: tabela `migrations` deve listar até `0001_01_01_000015_grupo_modo_horarios`.

## Redeploy e verificação

1. Salve as vars e faça **Redeploy** (aba Deployments → ⋯ → Redeploy) — var de ambiente nova/reeditada só vale após redeploy.
2. Abra `https://agendafacil-teste.vercel.app/acesso` — deve abrir a etapa 1 (sem 500).
3. Com `APP_DEBUG=false`, erros viram página genérica; acompanhe pelo log (`LOG_CHANNEL=stderr` → Runtime Logs na Vercel).

## Checklist se ainda der 500

- [ ] `DB_CONNECTION=pgsql` existe mesmo no dashboard (a causa deste chamado)
- [ ] Redeploy feito após salvar as vars
- [ ] Migrations rodadas no pgsql (erro passa a ser "relation ... does not exist" se faltar)
- [ ] `DB_SSLMODE=require` se o erro for de SSL/conexão recusada
- [ ] `APP_KEY` definido (sessões/cookies quebram sem ele)
