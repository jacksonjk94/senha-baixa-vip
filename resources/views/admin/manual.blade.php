@extends('layouts.admin')
@section('titulo', 'Manual do sistema')

@section('conteudo')
<h1 class="h4">📘 Manual do sistema</h1>
<p class="text-muted">Guia completo do AgendaFácil: fluxo do visitante, telas administrativas, papéis de acesso, grupos, disparos automáticos e solução de problemas.</p>

<div class="card stat-card mb-3"><div class="card-body">
    <h2 class="h6">Índice</h2>
    <ol class="mb-0">
        <li><a href="#m-visao">Visão geral</a></li>
        <li><a href="#m-fluxo">Fluxo do visitante (5 etapas)</a></li>
        <li><a href="#m-admin">Telas administrativas</a></li>
        <li><a href="#m-papeis">Papéis e permissões</a></li>
        <li><a href="#m-grupos">Grupos: aberto × fechado</a></li>
        <li><a href="#m-disparo">Disparo automático</a></li>
        <li><a href="#m-bot">Bot do Telegram</a></li>
        <li><a href="#m-acesso">Conta e acesso</a></li>
        <li><a href="#m-problemas">Solução de problemas</a></li>
    </ol>
</div></div>

<div class="card stat-card mb-3" id="m-visao"><div class="card-body">
    <h2 class="h6">1 · Visão geral</h2>
    <p class="mb-0">O AgendaFácil organiza <strong>senhas de atendimento</strong>: o visitante agenda pelo navegador (sem precisar de conta no Telegram), e no horário marcado o sistema envia a senha automaticamente no <strong>grupo do Telegram</strong> de destino. A ordem de envio segue a <strong>posição</strong> (quem agendou primeiro aparece primeiro). A página inicial (<code>/</code>) redireciona para o fluxo em <code>/acesso</code>. Todos os horários do sistema usam <strong>Brasília (America/Sao_Paulo)</strong>.</p>
</div></div>

<div class="card stat-card mb-3" id="m-fluxo"><div class="card-body">
    <h2 class="h6">2 · Fluxo do visitante (5 etapas)</h2>
    <ol class="mb-0">
        <li><strong>Acesso</strong> (<code>/acesso</code>): informa os <strong>4 últimos dígitos do telefone</strong> do agendamento. Sessão local válida por 2h; botão <strong>Sair</strong> no fim do fluxo.</li>
        <li><strong>Confirmar os dados</strong> (<code>/confirmar-dados</code>): mostra grupo, mensagem (padrão <code>Nome + xNÚMERO</code>, ex: <code>Maria x8</code>), horário, tipo e telefone. A senha é sugerida como a próxima da sequência do grupo. Inclui tela de <strong>resumo</strong> antes da confirmação final.</li>
        <li><strong>Código</strong> (<code>/codigo</code>): confirmação com <strong>login no próprio Telegram</strong> (conta vinculada ao cadastro). Alternativa: código de 6 dígitos enviado no grupo.</li>
        <li><strong>Grupos do Telegram</strong>: exibe somente o grupo do agendamento; o visitante confirma que é o correto.</li>
        <li><strong>Concluído</strong> (<code>/concluido</code>): exibe a posição (ex: <code>5ª de 12</code>). Depois disso, o envio é automático.</li>
    </ol>
</div></div>

<div class="card stat-card mb-3" id="m-admin"><div class="card-body">
    <h2 class="h6">3 · Telas administrativas</h2>
    <ul class="mb-0">
        <li><strong>Visão geral</strong> (<code>/admin</code>): contadores (total, enviados, pendentes, erro) e agendamentos recentes.</li>
        <li><strong>Cadastros</strong>: visitantes (nome, e-mail, telefone com +55, Telegram, ativo). Busca por nome/e-mail/telefone; criar, editar e excluir (agendamentos vinculados são mantidos).</li>
        <li><strong>Usuários</strong>: contas do sistema. Visíveis conforme a hierarquia (abaixo do seu nível sempre; mesmo nível só com <code>can_manage_admins</code>).</li>
        <li><strong>Disparos</strong>: todos os agendamentos com filtros por status, destino e data. Ações por linha: <strong>reenviar</strong> (pendente/erro), <strong>cancelar</strong> (bloqueado se já enviado) e troca direta de status.</li>
        <li><strong>Grupos</strong>: cadastro com nome, <code>chat_id</code>, tipo de chat, regra (aberto/fechado), horários, dias e ordem. Importação automática via <strong>listar do Telegram</strong> (só grupos onde o bot está e houve mensagem recente).</li>
        <li><strong>Config. disparos</strong>: modo (<code>agendado</code> ou <code>imediato</code>) e horário padrão.</li>
        <li><strong>Teste Telegram</strong>: valida a conexão (<code>getMe</code>), envia mensagem de teste e gera/verifica códigos de 6 dígitos.</li>
        <li><strong>Bots Telegram</strong>: vários bots (nome, username sem @, token — nunca exibido). Um deles é o <strong>padrão ativo</strong>, usado nos envios. Dá para importar do ambiente (<code>.env</code>).</li>
    </ul>
</div></div>

<div class="card stat-card mb-3" id="m-papeis"><div class="card-body">
    <h2 class="h6">4 · Papéis e permissões</h2>
    <p class="mb-2">Hierarquia (menor nível = mais poder): <code>root</code> (0) → <code>system</code> (1) → <code>master</code> (2) → <code>super-admin</code> (3) → <code>admin</code> (4) → <code>user</code> (5, visitante) → <code>guest</code> (6). A área <code>/admin</code> exige papel de sistema (<code>root</code> a <code>admin</code>); demais casos recebem 403. Na tela de Usuários cada um só vê quem está abaixo do próprio nível (pares só com <code>can_manage_admins</code>).</p>
