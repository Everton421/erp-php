<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('estoque_entrada');

$produtoId = (int)($_GET['produto'] ?? 0);
$produto = $produtoId > 0 ? buscar_linha('produtos', $produtoId) : null;
$fornecedores = db()->query('SELECT id, razao_social FROM fornecedores ORDER BY razao_social')->fetchAll();

$tituloPagina = 'Entrada de estoque';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-box-arrow-in-down me-2"></i>Entrada de estoque</h1>
        <span class="subtitulo">Registre a entrada de mercadorias</span>
    </div>
    <a href="<?= url('estoque/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('estoque/salvar.php') ?>" class="js-converte">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="entrada">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-box-seam"></i>Produto</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label obrigatorio" for="buscaProduto">Produto</label>
                            <div class="auto-wrapper">
                                <input type="text" class="form-control" id="buscaProduto" placeholder="Busque por descrição, código ou código de barras..." autocomplete="off"
                                       value="<?= $produto ? e($produto['descricao']) : '' ?>" required>
                                <input type="hidden" name="produto_id" id="produtoId" value="<?= (int)$produtoId ?>" required>
                            </div>
                            <div class="small text-muted mt-1">
                                <span id="infoProduto">
                                    <?= $produto ? 'Estoque atual: <b>' . formatar_qtde($produto['estoque_atual']) . '</b>' : '<i class="bi bi-info-circle"></i> Selecione um produto para ver o estoque.' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label obrigatorio" for="quantidade">Quantidade</label>
                            <input type="text" class="form-control" id="quantidade" name="quantidade" data-qtde value="1,000">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="custo">Custo unitário (R$)</label>
                            <input type="text" class="form-control" id="custo" name="custo" data-moeda value="<?= $produto ? e(formatar_moeda($produto['preco_custo'])) : '0,00' ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="documento">Documento</label>
                            <input type="text" class="form-control" id="documento" name="documento" placeholder="NF, ordem, etc." maxlength="40">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="fornecedor_id">Fornecedor</label>
                            <select class="form-select" id="fornecedor_id" name="fornecedor_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($fornecedores as $f): ?>
                                <option value="<?= (int)$f['id'] ?>"><?= e($f['razao_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="motivo">Motivo</label>
                            <select class="form-select" id="motivo" name="motivo" required>
                                <option value="COMPRA">Compra</option>
                                <option value="DEVOLUÇÃO">Devolução</option>
                                <option value="AJUSTE POSITIVO">Ajuste positivo</option>
                                <option value="OUTROS">Outros</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="data">Data</label>
                            <input type="date" class="form-control" id="data" name="data" value="<?= hoje() ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="observacao">Observação</label>
                            <textarea class="form-control" id="observacao" name="observacao" rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-journal-text"></i>Resumo</div>
                <div class="card-body-custom">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Custo unitário</span><b id="resCusto">R$ 0,00</b></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Quantidade</span><b id="resQtd">0,000</b></div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total da entrada</span>
                        <span class="fs-4 fw-bold text-success" id="resTotal">R$ 0,00</span>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100 mt-3"><i class="bi bi-box-arrow-in-down me-1"></i>Registrar entrada</button>
                </div>
            </div>
            <div class="alert alert-info small">
                <i class="bi bi-lightbulb me-1"></i>A entrada soma ao estoque atual e fica registrada no histórico,
                sem alterar manualmente o produto.
            </div>
        </div>
    </div>
</form>

<script>
    $(function () {
        const urlApi = APP.baseUrl + '/api/produtos.php';
        APP.autocompletar($('#buscaProduto'), urlApi, function (p) {
            $('#produtoId').val(p.id);
            $('#buscaProduto').val(p.descricao);
            $('#custo').val(APP.fmtNumero(p.preco_custo, 2));
            $('#infoProduto').html('Código: <b>' + APP.esc(p.codigo || '-') + '</b> • Barras: <b>' + APP.esc(p.codigo_barras || '-') + '</b> • Estoque atual: <b>' + APP.fmtNumero(p.estoque_atual, 3) + '</b> <span class="badge bg-primary-subtle text-primary">' + APP.esc(p.unidade || '') + '</span>');
            recalcular();
        });
        $('#buscaProduto').on('input', function () { $('#produtoId').val(''); });

        function recalcular() {
            const qtd = APP.paraNumero($('#quantidade').val());
            const custo = APP.paraNumero($('#custo').val());
            $('#resCusto').text(APP.fmtMoeda(custo));
            $('#resQtd').text(APP.fmtNumero(qtd, 3));
            $('#resTotal').text(APP.fmtMoeda(qtd * custo));
        }
        $('#quantidade, #custo').on('blur', recalcular);
        recalcular();
    });
</script>
<?php include INC . 'footer.php'; ?>