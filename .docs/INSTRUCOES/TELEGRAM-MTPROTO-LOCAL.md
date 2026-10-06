# Teste local: grupos de contas Telegram via MTProto

Esta integração autentica a conta pessoal do visitante com Teleproto e lista grupos básicos e supergrupos dos diálogos dessa conta. O bot da AgendaFácil continua sendo o responsável por enviar mensagens.

## Configuração local

Adicione ao `.env` da raiz os valores obtidos em `my.telegram.org`; não os coloque no código, no frontend ou em logs:

```env
TELEGRAM_API_ID=
TELEGRAM_API_HASH=
TELEGRAM_MTPROTO_URL=http://127.0.0.1:3037
TELEGRAM_MTPROTO_SERVICE_TOKEN=
```

Gere um valor aleatório exclusivo para `TELEGRAM_MTPROTO_SERVICE_TOKEN` e configure o mesmo valor no Laravel e no processo Node. Exemplo de geração pelo PHP:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Não copie credenciais reais para este documento. O `.env` já deve permanecer ignorado pelo Git.

## Iniciar o fluxo

Use dois terminais na raiz do projeto.

Terminal 1, Laravel:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, serviço MTProto:

```powershell
Set-Location .\services\telegram-mtproto
npm start
```

O serviço escuta somente `127.0.0.1:3037`; não exponha essa porta pelo Cloudflare Tunnel. O Laravel chama o endpoint local autenticado por bearer token. Para usar o Telegram Login Widget em desenvolvimento, mantenha o domínio público configurado em `APP_URL` e liberado no @BotFather; o túnel público continua apontando apenas para o Laravel.

## Fluxo de teste

1. Abra `/acesso`, identifique um cadastro visitante e faça a confirmação inicial do Telegram pelo Login Widget.
2. Leia e aceite o consentimento MTProto.
3. Informe o telefone da própria conta em formato internacional (`+55...`).
4. Digite o código entregue pelo Telegram. Se solicitado, informe a senha 2FA.
5. O sistema confirma novamente que o Telegram ID MTProto é igual ao ID validado pelo widget, lista os grupos/supergrupos e volta ao fluxo.

Para testar a tela isoladamente, abra `/telegram/teste-grupos`. Ela usa o mesmo Login Widget, consentimento, autenticação e listagem.

## Dados e limites

- A autorização MTProto fica somente na memória do serviço, por até 10 minutos durante a tentativa. Depois de listar os diálogos, a sessão é encerrada; nenhuma `StringSession`, senha 2FA ou código é persistido.
- São armazenados no banco apenas nome, `chat_id`, tipo e vínculo do grupo ao visitante. Somente `group` e `supergroup` são incluídos; canais e conversas privadas ficam de fora.
- O serviço local em memória não é adequado para deploy serverless multi-instância como a configuração Vercel atual. Produção exigirá um serviço persistente e uma estratégia protegida para as sessões MTProto.
- Login por cliente de terceiros pode acionar proteções do Telegram. Use apenas contas cujos titulares deram consentimento e pare se o Telegram indicar restrições.
- A autorização da conta pessoal apenas permite listar os grupos; não autoriza o bot da AgendaFácil a enviar mensagens. O bot precisa estar nos grupos e ter permissão de envio.

## Verificações automatizadas

Na pasta `services/telegram-mtproto`:

```powershell
npm test
```

Os testes usam uma implementação simulada do cliente; eles não solicitam telefone nem enviam códigos reais ao Telegram.
