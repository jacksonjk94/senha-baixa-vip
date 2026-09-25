# Cloudflare Tunnel — Passo a Passo (Windows)

> Objetivo: expor o Laravel local (`http://localhost:8000`) com HTTPS pública,
> ex.: para testar o callback do Telegram (`/telegram/callback`), que exige URL pública.
>
> Pré-requisito: este projeto já confia em proxy (`trustProxies(at: '*')` em
> `bootstrap/app.php`), então o Laravel detecta HTTPS atrás do túnel sem ajuste extra.

---

## 1. Criar conta na Cloudflare (só p/ túnel nomeado)

1. Acesse https://dash.cloudflare.com/sign-up e crie a conta (plano Free basta).
2. Se quiser **domínio próprio** (ex.: `dev.seudominio.com`): adicione o domínio
   em **Websites → Add domain** e troque os nameservers no registrador, aguardando
   ativação. Para teste rápido, **pule** — o *Quick Tunnel* gera URL pública sozinho.

## 2. Baixar e instalar o `cloudflared`

Opção A — winget (recomendado):

```powershell
winget install --id Cloudflare.cloudflared
```

Opção B — instalador: baixe em
https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/
e instale. Confirme:

```powershell
cloudflared --version
```

## 3-A. Teste rápido — Quick Tunnel (2 minutos, sem conta/domínio)

```powershell
# Terminal 1: suba o Laravel
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2: abra o túnel
cloudflared tunnel --url http://localhost:8000
```

Copie a URL `https://xxxx.trycloudflare.com` exibida. Limitações: URL muda a cada
execução e cai ao fechar o terminal — ideal só para testes pontuais.

## 3-B. Túnel nomeado + fixo (recomendado)

```powershell
# 1. Login (abre o navegador para autorizar)
cloudflared tunnel login

# 2. Criar o túnel (guarde o ID exibido)
cloudflared tunnel create agendafacil-dev

# 3. Apontar um DNS para o túnel (subdomínio de um domínio já ativo na Cloudflare)
cloudflared tunnel route dns agendafacil-dev dev.seudominio.com
```

Crie o arquivo `%USERPROFILE%\.cloudflared\config.yml`:

```yaml
tunnel: agendafacil-dev
credentials-file: C:\Users\SEU_USUARIO\.cloudflared\<ID-DO-TUNEL>.json

ingress:
  - hostname: dev.seudominio.com
    service: http://localhost:8000
  - service: http_status:404
```

Rode:

```powershell
cloudflared tunnel run agendafacil-dev
```

Para deixar como serviço do Windows (sobe junto com o PC):

```powershell
cloudflared service install
```

Gerenciar em `services.msc` (serviço `Cloudflared`).

## 4. Ajustar o Laravel para a URL pública

No `.env` **local de teste**:

```env
APP_URL="https://dev.seudominio.com"
TELEGRAM_REDIRECT_URI="${APP_URL}/telegram/callback"
SESSION_SECURE_COOKIE=true
```

> `SESSION_DOMAIN` deve continuar vazio/`null`. Com `trustProxies` já ativo,
> `$request->secure()` e as URLs geradas saem em HTTPS automaticamente.

Limpe caches após trocar o `.env`:

```powershell
php artisan optimize:clear
```

## 5. Apontar o Telegram para o túnel

- **Login Widget**: no BotFather, `/setdomain` → envie só o host
  (`dev.seudominio.com`, sem `https://`).
- **Webhook** (se usar): registre com o token do bot padrão:

```powershell
curl "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://dev.seudominio.com/telegram/webhook"
```

Confira com `getWebhookInfo`:

```powershell
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

## 6. Comandos úteis

```powershell
cloudflared tunnel list          # lista túneis
cloudflared tunnel info agendafacil-dev
cloudflared tunnel delete -f agendafacil-dev   # remove o túnel
cloudflared tunnel run --url http://localhost:8000  # quick tunnel (forma curta)
```

## 7. Problemas comuns

| Sintoma | Causa provável / solução |
|---|---|
| `ERR request failed` / 502 | Laravel parado — suba o `php artisan serve` na porta do `service:` |
| Assets/links em `http://` | `APP_URL` ainda com `http://localhost`; ajuste + `optimize:clear` |
| Login 419 pelo túnel | Cookie antigo: limpe os dados do site no navegador e teste em anônima |
| Telegram diz "Bot domain invalid" | Domínio do widget (`data-auth-url`) ≠ domínio vinculado no BotFather; alinhe `TELEGRAM_BOT_NAME`, token e `/setdomain` para o **mesmo** bot |
| Túnel cai ao fechar terminal | Normal no modo manual; instale como serviço (`cloudflared service install`) |
| `Permission denied` no `.json` | Rode o PowerShell como administrador na primeira configuração |

---

*Gerado para o projeto AgendaFácil/SenhaBaixa — túnel usado para testes locais com URL pública.*
