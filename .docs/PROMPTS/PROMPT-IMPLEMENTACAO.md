# PROMPT DE IMPLEMENTAÇÃO — AgendaFácil

**Projeto:** AgendaFácil · Sistema de Disparo de Senhas via Telegram
**Framework:** Laravel (pré-configurado)
**Versão:** 1.0
**Data:** 23/09/2026 · Curitiba/PR

---

## 🎯 OBJETIVO

Implementar o fluxo completo do **AgendaFácil** no projeto Laravel existente, integrando **Socialite para Telegram**, criando área administrativa, telas de agendamento, seleção de grupos e disparo automático. Aplicar identidade visual alinhada ao Telegram.

> **Instrução inicial para o agente (OpenCode):** Antes de qualquer implementação, leia TODO o projeto existente (rotas, controllers, models, migrations, views, configurações) para se contextualizar das implementações já realizadas e manter os padrões adotados. Não sobrescreva funcionalidades já existentes; integre-se a elas.

---

## 📋 CONTEXTO E FLUXO

### Fluxo do Usuário

```
1. Acesso → Últimos 4 dígitos do telefone
   ↓
2. Confirmação de Dados → Nome, grupo, horário, tipo de disparo
   ↓
3. Conexão Telegram → Socialite autoriza conta
   ↓
4. Validação por Código → Recebe e digita código de confirmação
   ↓
5. Seleção de Grupo → Lista grupos disponíveis → escolhe destino
   ↓
6. Confirmação do Grupo → Validação visual
   ↓
7. Agendamento → Define data/hora do disparo
   ↓
8. Execução Automática → Envia mensagem com senha
   ↓
9. Registro → Histórico: enviado / pendente / erro
```

### Destinos de Envio

- **Grupo do Telegram** → mensagem com dados da senha enviada no horário programado
- **Pessoa individual** → envio direto no chat do usuário

---

## 🧩 ETAPA 1 — INTEGRAÇÃO SOCIALITE TELEGRAM

### 1.1 Instalar Dependências

```bash
composer require laravel/socialite
composer require socialiteproviders/telegram
```

### 1.2 Configurar Serviços — `config/services.php`

```php
'telegram' => [
    'bot'           => env('TELEGRAM_BOT_NAME'),
    'client_secret' => env('TELEGRAM_TOKEN'),
    'redirect'      => env('TELEGRAM_REDIRECT_URI'),
],
```

### 1.3 Variáveis de Ambiente — `.env` (e `.env.example`)

```env
TELEGRAM_BOT_NAME=AgendaFacil_bot
TELEGRAM_TOKEN=123456789:ABCdefGhIjKlMnOpQrStUvWxYz123456
TELEGRAM_REDIRECT_URI=https://seusite.com/telegram/callback
```

### 1.4 Registrar Provider — `app/Providers/AppServiceProvider.php`

```php
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Telegram\TelegramExtendSocialite;

public function boot(): void
{
    Event::listen(function (SocialiteWasCalled $event) {
        $event->extendSocialite('telegram', TelegramExtendSocialite::class);
    });
}
```

### 1.5 Rotas — `routes/web.php`

```php
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\AdminController;

// Autenticação Telegram
Route::get('/telegram/connect', [TelegramController::class, 'connect'])->name('telegram.connect');
Route::get('/telegram/callback', [TelegramController::class, 'callback'])->name('telegram.callback');

// Fluxo Principal
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AgendamentoController::class, 'index'])->name('dashboard');
    Route::get('/agendamento/novo', [AgendamentoController::class, 'create'])->name('agendamento.novo');
    Route::post('/agendamento', [AgendamentoController::class, 'store'])->name('agendamento.salvar');
    Route::get('/grupos/selecionar', [GrupoController::class, 'selecionar'])->name('grupos.selecionar');
    Route::post('/grupos/confirmar', [GrupoController::class, 'confirmar'])->name('grupos.confirmar');
});

// Área Administrativa
Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.inicio');
    Route::get('/cadastros', [AdminController::class, 'cadastros'])->name('admin.cadastros');
    Route::get('/disparos', [AdminController::class, 'disparos'])->name('admin.disparos');
    Route::get('/grupos', [AdminController::class, 'grupos'])->name('admin.grupos');
    Route::patch('/agendamento/{id}/status', [AdminController::class, 'atualizarStatus'])->name('admin.status');
});
```

### 1.6 Controladores

- **TelegramController** → `connect()` e `callback()` — autenticação e armazenamento dos dados do usuário conectado
- **AgendamentoController** → CRUD de agendamentos, validação de dados
- **GrupoController** → listar grupos via API do Telegram, confirmação
- **DisparoController** → fila e execução do envio programado
- **AdminController** → painel com visão geral e gestão

### 1.7 Modelos e Migrations

