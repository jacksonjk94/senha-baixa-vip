<div class="modal fade" id="modalContexto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <h2 class="h5 mb-0">👁 Posição no grupo</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body" id="contextoCorpo">
            <p class="text-muted">Carregando…</p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
    </div></div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('modalContexto');
        var corpo = document.getElementById('contextoCorpo');
        if (!modalEl || !corpo) return;

        function hora(ts) {
            if (!ts) return '';
            try {
                var d = new Date(Number(ts) * 1000);
                return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
            } catch (e) { return ''; }
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-contexto-url]');
            if (!btn || typeof bootstrap === 'undefined') return;
            e.preventDefault();
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            corpo.innerHTML = '<p class="text-muted">Carregando…</p>';
            modal.show();

            fetch(btn.getAttribute('data-contexto-url'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (j) { return { status: r.status, corpo: j }; }); })
                .then(function (ret) {
                    if (ret.status !== 200 || !ret.corpo.mensagens) {
                        corpo.innerHTML = '<p class="text-danger mb-0">' + ((ret.corpo && ret.corpo.erro) || 'Sem mensagens capturadas para este disparo.') + '</p>';
                        return;
                    }
                    var d = ret.corpo;
                    var posicao = Number(d.posicao) || 0;
                    var total = Number(d.total) || d.mensagens.length;
                    var depois = posicao > 0 ? posicao - 1 : 0;
                    var html = '<p class="mb-2">'
                        + (posicao > 0
                            ? 'Sua mensagem é a <strong>Nº ' + posicao + '</strong> de ' + total + ' recentes (1 = mais recente)'
                                + (posicao === 1 ? ' 🎉 <strong>é a mais recente!</strong>' : ' · ' + depois + ' mensagem(ns) após a sua')
                            : 'Sua mensagem não está entre as ' + total + ' recentes')
                        + (d.ao_vivo ? ' <span class="badge-tg badge-enviado">AO VIVO</span>' : ' <span class="badge-tg badge-pendente">NO ENVIO</span>')
                        + (d.capturado_em ? ' <small class="text-muted">(' + d.capturado_em + ')</small>' : '')
                        + '</p><div class="d-grid gap-2">';
                    // Ordem igual ao grupo: mais antigas no topo, mais recentes embaixo.
                    d.mensagens.slice().reverse().forEach(function (m) {
                        var nossa = String(m.id) === String(d.message_id);
                        var div = document.createElement('div');
                        div.className = 'p-2 rounded' + (nossa ? ' border border-primary' : ' border');
                        var titulo = document.createElement('div');
                        titulo.className = 'small fw-bold' + (nossa ? ' text-primary' : ' text-muted');
                        titulo.textContent = (nossa ? '⭐ SUA MENSAGEM' : 'Mensagem') + (m.data ? ' · ' + hora(m.data) : '');
                        var texto = document.createElement('div');
                        texto.textContent = m.texto || '(sem texto)';
                        div.appendChild(titulo);
                        div.appendChild(texto);
                        html += div.outerHTML;
                    });
                    corpo.innerHTML = html + '</div>';
                })
                .catch(function () {
                    corpo.innerHTML = '<p class="text-danger mb-0">Falha ao carregar. Tente novamente.</p>';
                });
        });
    })();
</script>