</div></div>

<div class="card stat-card mb-3" id="m-grupos"><div class="card-body">
    <h2 class="h6">5 · Grupos: aberto × fechado</h2>
    <ul class="mb-0">
        <li><strong>Aberto</strong>: horário livre de envio; posição calculada por minuto.</li>
        <li><strong>Fechado</strong>: tem <strong>abertura</strong>, <strong>fechamento</strong> e <strong>disparo em bloco</strong> (horário fixo) + <strong>dias da semana</strong> (1=seg … 7=dom, vazio = todos). Antes da abertura o agendamento é bloqueado; após o fechamento vale a próxima rodada. Posição calculada por rodada.</li>
        <li>O campo <strong>ordem</strong> define a sequência de exibição dos grupos.</li>
    </ul>
</div></div>

<div class="card stat-card mb-3" id="m-disparo"><div class="card-body">
    <h2 class="h6">6 · Disparo automático</h2>
    <p class="mb-2">Um worker (n8n) chama <code>GET /api/n8n/disparos/processar</code> <strong>a cada minuto</strong> com o cabeçalho <code>X-N8N-Secret</code> (igual ao <code>N8N_WORKER_SECRET</code> do ambiente). Ele processa em lote de 50, na ordem horário → grupo → confirmação, recalcula a posição no envio e devolve <code>{executado_em, processados, enviados, erros, ignorados}</code>. Grupo fechado só dispara após o <code>horario_disparo</code>.</p>
    <p class="mb-0">Status do agendamento: <span class="badge-tg badge-pendente">PENDENTE</span> <span class="badge-tg badge-enviado">ENVIADO</span> <span class="badge-tg badge-erro">ERRO</span> <span class="badge-tg badge-cancelado">CANCELADO</span>. Falhas de envio registram <code>mensagem_erro</code> e podem ser reenviadas pela tela de Disparos.</p>
</div></div>

<div class="card stat-card mb-3" id="m-bot"><div class="card-body">
    <h2 class="h6">7 · Bot do Telegram</h2>
    <p class="mb-0">Resumo: crie o bot com o <strong>@BotFather</strong> (<code>/newbot</code>), anote username e token, cadastre em <strong>Bots Telegram</strong> e marque como padrão, adicione o bot aos grupos (de preferência como administrador), envie uma mensagem em cada grupo e importe em <strong>Grupos → listar do Telegram</strong>. Passo a passo completo na página pública <a href="{{ route('ajuda.bot-telegram') }}" target="_blank" rel="noopener">Ajuda: Bot Telegram</a>.</p>
</div></div>

<div class="card stat-card mb-3" id="m-acesso"><div class="card-body">
    <h2 class="h6">8 · Conta e acesso</h2>
    <ul class="mb-0">
        <li>Entrada em <code>/login</code> (e-mail ou usuário + senha). Conta inicial: <code>admin@admin.com</code> / <code>admin</code> (troque a senha após o primeiro acesso).</li>
        <li>Saída pelo menu do perfil na sidebar (<strong>Sair</strong>).</li>
        <li>Perfil em <strong>Meu perfil</strong> (dados, senha, foto).</li>
        <li>Tema claro/escuro no botão 🌙 da barra superior (salvo no navegador); sidebar recolhível ☰.</li>
    </ul>
</div></div>

<div class="card stat-card mb-3" id="m-problemas"><div class="card-body">
    <h2 class="h6">9 · Solução de problemas</h2>
    <div class="af-table-wrap"><table class="table mb-0">
        <thead><tr><th>Sintoma</th><th>Causa provável</th><th>O que fazer</th></tr></thead>
        <tbody>
            <tr><td data-label="Sintoma">Botão do Telegram não aparece no acesso</td><td data-label="Causa provável">Domínio não liberado ou sem bot</td><td data-label="O que fazer">No <strong>@BotFather</strong>, comando <code>/setdomain</code> com o domínio do site; confira o bot padrão em <strong>Bots Telegram</strong></td></tr>
            <tr><td data-label="Sintoma">Mensagem não chega no grupo</td><td data-label="Causa provável">Bot fora do grupo ou sem permissão; grupo inativo</td><td data-label="O que fazer">Confira em Teste Telegram (getMe + envio de teste); promova o bot a administrador</td></tr>
            <tr><td data-label="Sintoma">Grupo não aparece em “listar do Telegram”</td><td data-label="Causa provável">Sem atividade recente com o bot</td><td data-label="O que fazer">Envie uma mensagem no grupo e recarregue a página</td></tr>
            <tr><td data-label="Sintoma">Disparo saiu no horário errado</td><td data-label="Causa provável">Cron do n8n com timezone diferente</td><td data-label="O que fazer">Ajuste o cron para <code>America/Sao_Paulo</code>; confira modo/horários do grupo</td></tr>
            <tr><td data-label="Sintoma">Disparo com erro</td><td data-label="Causa provável">Ver <code>mensagem_erro</code> na linha</td><td data-label="O que fazer">Corrija (token, chat_id, bot bloqueado) e use <strong>Reenviar</strong></td></tr>
            <tr><td data-label="Sintoma">Não consigo cancelar</td><td data-label="Causa provável">Já foi enviado</td><td data-label="O que fazer">Agendamentos enviados não podem ser cancelados (proteção)</td></tr>
            <tr><td data-label="Sintoma">Usuário não vê alguém na lista</td><td data-label="Causa provável">Hierarquia de papéis</td><td data-label="O que fazer">Só vê níveis abaixo do seu (pares exigem <code>can_manage_admins</code>)</td></tr>
        </tbody>
    </table></div>
</div></div>
@endsection
