# Relatório: Fluxo de Acesso e Grupos do Telegram

## Sequência implementada

1. **Acesso (`/acesso`)**: o visitante informa os quatro últimos dígitos do telefone cadastrado. O sistema identifica o usuário e inicia a sessão temporária do fluxo.
2. **Código (`/codigo`)**: o visitante confirma sua identidade pelo Telegram Login Widget. Com MTProto configurado, o fluxo segue para consentimento explícito e autenticação da conta pessoal por telefone, código e senha 2FA opcional.
3. **Confirmar dados (`/confirmar-dados`)**: após validação do Telegram, são exibidos os dados do agendamento e os grupos ativos pertencentes ao usuário. A pessoa confirma mensagem e horário.
4. **Grupos do Telegram (`/grupos/selecionar`)**: lista os grupos ativos vinculados ao usuário autenticado no fluxo para selecionar o destino final do disparo.
5. **Concluído (`/concluido`)**: confirma o destino escolhido e encerra o fluxo.

A tela de resumo (`/confirmar-dados/resumo`) é uma confirmação intermediária da etapa 3. Ao confirmar o agendamento, o fluxo segue para `/grupos/selecionar`, não volta para `/codigo`.

## Autenticação Telegram

O callback `GET /telegram/confirmar-callback`, tratado por `TelegramValidacaoController@confirmar`, valida o retorno oficial do Login Widget.

- A sessão identifica o visitante por `agendafacil.visitante_id` e expira após 2 horas (`VALIDADE_SEGUNDOS = 7200`). Sessões expiradas são removidas e redirecionadas para `/acesso`.
- O payload exige `id` e `auth_date` numéricos, data de autenticação com no máximo 24 horas (`AUTH_DATE_MAXIMO = 86400`) e hash válido.
- O hash é calculado com os parâmetros ordenados, chaveados por linha, usando HMAC-SHA256 e a chave derivada do token do bot padrão, com fallback para `services.telegram.token`.
- A identidade retornada precisa corresponder ao `telegram_id` já cadastrado; se ainda não houver ID, é conferido o username cadastrado e o ID retornado é vinculado ao usuário.
- No sucesso, a sessão recebe `agendafacil.codigo_ok`, `agendafacil.telegram_ok` e `agendafacil.acesso_em`; o callback redireciona para `agendafacil.confirmar-dados`.
- Somente a flag `telegram_ok`, definida pelo callback do widget, libera as etapas de confirmação, seleção de grupos e conclusão. O endpoint legado de código numérico não autentica a conta Telegram.

A autenticação do Login Widget comprova a identidade do visitante. Com configuração MTProto, o titular autoriza separadamente a leitura dos diálogos da conta. A identidade MTProto precisa corresponder ao mesmo `telegram_id` confirmado pelo widget. A autorização pessoal nunca envia mensagens; o envio continua sendo feito pelo bot configurado para o `destino_id` persistido no agendamento.

## Listagem e seleção de grupos

Tanto `/confirmar-dados` quanto `/grupos/selecionar` consultam `GrupoTelegram` filtrando por `usuario_id` igual ao usuário identificado na sessão, além de `ativo = true`. Os resultados são ordenados por `ordem` e depois por `nome`; grupos inativos e grupos de outras contas não são exibidos.

### Descoberta via MTProto

Quando `TELEGRAM_API_ID`, `TELEGRAM_API_HASH` e `TELEGRAM_MTPROTO_SERVICE_TOKEN` estão configurados, o fluxo usa o microserviço Teleproto local para consultar os diálogos da conta autorizada. Só grupos básicos e supergrupos são incluídos; canais de transmissão, chats privados e entidades proibidas são excluídos. Os resultados são sincronizados ao usuário, e grupos antigos que não aparecem mais ficam inativos sem serem apagados.

O cliente e o desafio de autenticação ficam somente em memória por até 10 minutos. Após obter os diálogos, o cliente executa logout; não persiste `StringSession`, código nem senha 2FA. A tela `/telegram/teste-grupos` utiliza o mesmo fluxo. Instruções para configurar o `.env` e iniciar Laravel + Node estão em [TELEGRAM-MTPROTO-LOCAL.md](INSTRUCOES/TELEGRAM-MTPROTO-LOCAL.md).

