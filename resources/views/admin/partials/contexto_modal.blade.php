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

        var estado = null;

        function hora(ts) {
            if (!ts) return '';
            try {
                var d = new Date(Number(ts) * 1000);
                return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
            } catch (e) { return ''; }
        }

        function cabecalhoHtml() {
            var d = estado.dados;
            var posicao = Number(d.posicao) || 0;
            var fixa = !!d.posicao_fixa;
            return (posicao > 0
                    ? 'Sua mensagem é a <strong>Nº ' + posicao + '</strong> (1 = mais recente)'
                        + (fixa ? ' 🎯 <strong>posição identificada</strong> — role para ver a atual'
                            : (posicao === 1 ? ' 🎉 <strong>é a mais recente!</strong>' : ' · ' + (posicao - 1) + ' mensagem(ns) após a sua'))
                    : 'Sua mensagem ainda não apareceu — role para carregar mais')
                + (d.ao_vivo ? ' <span class="badge-tg badge-enviado">AO VIVO</span>' : ' <span class="badge-tg badge-pendente">NO ENVIO</span>')
                + (fixa ? ' <span class="badge-tg badge-pendente">GRAVADA</span>' : '')
                + (d.capturado_em ? ' <small class="text-muted">(' + d.capturado_em + ')</small>' : '');
        }

        function atualizarCabecalho() {
            var el = document.getElementById('contextoResumo');
            if (el) el.innerHTML = cabecalhoHtml();
        }

        function bolha(m, messageId) {
            var nossa = String(m.id) === String(messageId);
            var div = document.createElement('div');
            div.className = 'p-2 rounded' + (nossa ? ' border border-primary' : ' border');
            if (nossa) div.id = 'contextoNossa';
            var titulo = document.createElement('div');
            titulo.className = 'small fw-bold' + (nossa ? ' text-primary' : ' text-muted');
            titulo.textContent = (nossa ? '⭐ SUA MENSAGEM' : 'Mensagem') + (m.data ? ' · ' + hora(m.data) : '');
            var texto = document.createElement('div');
            texto.textContent = m.texto || '(sem texto)';
            div.appendChild(titulo);
            div.appendChild(texto);
            return div;
        }

        function desenhar(inicio) {
            var d = estado.dados;
            // Esqueleto fixo: cabeçalho grudado no topo (não sobe com a
            // rolagem), lista e zona de "carregar mais".
            var html = '<div id="contextoResumoWrap" style="position:sticky;top:-1rem;margin:0 -1rem;padding:.5rem 1rem;background:var(--af-surface,#fff);z-index:5;border-bottom:1px solid var(--af-border,#E2EAF1);">'
                + '<p class="mb-0" id="contextoResumo"></p></div>'
                + '<div class="d-grid gap-2 mt-2" id="contextoLista"></div>'
                + '<div id="contextoMais" class="text-center mt-2"></div>';
            corpo.innerHTML = html;
            atualizarCabecalho();
            // Ordem igual ao grupo: mais antigas no topo, mais recentes embaixo.
            var lista = document.getElementById('contextoLista');
            estado.todas.slice().reverse().forEach(function (m) {
                lista.appendChild(bolha(m, d.message_id));
            });
            atualizarMais();
        }

        function anexar(novas, messageId) {
            var lista = document.getElementById('contextoLista');
            if (!lista) return;
            novas.slice().reverse().forEach(function (m) {
                lista.appendChild(bolha(m, messageId));
            });
        }

        function atualizarMais() {
            var zona = document.getElementById('contextoMais');
            if (!zona) return;
            if (estado.carregando) {
                zona.innerHTML = '<p class="text-muted small mb-0">Carregando mais…</p>';
            } else if (estado.temMais) {
                zona.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary" id="contextoBtnMais">Carregar mais mensagens</button>';
            } else {
                zona.innerHTML = '<p class="text-muted small mb-0">Fim do histórico carregado.</p>';
            }
        }

        function carregarMais() {
            if (!estado || estado.carregando || !estado.temMais || !estado.nextOffset) return;
            estado.carregando = true;
            atualizarMais();
            var url = estado.urlBase + (estado.urlBase.indexOf('?') >= 0 ? '&' : '?')
                + 'limit=15&offset_id=' + encodeURIComponent(estado.nextOffset)
                + '&lidas=' + estado.exibidas;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    estado.carregando = false;
                    if (!d || !d.mensagens) {
                        estado.temMais = false;
                        atualizarMais();
                        return;
                    }
                    d.mensagens.forEach(function (m) { estado.todas.push(m); });
                    estado.exibidas = estado.todas.length;
                    estado.temMais = !!d.tem_mais;
                    estado.nextOffset = d.next_offset_id || null;
                    if (d.posicao) {
                        estado.dados.posicao = d.posicao;
                        estado.dados.posicao_fixa = false;
                        estado.achou = true;
                    }
                    atualizarCabecalho();
                    anexar(d.mensagens, estado.dados.message_id);
                    atualizarMais();
                    if (estado.achou) {
                        var nossa = document.getElementById('contextoNossa');
                        if (nossa) nossa.scrollIntoView({ block: 'center' });
                    }
                })
                .catch(function () {
                    estado.carregando = false;
                    atualizarMais();
                });
        }

        corpo.addEventListener('scroll', function () {
            if (!estado || estado.carregando || !estado.temMais) return;
            if (corpo.scrollTop + corpo.clientHeight >= corpo.scrollHeight - 250) {
                carregarMais();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('#contextoBtnMais')) {
                carregarMais();
                return;
            }
            var btn = e.target.closest('[data-contexto-url]');
            if (!btn || typeof bootstrap === 'undefined') return;
            e.preventDefault();
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            estado = {
                urlBase: btn.getAttribute('data-contexto-url'),
                dados: null, todas: [], exibidas: 0,
                temMais: false, nextOffset: null,
                carregando: true, achou: false,
            };
            corpo.innerHTML = '<p class="text-muted">Carregando…</p>';
            corpo.scrollTop = 0;
            modal.show();

            fetch(estado.urlBase + (estado.urlBase.indexOf('?') >= 0 ? '&' : '?') + 'limit=15', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (j) { return { status: r.status, corpo: j }; }); })
                .then(function (ret) {
                    estado.carregando = false;
                    if (ret.status !== 200 || !ret.corpo.mensagens) {
                        corpo.innerHTML = '<p class="text-danger mb-0">' + ((ret.corpo && ret.corpo.erro) || 'Sem mensagens capturadas para este disparo.') + '</p>';
                        return;
                    }
                    estado.dados = ret.corpo;
                    estado.todas = ret.corpo.mensagens.slice();
                    estado.exibidas = estado.todas.length;
                    estado.temMais = !!ret.corpo.tem_mais;
                    estado.nextOffset = ret.corpo.next_offset_id || null;
                    estado.achou = !!ret.corpo.posicao;
                    desenhar(true);
                })
                .catch(function () {
                    estado.carregando = false;
                    corpo.innerHTML = '<p class="text-danger mb-0">Falha ao carregar. Tente novamente.</p>';
                });
        });
    })();
</script>
