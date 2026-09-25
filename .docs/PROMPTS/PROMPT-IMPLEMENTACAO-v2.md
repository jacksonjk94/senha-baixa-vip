# PROMPT DE IMPLEMENTAÇÃO — SenhaBaixa

**Versão:** 2.0 Atualizada
**Data:** 24/09/2026 · Curitiba/PR
**Projeto:** SenhaBaixa — Sistema de Agendamento e Disparo Automático via Telegram
**Framework:** Laravel (pré-configurado)

---

## 🎯 OBJETIVO

Implementar o sistema **SenhaBaixa** no projeto Laravel existente, conforme fluxo e regras definidas pela cliente: o usuário acessa via link, agenda, e o robô envia automaticamente no grupo do Telegram no horário programado.

> **Diretriz principal confirmada pela cliente:** O visitante **NÃO precisa** ter conta no Telegram. Apenas o robô/sistema tem conta e faz os envios.

> **Instrução inicial para o agente (OpenCode):** Antes de qualquer implementação, leia TODO o projeto existente (rotas, controllers, models, migrations, views, configurações) para se contextualizar das implementações já realizadas e manter os padrões adotados. Não sobrescreva funcionalidades já existentes; integre-se a elas.

---

## 📋 ENTENDIMENTO OFICIAL DO FLUXO

### ✅ Diretrizes confirmadas pela cliente

- O sistema é acessado **por link** → a pessoa abre no navegador, sem instalar nada
- **NÃO precisa ter conta no Telegram** para agendar
- O que é enviado = **texto livre** (nome da pessoa + observação, ex: `Roberta lallison x8`)
- A **ordem de envio no grupo = posição da senha** (quem aparece primeiro é atendido primeiro)
- O robô envia **exatamente no horário programado** — mensagens enviadas antes do horário "queimam" e não valem
- A equipe do presídio organiza o caderno pela ordem que as mensagens aparecem no grupo
- A pessoa **escolhe o grupo de destino** a cada agendamento (vários grupos disponíveis)
- Não é necessário apresentar nada na porta — a senha já aparece no grupo

### ❌ O que NÃO é necessário (remover do escopo anterior)

- ❌ Visitante ter conta no Telegram
- ❌ Gerar código/senha automática → o texto digitado é a "senha"
- ❌ Login do visitante no Telegram / código de verificação para o visitante
- ❌ Campo "número da senha" separado → a posição é definida pela ordem de envio

### 🔁 Fluxo Completo

```
1️⃣ Pessoa recebe link → abre no navegador (sem login prévio)
   ↓
2️⃣ Preenche:
    • Grupo de destino (ex: SENHA NORMAL P1 E ALA)
    • Data e Horário do disparo
    • Texto/Nome que vai aparecer no grupo (ex: "Roberta lallison x8")
   ↓
3️⃣ Confirma → sistema salva como Agendamento (status: pendente)
   ↓
4️⃣ No horário exato → Robô (conta do sistema) envia a mensagem no grupo do Telegram
   ↓
5️⃣ Aparece no grupo: "Roberta lallison x8" → ordem de chegada = posição
   ↓
6️⃣ Tela confirma: Enviado ✅ | Posição: X de Y
```

---

## 🧩 ESTRUTURA TÉCNICA

### 1 — Autenticação do Sistema (Apenas o Robô)

- Apenas **a conta do robô/sistema** conecta ao Telegram via Socialite
- Usuário visitante **não faz login** — acessa pelo link direto (rota pública)
- Socialite Telegram configurado apenas para a conta administrativa/robô
- Área administrativa protegida por senha/middleware `admin`

### 2 — Modelos e Migrations

#### Tabela `grupos`

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | bigint | PK |
| `nome` | string | Ex: SENHA NORMAL P1 E ALA |
| `chat_id` | string | ID do grupo no Telegram |
| `ativo` | boolean | Se aparece na lista de agendamento |
| `ordem` | integer | Ordem de exibição |
| `created_at` / `updated_at` | timestamps | |

