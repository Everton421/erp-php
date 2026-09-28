<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('vendas_criar');

$formas = db()->query(
    'SELECT f.* FROM formas_pagamento f WHERE f.ativo = 1 ORDER BY f.nome'
)->fetchAll();

$tiposPedido = db()->query(
    "SELECT id, nome, gera_financeiro FROM tipos_pedido WHERE modulo = 'VENDA' AND ativo = 1 ORDER BY nome"
)->fetchAll();

$tipoPadrao = (int)obter_config('venda_tipo_pedido_padrao', 0);
$formaPadrao = (int)obter_config('venda_forma_pagamento_padrao', 0);
$rascunho = $_SESSION['venda_rascunho'] ?? null;

$tituloPagina = 'Nova venda';
include INC . 'header.php';
?>
<style>
    .cart-item td { vertical-align: middle; }
    .cart-item .qtd-input { width: 74px; }
    .cart-item .val-input { width: 96px; }
    .pgto-row { border: 1px solid var(--border); border-radius: 12px; padding: .7rem .8rem; margin-bottom: .6rem; background: #fbfcff; }
</style>

<div class="page-header">
    <div>
        <h1><i class="bi bi-cart-plus me-2"></i>Nova venda</h1>
        <span class="subtitulo">Lançamento com pagamentos múltiplos e parcelamento</span>
    </div>
    <a href="<?= url('vendas/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('vendas/salvar.php') ?>" id="formVenda">
    <?= csrf_field() ?>
    <input type="hidden" name="cliente_id" id="clienteId" value="">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <!-- Cliente -->
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-person-badge"></i>Cliente</div>
                <div class="card-body-custom">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="chkConsumidor" checked>
                        <label class="form-check-label" for="chkConsumidor">Consumidor final (sem cliente)</label>
                    </div>
                    <div id="areaCliente" style="display:none">
                        <div class="auto-wrapper">
                            <input type="text" class="form-control" id="buscaCliente" placeholder="Busque por nome, documento ou cidade..." autocomplete="off">
                        </div>
                        <div class="small text-muted mt-1"><span id="infoCliente"><i class="bi bi-info-circle"></i> Selecione um cliente.</span></div>
                    </div>
                </div>
            </div>

            <!-- Tipo de pedido -->
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-tag"></i>Tipo de pedido</div>
                <div class="card-body-custom">
                    <select class="form-select" id="tipoPedido" name="tipo_pedido_id" required>
                        <?php foreach ($tiposPedido as $tp): ?>
                        <option value="<?= (int)$tp['id'] ?>" <?= $tipoPadrao === (int)$tp['id'] ? 'selected' : '' ?>><?= e($tp['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Itens -->
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-box-seam"></i>Itens da venda</div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="buscaProduto">Adicionar produto</label>
                        <div class="auto-wrapper">
                            <input type="text" class="form-control form-control-lg" id="buscaProduto"
                                   placeholder="Busque por descrição, código ou código de barras (Enter adiciona o 1º resultado)..." autocomplete="off">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="tabelaItens">
                            <thead>
                                <tr>
                                    <th style="width:34%">Produto</th>
                                    <th class="text-center" style="width:13%">Qtd.</th>
                                    <th class="text-end" style="width:17%">Preço</th>
                                    <th class="text-end" style="width:15%">Desc.</th>
                                    <th class="text-end" style="width:13%">Subtotal</th>
                                    <th style="width:4%"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2" id="vazioItens"><i class="bi bi-inbox"></i> Nenhum item — adicione produtos ao carrinho.</div>
                </div>
            </div>

            <!-- Pagamentos -->
            <div class="card mb-3">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cash-coin"></i>Pagamentos</span>
                    <button type="button" class="btn btn-sm btn-soft" id="btnAddPgto"><i class="bi bi-plus-lg me-1"></i>Pagamento</button>
                </div>
                <div class="card-body-custom">
                    <div id="listaPgto"><div class="text-muted small"><i class="bi bi-info-circle"></i> Adicione as formas de pagamento (dinheiro, PIX, cartão, parcelas...).</div></div>
                    <div id="totalPgtos" class="alert alert-primary small mb-0 d-none">
                        Total dos pagamentos: <b>R$ 0,00</b> <span id="faltaPgto" class="ms-1"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumo -->
        <div class="col-12 col-lg-4">
            <div class="card mb-3 sticky-top" style="top:76px">
                <div class="card-header-custom"><i class="bi bi-journal-text"></i>Resumo</div>
                <div class="card-body-custom">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><b id="rSubtotal">R$ 0,00</b></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Desconto</span><b class="text-danger" id="rDesconto">R$ 0,00</b></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Acréscimo</span><b class="text-success" id="rAcrescimo">R$ 0,00</b></div>
                    <div class="d-flex justify-content-between mb-3 pb-2 border-bottom"><span class="text-muted">Itens</span><b id="rItens">0</b></div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Desconto (R$)</label>
                            <input type="text" class="form-control form-control-sm" id="descontoGlobal" name="desconto" data-moeda value="0,00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Acréscimo (R$)</label>
                            <input type="text" class="form-control form-control-sm" id="acrescimoGlobal" name="acrescimo" data-moeda value="0,00">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">TOTAL</span>
                        <span class="fs-3 fw-bold text-success" id="rTotal">R$ 0,00</span>
                    </div>

                    <label class="form-label">Observação</label>
                    <textarea class="form-control mb-3" id="obs" name="observacao" rows="2" maxlength="500" placeholder="Observações da venda..."></textarea>

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="btnFinalizar"><i class="bi bi-check-lg me-1"></i>Finalizar venda</button>
                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="btnOrcamento"><i class="bi bi-file-earmark-text me-1"></i>Salvar como Orçamento</button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="tipo_operacao" id="tipoOperacao" value="VENDA">
    <input type="hidden" name="itens_json" id="itensJson">
    <input type="hidden" name="pagamentos_json" id="pgtosJson">
    <input type="hidden" name="rascunho_json" id="rascunhoJson">
</form>

<script>
    const FORMAS = <?= json_encode(array_map(static fn($f) => ['id' => (int)$f['id'], 'nome' => (string)$f['nome']], $formas), JSON_UNESCAPED_UNICODE) ?>;
    const FORMA_PADRAO = <?= (int)$formaPadrao ?>;
    const RASCUNHO = <?= json_encode($rascunho, JSON_UNESCAPED_UNICODE) ?>;
    const TIPOS_DADOS = <?= json_encode(array_map(static fn($t) => ['id' => (int)$t['id'], 'gera_financeiro' => (int)$t['gera_financeiro']], $tiposPedido), JSON_UNESCAPED_UNICODE) ?>;

    $(function () {
        const carrinho = [];
        const pagamentos = [];
        let pgSeq = 0;
        const urlProd = APP.baseUrl + '/api/produtos.php';

        /* ---- Cliente ---- */
        $('#chkConsumidor').on('change', function () {
            $('#areaCliente').toggle(!this.checked);
            if (this.checked) { $('#clienteId').val(''); $('#buscaCliente').val(''); $('#infoCliente').html('<i class="bi bi-info-circle"></i> Selecione um cliente.'); }
        });
        APP.autocompletar($('#buscaCliente'), APP.baseUrl + '/api/clientes.php', function (c) {
            $('#clienteId').val(c.id);
            $('#buscaCliente').val(c.nome);
            $('#infoCliente').html('Documento: <b>' + APP.esc(c.documento || '-') + '</b> • ' + APP.esc(c.cidade || '') + (c.uf ? '/' + c.uf : ''));
        });

        /* ---- Carrinho ---- */
        function adicionarItem(p) {
            const preco = (p.preco_promocional != null && p.preco_promocional > 0) ? p.preco_promocional : p.preco_venda;
            const idx = carrinho.findIndex(i => i.id === p.id);
            if (idx >= 0) {
                carrinho[idx].qtd = +(carrinho[idx].qtd + 1).toFixed(3);
                renderizar();
                return;
            }
            carrinho.push({
                id: p.id, codigo: p.codigo || p.codigo_barras || '',
                descricao: p.descricao, unidade: p.unidade || '',
                foto: p.foto || '',
                qtd: 1, preco: +preco, desconto: 0
            });
            renderizar();
        }

        APP.autocompletar($('#buscaProduto'), urlProd, function (p) {
            adicionarItem(p);
            $('#buscaProduto').val('').trigger('input').focus();
        }, function (p) {
            const preco = (p.preco_promocional != null && p.preco_promocional > 0) ? p.preco_promocional : p.preco_venda;
            const est = APP.fmtNumero(p.estoque_atual, 3).replace(/0+$/, '').replace(/[.,]$/, '');
            const cod = p.codigo_barras ? 'Barras: ' + p.codigo_barras : 'Código: ' + (p.codigo || '-');
            return { sub: cod + ' • Est.: ' + est + ' • Venda: ' + APP.fmtMoeda(preco), extra: '' };
        });

        $('#buscaProduto').on('keydown', function (e) {
            if (e.key !== 'Enter' || e.isDefaultPrevented()) return;
            e.preventDefault();
            const q = this.value.trim();
            if (q.length < 2) return;
            $.getJSON(urlProd, { q: q }).done(res => {
                if (res.dados && res.dados.length) {
                    adicionarItem(res.dados[0]);
                    const $box = $('#buscaProduto').data('autocompleteBox');
                    if ($box) $box.empty().hide();
                    $('#buscaProduto').val('').trigger('input').focus();
                }
            });
        });

        function linhaItem(i) {
            const tot = i.qtd * i.preco - i.desconto;
            return '<tr class="cart-item" data-idx="' + i.id + '">' +
                '<td>' +
                '<div class="d-flex align-items-center gap-2">' +
                (i.foto ? '<img src="' + APP.baseUrl + '/' + i.foto + '" width="38" height="38" class="rounded object-fit-cover" style="flex-shrink:0" alt="">' : '') +
                '<div><b class="d-block" style="font-size:.82rem">' + APP.esc(i.descricao) + '</b>' +
                '<small class="text-muted">' + APP.esc(i.codigo) + ' <span class="badge bg-primary-subtle text-primary">' + APP.esc(i.unidade) + '</span></small></div></div>' +
                '</td>' +
                '<td class="text-center"><input type="text" class="form-control form-control-sm text-center qtd-input js-moqtre" data-id-qtd="' + i.id + '" value="' + APP.fmtNumero(i.qtd, 3) + '"></td>' +
                '<td class="text-end"><input type="text" class="form-control form-control-sm text-end val-input js-mopor" data-id-preco="' + i.id + '" value="' + APP.fmtNumero(i.preco, 2) + '"></td>' +
                '<td class="text-end"><input type="text" class="form-control form-control-sm text-end val-input js-modesc" data-id-desc="' + i.id + '" value="' + APP.fmtNumero(i.desconto, 2) + '"></td>' +
                '<td class="text-end fw-semibold" data-id-sub="' + i.id + '">' + APP.fmtMoeda(tot) + '</td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-soft-danger btn-icone js-remove" data-id-rm="' + i.id + '"><i class="bi bi-trash"></i></button></td></tr>';
        }

        function renderizar() {
            const $tb = $('#tabelaItens tbody').empty();
            carrinho.forEach(i => $tb.append(linhaItem(i)));
            $('#vazioItens').toggle(!carrinho.length);
            recalcular();
        }

        function somaPgtos() {
            return pagamentos.reduce((s, p) => s + p.valor, 0);
        }

        function tipoGeraFinanceiro() {
            const t = TIPOS_DADOS.find(x => x.id === parseInt($('#tipoPedido').val(), 10));
            return !t || t.gera_financeiro === 1;
        }

        $('#tipoPedido').on('change', function () { recalcular(); });

        function recalcular() {
            let subtotal = 0, descItens = 0;
            carrinho.forEach(i => {
                subtotal += i.qtd * i.preco;
                descItens += i.desconto;
            });
            const baseDesc = Math.max(subtotal - descItens, 0);
            const descGlobal = Math.min(APP.paraNumero($('#descontoGlobal').val()), baseDesc);
            const acrescGlobal = APP.paraNumero($('#acrescimoGlobal').val());
            const total = baseDesc - descGlobal + acrescGlobal;
            $('#rSubtotal').text(APP.fmtMoeda(subtotal));
            $('#rDesconto').text(APP.fmtMoeda(descItens + descGlobal));
            $('#rAcrescimo').text(APP.fmtMoeda(acrescGlobal));
            $('#rItens').text(carrinho.length);
            $('#rTotal').text(APP.fmtMoeda(total));

            carrinho.forEach(i => {
                const $td = $('#tabelaItens td[data-id-sub="' + i.id + '"]');
                if ($td.length) $td.text(APP.fmtMoeda(i.qtd * i.preco - i.desconto));
            });

            $('#totalPgtos b').text(APP.fmtMoeda(somaPgtos()));
            $('#totalPgtos').toggleClass('d-none', !pagamentos.length);
            const falta = total - somaPgtos();
            $('#faltaPgto').text(Math.abs(falta) > 0.005
                ? '• ' + (falta > 0 ? 'faltam ' : 'excedem ') + APP.fmtMoeda(Math.abs(falta))
                : '• OK ✓');
            $('#btnFinalizar').toggleClass('disabled', carrinho.length === 0 || (tipoGeraFinanceiro() && (Math.abs(falta) > 0.005 || !pagamentos.length)));
            return total;
        }

        /* ---- Edição inline dos itens ---- */
        $('#tabelaItens')
            .on('input', '.js-moqtre, .js-mopor, .js-modesc', function () {
                const tipo = $(this).hasClass('js-moqtre') ? 'qtd' : ($(this).hasClass('js-mopor') ? 'preco' : 'desconto');
                const id = $(this).data('id-' + tipo);
                const i = carrinho.find(x => x.id == id);
                if (!i) return;
                if (tipo === 'qtd') i.qtd = Math.max(APP.paraNumero(this.value), 0.001);
                if (tipo === 'preco') i.preco = Math.max(APP.paraNumero(this.value), 0);
                if (tipo === 'desconto') i.desconto = Math.min(Math.max(APP.paraNumero(this.value), 0), i.qtd * i.preco);
                recalcular();
            })
            .on('blur', '.js-moqtre', function () {
                const i = carrinho.find(x => x.id == $(this).data('id-qtd'));
                if (i) $(this).val(APP.fmtNumero(i.qtd, 3));
            })
            .on('blur', '.js-mopor', function () {
                const i = carrinho.find(x => x.id == $(this).data('id-preco'));
                if (i) $(this).val(APP.fmtNumero(i.preco, 2));
            })
            .on('blur', '.js-modesc', function () {
                const i = carrinho.find(x => x.id == $(this).data('id-desc'));
                if (i) $(this).val(APP.fmtNumero(i.desconto, 2));
            })
            .on('click', '.js-remove', function () {
                const idx = carrinho.findIndex(x => x.id == $(this).data('id-rm'));
                if (idx >= 0) carrinho.splice(idx, 1);
                renderizar();
            });

        $('#descontoGlobal, #acrescimoGlobal').on('blur', function () {
            recalcular();
            $(this).val(APP.fmtNumero(APP.paraNumero(this.value), 2));
        });

        /* ---- Pagamentos ---- */
        function optionsForma(selecionado) {
            return FORMAS.map(f =>
                '<option value="' + f.id + '"' + (f.id === selecionado ? ' selected' : '') + '>' + APP.esc(f.nome) + '</option>'
            ).join('');
        }

        function addPagamento(dados = null) {
            const id = ++pgSeq;
            pagamentos.push({
                id,
                forma_id: dados ? Number(dados.forma_id || 0) : (FORMA_PADRAO || (FORMAS[0] ? FORMAS[0].id : 0)),
                valor: dados ? Number(dados.valor || 0) : (APP.paraNumero($('#rTotal').text()) || 0),
                qtde_parcelas: dados ? Math.max(Number(dados.qtde_parcelas || 1), 1) : 1
            });
            $('#listaPgto p').remove();
            $('#listaPgto').append(
                '<div class="pgto-row" data-pg="' + id + '">' +
                '<div class="row g-2 align-items-center">' +
                '<div class="col-12 col-md-4 col-lg-5">' +
                '<select class="form-select form-select-sm js-pg-forma" data-id="' + id + '">' + optionsForma(pagamentos.find(p => p.id === id).forma_id) + '</select></div>' +
                '<div class="col-6 col-md-3"><input type="text" class="form-control form-control-sm js-pg-valor" data-id="' + id + '" data-moeda value="' + APP.fmtNumero(pagamentos.find(p => p.id === id).valor, 2) + '"></div>' +
                '<div class="col-6 col-md-2"><input type="text" class="form-control form-control-sm js-pg-parc" data-id="' + id + '" data-int value="' + (pagamentos.find(p => p.id === id).qtde_parcelas || 1) + '"></div>' +
                '<div class="col-12 col-md-3 col-lg-2"><small class="text-muted d-none d-md-block">parcelas</small>' +
                '<button type="button" class="btn btn-sm btn-soft-danger btn-icone js-pg-remove" data-id="' + id + '"><i class="bi bi-x-lg"></i></button></div>' +
                '</div></div>'
            );
            recalcular();
        }

        $('#btnAddPgto').on('click', addPagamento);
        $('#listaPgto')
            .on('input', '.js-pg-valor', function () {
                const p = pagamentos.find(x => x.id == $(this).data('id'));
                if (p) { p.valor = Math.max(APP.paraNumero(this.value), 0); recalcular(); }
            })
            .on('blur', '.js-pg-valor', function () {
                const p = pagamentos.find(x => x.id == $(this).data('id'));
                if (p) $(this).val(APP.fmtNumero(p.valor, 2));
            })
            .on('keydown', '.js-pg-parc', function (e) {
                if (!/^\d$/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) e.preventDefault();
            })
            .on('input', '.js-pg-parc', function () {
                const p = pagamentos.find(x => x.id == $(this).data('id'));
                const n = parseInt(this.value, 10);
                if (p) p.qtde_parcelas = isNaN(n) ? 1 : Math.max(n, 1);
            })
            .on('click', '.js-pg-remove', function () {
                const id = $(this).data('id');
                const idx = pagamentos.findIndex(x => x.id == id);
                if (idx >= 0) pagamentos.splice(idx, 1);
                $(this).closest('.pgto-row').remove();
                if (!pagamentos.length) $('#listaPgto').prepend('<p class="text-muted small"><i class="bi bi-info-circle"></i> Adicione as formas de pagamento...</p>');
                recalcular();
            });

        if (RASCUNHO) {
            $('#clienteId').val(RASCUNHO.cliente_id || '');
            $('#tipoPedido').val(RASCUNHO.tipo_pedido_id || $('#tipoPedido').val());
            $('#descontoGlobal').val(RASCUNHO.desconto || '0,00');
            $('#acrescimoGlobal').val(RASCUNHO.acrescimo || '0,00');
            $('#obs').val(RASCUNHO.observacao || '');
            $('#tipoOperacao').val(RASCUNHO.tipo_operacao === 'ORCAMENTO' ? 'ORCAMENTO' : 'VENDA');

            let dadosRascunho = {};
            try { dadosRascunho = JSON.parse(RASCUNHO.rascunho_json || '{}'); } catch (e) {}
            if (Array.isArray(dadosRascunho.itens)) {
                dadosRascunho.itens.forEach(i => carrinho.push(i));
            }
            $('#buscaCliente').val(dadosRascunho.cliente_nome || '');
            renderizar();
            if (Array.isArray(dadosRascunho.pagamentos) && dadosRascunho.pagamentos.length) {
                dadosRascunho.pagamentos.forEach(p => addPagamento(p));
            } else {
                addPagamento();
            }
            $('#chkConsumidor').prop('checked', !RASCUNHO.cliente_id).trigger('change');
        } else {
            addPagamento();
        }

        /* ---- Submit ---- */
        $('#btnOrcamento').on('click', function () {
            $('#tipoOperacao').val('ORCAMENTO');
            $('#formVenda').trigger('submit');
        });

        $('#btnFinalizar').on('click', function () {
            $('#tipoOperacao').val('VENDA');
        });

        $('#formVenda').on('submit', function (e) {
            const ehOrcamento = $('#tipoOperacao').val() === 'ORCAMENTO';
            const total = APP.paraNumero(String($('#rTotal').text()).replace(/[^\d,.-]/g, ''));
            const falta = pagamentos.reduce((s, p) => s + p.valor, 0) - total;

            if (!carrinho.length) {
                e.preventDefault();
                Swal.fire('Atenção', 'Adicione ao menos um item ' + (ehOrcamento ? 'ao orçamento.' : 'à venda.'), 'warning');
                return;
            }

            if (!ehOrcamento && tipoGeraFinanceiro() && (!pagamentos.length || Math.abs(falta) > 0.005)) {
                e.preventDefault();
                Swal.fire('Atenção', 'O total dos pagamentos deve ser igual ao total da venda.', 'warning');
                return;
            }

            // Para orçamento, se não informou pagamentos, adiciona forma padrão
            if (ehOrcamento && !pagamentos.length && FORMAS.length) {
                pagamentos.push({ id: ++pgSeq, forma_id: FORMA_PADRAO || FORMAS[0].id, valor: total, qtde_parcelas: 1 });
            }

            $('#itensJson').val(JSON.stringify(carrinho.map(i => ({
                produto_id: i.id, quantidade: i.qtd, preco_unitario: i.preco, desconto: i.desconto
            }))));
            $('#pgtosJson').val(JSON.stringify(pagamentos.map(p => ({
                forma_pagamento_id: p.forma_id, valor: p.valor, qtde_parcelas: p.qtde_parcelas
            }))));
            $('#rascunhoJson').val(JSON.stringify({
                itens: carrinho,
                pagamentos: pagamentos,
                cliente_nome: $('#buscaCliente').val()
            }));
        });
    });
</script>
<?php include INC . 'footer.php'; ?>