- **User** → adicionar: `telegram_id`, `telegram_username`, `telegram_token`, `ultimos_digitos_telefone`
- **Agendamento** → `nome_visitante`, `dados_senha`, `destino_tipo` (grupo/pessoa), `destino_id`, `data_hora_envio`, `status` (pendente/enviado/erro), `mensagem_erro`
- **GrupoTelegram** → `nome`, `chat_id`, `usuario_id`

---

## 🎨 ETAPA 2 — IDENTIDADE VISUAL (Estilo Telegram)

### Paleta de Cores

| Elemento | Cor | Uso |
|---|---|---|
| Principal | `#2AABEE` | Cabeçalhos, botões primários, links |
| Escuro | `#229ED8` | Hover, gradiente escuro |
| Claro | `#7DD3F8` | Gradiente claro, destaques |
| Fundo | `#F4F9FC` | Páginas |
| Cartão | `#FFFFFF` | Blocos de conteúdo |
| Texto | `#333333` | Padrão |
| Sucesso | `#28C76F` | Confirmações, enviado |
| Atenção | `#FF9F43` | Pendente |
| Erro | `#EA5455` | Falhas |

### Estilos e Gradientes

```css
/* Gradiente principal Telegram */
.telegram-gradient {
    background: linear-gradient(135deg, #2AABEE 0%, #229ED8 100%);
}

.telegram-gradient-light {
    background: linear-gradient(135deg, #7DD3F8 0%, #2AABEE 100%);
}

/* Botão padrão */
.btn-telegram {
    background: linear-gradient(90deg, #2AABEE, #229ED8);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 10px 24px;
    font-weight: 600;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-telegram:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(42, 171, 238, 0.35);
    color: white;
}

/* Cartões */
.card-telegram {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(42, 171, 238, 0.08);
    overflow: hidden;
}

.card-header-telegram {
    background: linear-gradient(90deg, #2AABEE, #229ED8);
    color: white;
    padding: 12px 20px;
}
```

### Layout Responsivo

- Adaptar exatamente às telas do fluxo: Desbloquear → Confirmar Dados → Código → Selecionar Grupo → Confirmar → Concluído
- Versão desktop mantendo proporção e elegância
- Mobile-first, com experiência fluida no celular

---

## ⚙️ ETAPA 3 — LÓGICA DE NEGÓCIO

### 3.1 Acesso por Últimos 4 Dígitos

- Tela inicial solicita 4 dígitos do telefone
- Valida contra o cadastro do usuário → permite acesso ou rejeita
- Se não conectado ao Telegram → redireciona para autorização

### 3.2 Listagem de Grupos

- Após conexão, consultar `getUpdates` via Telegram Bot API
- Filtrar e listar apenas grupos/canais que o bot tem acesso
- Permitir busca/filtro na lista
- Ao selecionar → guardar `chat_id` e exibir nome para confirmação

### 3.3 Tipos de Disparo

- **Para Grupo** → mensagem enviada no grupo selecionado, formato:

```
✅ SENHA AUTORIZADA
Visitante: [Nome]
Ala/Unidade: [Grupo]
Horário: [Data/Hora]
Senha: [XXX]
```

- **Para Pessoa** → mensagem direta no chat do usuário, mesmos dados

### 3.4 Agendamento e Execução

- Usar **Laravel Scheduler** + filas (`queue:work`)
- Tabela de agendamentos com `data_hora_envio` e `status`
- Verificar a cada minuto registros com horário atingido e status = pendente → disparar → marcar como enviado
- Em caso de falha → marcar como erro + mensagem explicativa

### 3.5 Área Administrativa

- Dashboard → total de agendamentos / enviados / pendentes / com erro
- Cadastros → listagem de visitantes/dados
- Disparos → filtro por período, status, destino
- Grupos → gerenciamento dos destinos
- Ação manual → reenviar disparo que falhou

---

## 📝 ETAPA 4 — HISTÓRICO DE ALTERAÇÕES

Criar e manter atualizado o arquivo `LOG-ALTERACOES.md` na raiz do projeto, registrando cada implementação realizada (data, versão, área, descrição, responsável). Atualizar este log AO FINAL de cada etapa concluída.

---

## ✅ CRITÉRIOS DE CONCLUSÃO

- [ ] Socialite Telegram conecta e retorna dados do usuário
- [ ] Fluxo completo de 8 telas funcionando no navegador
- [ ] Listagem de grupos carregando da API do Telegram
- [ ] Disparo para grupo e para pessoa funcionando
- [ ] Agendamento automático via fila/scheduler
- [ ] Área administrativa protegida e navegável
- [ ] Layout com identidade visual Telegram aplicada
- [ ] `LOG-ALTERACOES.md` atualizado com todas as implementações
- [ ] Código seguindo padrões do Laravel existente

---

*Fim do Prompt*
