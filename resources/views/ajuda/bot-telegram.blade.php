@extends('layouts.telegram')
@section('titulo', 'Como configurar o bot do Telegram')

@section('conteudo')
<div class="text-center mt-3">
    <span class="tg-logo telegram-gradient">🤖</span>
    <h1 class="h4 mt-2 mb-1">Como configurar o bot do Telegram</h1>
    <p class="text-muted">Siga o passo a passo para criar o bot, pegar o token, criar os grupos e vincular tudo ao AgendaFácil.</p>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">1️⃣ Criar o bot com o @BotFather</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>No Telegram, abra a conversa com <strong>@BotFather</strong> (confira o selo de verificado).</li>
            <li>Envie o comando <code>/newbot</code>.</li>
            <li>Escolha um <strong>nome</strong> de exibição (ex: <code>AgendaFácil</code>).</li>
            <li>Escolha um <strong>username</strong> terminado em <code>bot</code> (ex: <code>AgendaFacil26_bot</code>).</li>
            <li>O BotFather responde com o <strong>token</strong> (formato <code>123456789:ABCdef...</code>). <strong>Copie e guarde</strong> — ele é a senha do bot.</li>
        </ol>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">2️⃣ Anotar o username do bot</div>
    <div class="card-body p-4">
        <p class="mb-0">Anote o username <strong>com o @</strong> (ex: <code>@AgendaFacil26_bot</code>). No cadastro do sistema, digite <strong>sem o @</strong>. Para confirmar, abra <code>t.me/SEU_USERNAME</code> no navegador — deve abrir a conversa com o bot.</p>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">3️⃣ Cadastrar o bot no AgendaFácil</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>Acesse a área <strong>Admin → Bots Telegram</strong>.</li>
            <li>Clique em <strong>novo bot</strong> e preencha nome, username (sem @) e token.</li>
            <li>Defina-o como <strong>padrão</strong> (é ele que fará os envios).</li>
            <li>Use o botão <strong>testar</strong> para validar a conexão na hora.</li>
        </ol>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">4️⃣ Criar os grupos no Telegram</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>No Telegram, toque em <strong>nova conversa → Novo grupo</strong>.</li>
            <li>Adicione pelo menos 1 pessoa e dê um nome claro (ex: <code>SENHA NORMAL P1 E ALA</code>).</li>
            <li>Repita para cada grupo de senha (um por ala/unidade, se precisar).</li>
        </ol>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">5️⃣ Adicionar o bot aos grupos</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>Abra o grupo → toque no <strong>nome do grupo</strong> → <strong>Adicionar membros</strong>.</li>
            <li>Busque pelo <strong>@username do bot</strong> e adicione.</li>
            <li>Opcional, mas recomendado: promova o bot a <strong>administrador</strong> (evita restrições de envio).</li>
            <li><strong>Envie uma mensagem</strong> no grupo (ex: <code>Olá</code>) — o sistema só enxerga grupos com atividade recente.</li>
        </ol>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">6️⃣ Vincular os grupos ao AgendaFácil</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>Acesse <strong>Admin → Grupos</strong>.</li>
            <li>Use <strong>listar do Telegram</strong> para importar automaticamente (só aparecem grupos onde o bot está e houve mensagem recente).</li>
            <li>Se preferir manual: cadastre com nome + <code>chat_id</code>. Para descobrir o <code>chat_id</code>, adicione o <strong>@getmyid_bot</strong> ao grupo ou chame no navegador:<br><code>https://api.telegram.org/botSEU_TOKEN/getUpdates</code></li>
        </ol>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-header-telegram">7️⃣ Testar o envio</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li>Acesse <strong>Admin → Teste Telegram</strong>.</li>
            <li>Escolha o grupo, escreva uma mensagem de teste e envie.</li>
            <li>Confira se a mensagem chegou no grupo. Se não chegar, revise os passos 5 e 6.</li>
        </ol>
    </div>
</div>

<div class="card card-telegram mt-3">
    <div class="card-body p-4">
        <p class="fw-bold mb-1">🔒 Segurança do token</p>
        <p class="mb-0">O token é a senha do bot: <strong>nunca</strong> poste no grupo nem compartilhe. Se vazar, fale com o @BotFather (<code>/revoke</code>), gere um novo e atualize no Admin → Bots Telegram.</p>
    </div>
</div>

<div class="text-center mt-3 d-flex gap-2 justify-content-center">
    <a href="{{ route('admin.telegram-bots') }}" class="btn-telegram" style="width:auto">Ir para Bots Telegram</a>
    <a href="{{ route('agendafacil.acesso') }}" class="btn btn-link">Voltar ao acesso</a>
</div>
@endsection