#### Tabela `agendamentos`

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | bigint | PK |
| `grupo_id` | foreign | Grupo de destino |
| `texto` | string | Conteúdo que aparece no grupo: "Roberta lallison x8" |
| `data_hora_envio` | datetime | Horário programado do disparo |
| `status` | enum | pendente \| enviado \| erro \| cancelado |
| `posicao` | integer | Ordem de envio: "8 de 85" |
| `total_grupo_horario` | integer | Total agendado para mesmo grupo+horário |
| `mensagem_erro` | text | Em caso de falha no envio |
| `enviado_em` | datetime | Quando foi disparado |
| `ip_origem` | string | Controle de acesso |
| `created_at` / `updated_at` | timestamps | |

### 3 — Rotas — `routes/web.php`

```php
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\TelegramController;

// === PÚBLICAS (visitante, sem login) ===
Route::get('/', [AgendamentoController::class, 'inicio'])->name('inicio');
Route::get('/agendar', [AgendamentoController::class, 'create'])->name('agendar');
Route::post('/agendar', [AgendamentoController::class, 'store'])->name('agendar.salvar');
Route::get('/agendamento/{id}/confirmacao', [AgendamentoController::class, 'confirmacao'])->name('agendamento.confirmacao');
Route::get('/meus-agendamentos', [AgendamentoController::class, 'meus'])->name('agendamento.meus');

// === CONEXÃO DO ROBÔ (admin) ===
Route::get('/telegram/connect', [TelegramController::class, 'connect'])->name('telegram.connect');
Route::get('/telegram/callback', [TelegramController::class, 'callback'])->name('telegram.callback');

// === ÁREA ADMINISTRATIVA (protegida) ===
Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.inicio');
    Route::resource('grupos', GrupoController::class);
    Route::get('/agendamentos', [AdminController::class, 'agendamentos'])->name('admin.agendamentos');
    Route::patch('/agendamento/{id}/reenviar', [AdminController::class, 'reenviar'])->name('admin.reenviar');
    Route::patch('/agendamento/{id}/cancelar', [AdminController::class, 'cancelar'])->name('admin.cancelar');
    Route::get('/configuracoes', [AdminController::class, 'configuracoes'])->name('admin.configuracoes');
});
```

### 4 — Controladores

- **AgendamentoController** → formulário público, salvar agendamento, tela de confirmação, cálculo de posição
- **GrupoController** → CRUD de grupos (admin)
- **TelegramController** → `connect()` e `callback()` — apenas para o robô/admin
- **AdminController** → dashboard, listagem de agendamentos, reenviar/cancelar, configurações
- **DisparoJob** (Job/Fila) → executar o envio via Telegram Bot API

### 5 — Lógica de Negócio

#### Acesso e Agendamento (público)

- Rota pública `/agendar` → sem middleware de auth
- Formulário com:
  - **Select:** Grupo de destino (lista de grupos ativos, ordenada)
  - **Data e Horário** do envio (datetime picker)
  - **Campo Texto:** Nome/observação que aparece no grupo (ex: `Roberta lallison x8`)
  - **Botão:** Agendar
- Validação: texto obrigatório, grupo válido e ativo, data/hora futura

#### Cálculo da Posição

- Ao salvar agendamento: contar quantos estão agendados para o **mesmo grupo e mesmo horário** (minuto a minuto) → `posicao = contagem + 1`
- Salvar também `total_grupo_horario`
- Exibir na confirmação: `Posição: 3 de 12`

#### Disparo Automático (Scheduler + Fila)

- Em `app/Console/Kernel.php` (ou `routes/console.php` no Laravel 11):
  ```php
  Schedule::call(function () {
      Agendamento::where('status', 'pendente')
          ->where('data_hora_envio', '<=', now())
          ->each(fn ($a) => DisparoJob::dispatch($a));
  })->everyMinute();
  ```
- **Regra crítica:** NÃO enviar antes do horário programado (`data_hora_envio <= agora`)
- Enviar via Telegram Bot API: `sendMessage(chat_id, texto)`
- Após envio: marcar `status = enviado`, preencher `enviado_em`
- Em caso de falha: marcar `status = erro`, salvar `mensagem_erro`