Se MTProto não estiver configurado, permanece disponível a descoberta anterior via Bot API. Essa alternativa só verifica chats conhecidos pelo bot e depende das permissões do bot; não lista grupos desconhecidos da conta pessoal.

### Causa histórica da lista vazia

O cadastro administrativo anterior gravava `usuario_id` com o ID do administrador que adicionava/importava o grupo. Como a autenticação Telegram identifica um visitante, esse filtro não encontrava os registros. O painel `Admin > Grupos` agora exige a escolha da conta visitante ao cadastrar, importar ou editar um grupo, e exibe a conta vinculada. Grupos já existentes podem ser corrigidos pelo botão **Editar**.

Após o Login Widget validar a conta, `TelegramBotService::listarGruposDoUsuario()` reúne chats descobertos pelo bot via `getUpdates` e grupos já cadastrados. Consulta `getChatMember` em paralelo, em lotes de até dez, e mantém chats em que o ID Telegram está como criador, administrador, membro ou membro restrito. Grupos encontrados são associados automaticamente ao visitante, preservando a configuração existente quando há um registro prévio.

O painel `Admin > Grupos` continua permitindo associar manualmente chats conhecidos pelo bot e configurar regras de envio. Isso não é necessário para a descoberta MTProto, mas permite corrigir dados de agendamento e manter compatibilidade com o fallback Bot API.

Se `/grupos/selecionar` não encontrar grupos ativos, retorna à confirmação dos dados com aviso para verificar os grupos cadastrados. A seleção final também valida no servidor que o grupo está ativo e pertence ao visitante. Um ID de outra conta resulta em 404.

Ao confirmar, o destino selecionado é gravado na sessão e, quando existe agendamento pendente, atualiza também `grupo_telegram_id`, `destino_id` e `destino_nome` no registro. O job `EnviarSenhaTelegram` envia para o `destino_id` do agendamento; assim, o grupo confirmado é o alvo real do disparo, não apenas um valor temporário da sessão.

## Testes executados

Foram executados `php artisan test`: **52 testes passaram, nenhum falhou**. O microserviço `npm test` passou **3 testes**; `php artisan view:cache` e `php artisan route:list --path=telegram` também concluíram.

Os cenários cobertos incluem:

- Acesso válido encaminha para `/codigo`.
- Um novo acesso remove as flags Telegram e os dados de agendamento da sessão anterior.
- Login Telegram válido encaminha para `/confirmar-dados`; payload adulterado e conta não vinculada são rejeitados.
- Grupos ativos do próprio usuário aparecem em ordem; grupos inativos e de outros usuários são excluídos.
- Tentativa de confirmar grupo de outra conta é rejeitada.
- Sessão de grupos expirada após duas horas retorna para `/acesso`.
- Confirmação final sincroniza o grupo selecionado com o destino do agendamento.
- Painel vincula grupos manuais e importados ao visitante selecionado.
- Painel lista as contas e oferece o atalho para a página de diagnóstico.
- Página Blade de diagnóstico autentica pelo widget e lista somente os grupos daquela conta.
- Login associa automaticamente um grupo conhecido quando `getChatMember` confirma a participação.
- Login MTProto com consentimento lista/sincroniza grupos e supergrupos; 2FA e incompatibilidade de identidade têm cobertura.

## Arquivos envolvidos

- `routes/web.php`
- `app/Http/Controllers/AcessoController.php`
- `app/Http/Controllers/TelegramValidacaoController.php`
- `app/Services/TelegramMtprotoService.php`
- `services/telegram-mtproto/`
- `.docs/INSTRUCOES/TELEGRAM-MTPROTO-LOCAL.md`
- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/GrupoController.php`
- `resources/views/agendafacil/acesso.blade.php`
- `resources/views/agendafacil/codigo.blade.php`
- `resources/views/agendafacil/confirmar-dados.blade.php`
- `resources/views/agendafacil/confirmar-resumo.blade.php`
- `resources/views/agendafacil/selecionar-grupo.blade.php`
- `resources/views/agendafacil/teste-grupos-telegram.blade.php`
- `resources/views/admin/grupos.blade.php`
- `tests/Feature/AgendaFacilFlowTest.php`
- `tests/Feature/TelegramValidacaoTest.php`
