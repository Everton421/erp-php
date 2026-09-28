<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('estoque_saida');

$produtoId = (int)($_GET['produto'] ?? 0);
$produto = $produtoId > 0 ? buscar_linha('produtos', $produtoId) : null;

$tituloPagina = 'Saída de estoque';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-box-arrow-up me-2"></i>Saída de estoque</h1>
        <span class="subtitulo">Registre saídas por perda, avaria, consumo etc.</span>
    </div>
    <a href="<?= url('estoque/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('estoque/salvar.php') ?>" class="js-converte">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="saida">

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
                            <label class="form-label obrigatorio" for="motivo">Motivo</label>
                            <select class="form-select" id="motivo" name="motivo" required>
                                <option value="PERDA">Perda</option>
                                <option value="AVARIA">Avaria</option>
                                <option value="CONSUMO INTERNO">Consumo interno</option>
                                <option value="AJUSTE NEGATIVO">Ajuste negativo</option>
                                <option value="DEVOLUÇÃO">Devolução</option>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Quantidade</span>
                        <span class="fs-4 fw-bold text-danger" id="resQtd">0,000</span>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg w-100 mt-3"><i class="bi bi-box-arrow-up me-1"></i>Registrar saída</button>
                    <div class="small text-muted mt-2"><i class="bi bi-exclamation-triangle"></i> Se o estoque negativo estiver desativado, a saída acima do saldo será bloqueada.</div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    $(function () {
        APP.autocompletar($('#buscaProduto'), APP.baseUrl + '/api/produtos.php', function (p) {
            $('#produtoId').val(p.id);
            $('#buscaProduto').val(p.descricao);
            $('#infoProduto').html('Código: <b>' + APP.esc(p.codigo || '-') + '</b> • Barras: <b>' + APP.esc(p.codigo_barras || '-') + '</b> • Estoque atual: <b>' + APP.fmtNumero(p.estoque_atual, 3) + '</b> <span class="badge bg-primary-subtle text-primary">' + APP.esc(p.unidade || '') + '</span>');
        });
        $('#buscaProduto').on('input', function () { $('#produtoId').val(''); });
        $('#quantidade').on('blur', function () { $('#resQtd').text(APP.fmtNumero(APP.paraNumero(this.value), 3)); });
        $('#resQtd').text(APP.fmtNumero(APP.paraNumero($('#quantidade').val()), 3));
    });
</script>
<?php include INC . 'footer.php'; ?>