#### Área Administrativa (protegida)

- **Dashboard** → total agendamentos / enviados hoje / pendentes / com erro
- **Grupos** → cadastrar, editar, ativar/desativar, ordenar (preencher `chat_id`)
- **Agendamentos** → listar todos, filtrar por grupo/status/data, reenviar com erro, cancelar
- **Configurações** → credenciais do bot Telegram (token), teste de envio

---

## 🎨 IDENTIDADE VISUAL

### Nome e Marca
- **Nome do sistema:** SenhaBaixa
- **Domínio:** app.senhabaixa.com (referência nas telas)

### Paleta de Cores (estilo Telegram)

| Elemento | Cor | Uso |
|---|---|---|
| Principal | `#2AABEE` | Cabeçalhos, botões primários, links |
| Escuro | `#229ED8` | Hover, gradiente escuro |
| Claro | `#7DD3F8` | Gradiente claro, destaques |
| Fundo | `#F4F9FC` | Páginas |
| Cartão | `#FFFFFF` | Blocos de conteúdo |
| Texto | `#333333` | Padrão |
| Sucesso | `#28C76F` | Enviado / confirmado |
| Atenção | `#FF9F43` | Pendente |
| Erro | `#EA5455` | Falhas |

### Estilos e Gradientes

```css
.telegram-gradient {
    background: linear-gradient(135deg, #2AABEE 0%, #229ED8 100%);
}

.btn-telegram {
    background: linear-gradient(90deg, #2AABEE, #229ED8);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 12px 28px;
    font-weight: 600;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-telegram:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(42, 171, 238, 0.35);
    color: white;
}

.card-telegram {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(42, 171, 238, 0.08);
    overflow: hidden;
}

.card-header-telegram {
    background: linear-gradient(90deg, #2AABEE, #229ED8);
    color: white;
    padding: 14px 20px;
}
```

### Telas a Criar

1. **Início** → apresentação simples, botão "Agendar agora"
2. **Agendar** → formulário limpo: grupo + data/hora + texto
3. **Confirmação** → "Agendado ✅ — Envia dia XX às HHh — Posição: X de Y"
4. **Meus Agendamentos** → histórico com status (via cookie/IP)
5. **Admin** → dashboard + grupos + agendamentos + configurações

---

## ⚙️ CONFIGURAÇÕES

### `.env` e `.env.example`

```env
TELEGRAM_BOT_NAME=SenhaBaixa_bot
TELEGRAM_TOKEN=123456789:ABCdefGhIjKlMnOpQrStUvWxYz123456
TELEGRAM_REDIRECT_URI=https://app.senhabaixa.com/telegram/callback
```

### `config/services.php`

```php
'telegram' => [
    'bot'           => env('TELEGRAM_BOT_NAME'),
    'client_secret' => env('TELEGRAM_TOKEN'),
    'redirect'      => env('TELEGRAM_REDIRECT_URI'),
],
```

---

## ✅ CRITÉRIOS DE CONCLUSÃO

- [ ] Acesso pelo link funciona sem login do visitante
- [ ] Formulário de agendamento: grupo + data/hora + texto (texto livre)
- [ ] Posição calculada corretamente por grupo+horário e exibida na confirmação
- [ ] Disparo automático no horário exato via Scheduler (nunca antes)
- [ ] Mensagem aparece no grupo do Telegram com o texto digitado
- [ ] Status atualizado: pendente → enviado / erro
- [ ] Área administrativa protegida: grupos (CRUD) + agendamentos (filtros, reenviar, cancelar) + configurações
- [ ] Layout com identidade visual Telegram aplicada, responsivo (celular e computador)
- [ ] `LOG-ALTERACOES.md` atualizado com versão 2.0
- [ ] Nenhuma exigência de conta Telegram para o visitante

---

*Fim do Prompt — Versão 2.0 Atualizada*
