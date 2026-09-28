<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('estoque_ajuste');

$produtoId = (int)($_GET['produto'] ?? 0);
$produto = $produtoId > 0 ? buscar_linha('produtos', $produtoId) : null;

$tituloPagina = 'Ajuste de estoque';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-arrow-repeat me-2"></i>Ajuste de estoque</h1>
        <span class="subtitulo">Entrada, saída ou ajuste direto do saldo</span>
    </div>
    <a href="<?= url('estoque/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('estoque/salvar.php') ?>" class="js-converte" id="formAjuste">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="ajuste">
    <input type="hidden" name="tipo_ajuste" id="tipoAjusteHidden" value="AJUSTE">

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
                            <label class="form-label" for="estAnterior">Estoque atual</label>
                            <input type="text" class="form-control" id="estAnterior" value="0,000" readonly>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label obrigatorio" for="tipoAjuste">Tipo</label>
                            <select class="form-select" id="tipoAjuste" name="tipo_ajuste_select" required>
                                <option value="AJUSTE">Ajuste direto (nova quantidade)</option>
                                <option value="ENTRADA">Entrada (somar quantidade)</option>
                                <option value="SAIDA">Saída (subtrair quantidade)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="novaQtdLabel" id="lblQtd">Quantidade</label>
                            <input type="text" class="form-control" id="novaQtd" name="nova_quantidade" data-qtde value="0,000" required>
                        </div>
                        <div class="col-12 col-md-4" id="rowDiferenca">
                            <label class="form-label">Diferença</label>
                            <input type="text" class="form-control" id="diferenca" readonly>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="motivo">Motivo</label>
                            <select class="form-select" id="motivo" name="motivo" required>
                                <option value="CONTAGEM">Contagem / inventário</option>
                                <option value="ERRO DE CADASTRO">Erro de cadastro</option>
                                <option value="OUTROS">Outros</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
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
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Atual</span><b id="rAnterior">0,000</b></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Operação</span><b id="rOperacao">+0,000</b></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Novo saldo</span><b id="rNova">0,000</b></div>
                    <div class="alert alert-warning small mt-3 mb-0" id="msgTipo">
                        <i class="bi bi-exclamation-triangle me-1"></i>Toda movimentação gera um registro no histórico.
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning btn-lg w-100" id="btnSubmit"><i class="bi bi-check-lg me-1"></i>Confirmar ajuste</button>
        </div>
    </div>
</form>

<script>
    $(function () {
        const urlApi = APP.baseUrl + '/api/produtos.php';
        let estoqueAtual = 0;

        function trocarTipo() {
            const tipo = $('#tipoAjuste').val();
            $('#tipoAjusteHidden').val(tipo);
            $('#rowDiferenca').toggle(tipo === 'AJUSTE');

            if (tipo === 'ENTRADA') {
                $('#lblQtd').text('Quantidade a adicionar');
                $('#novaQtd').val(APP.fmtNumero(0, 3));
                $('#btnSubmit').html('<i class="bi bi-box-arrow-in-down me-1"></i>Registrar entrada').removeClass('btn-warning').addClass('btn-success');
                $('#msgTipo').html('<i class="bi bi-info-circle me-1"></i>Soma ao estoque atual. Registro fica no histórico de movimentações.');
                $('#msgTipo').removeClass('alert-warning').addClass('alert-success');
            } else if (tipo === 'SAIDA') {
                $('#lblQtd').text('Quantidade a retirar');
                $('#novaQtd').val(APP.fmtNumero(0, 3));
                $('#btnSubmit').html('<i class="bi bi-box-arrow-up me-1"></i>Registrar saída').removeClass('btn-warning').addClass('btn-danger');
                $('#msgTipo').html('<i class="bi bi-exclamation-triangle me-1"></i>Subtrai do estoque atual. Verifique o saldo antes de confirmar.');
                $('#msgTipo').removeClass('alert-warning').addClass('alert-danger');
            } else {
                $('#lblQtd').text('Nova quantidade');
                $('#novaQtd').val(APP.fmtNumero(estoqueAtual, 3));
                $('#btnSubmit').html('<i class="bi bi-check-lg me-1"></i>Confirmar ajuste').removeClass('btn-success btn-danger').addClass('btn-warning');
                $('#msgTipo').html('<i class="bi bi-exclamation-triangle me-1"></i>Define o saldo direto. Toda movimentação gera registro no histórico.');
                $('#msgTipo').removeClass('alert-success alert-danger').addClass('alert-warning');
            }
            atualizar();
        }

        function atualizar() {
            const ant = APP.paraNumero($('#estAnterior').val());
            const tipo = $('#tipoAjuste').val();
            const val = APP.paraNumero($('#novaQtd').val());
            let nova, operacao;

            if (tipo === 'ENTRADA') {
                operacao = '+' + APP.fmtNumero(val, 3);
                nova = ant + val;
            } else if (tipo === 'SAIDA') {
                operacao = '-' + APP.fmtNumero(val, 3);
                nova = ant - val;
            } else {
                operacao = (val >= ant ? '+' : '') + APP.fmtNumero(val - ant, 3);
                nova = val;
            }

            $('#diferenca').val(APP.fmtNumero(nova - ant, 3));
            $('#rAnterior').text(APP.fmtNumero(ant, 3));
            $('#rOperacao').text(operacao);
            $('#rNova').text(APP.fmtNumero(nova, 3));
        }

        APP.autocompletar($('#buscaProduto'), urlApi, function (p) {
            $('#produtoId').val(p.id);
            $('#buscaProduto').val(p.descricao);
            estoqueAtual = parseFloat(p.estoque_atual) || 0;
            $('#estAnterior').val(APP.fmtNumero(estoqueAtual, 3));
            $('#infoProduto').html('Código: <b>' + APP.esc(p.codigo || '-') + '</b> &bull; Barras: <b>' + APP.esc(p.codigo_barras || '-') + '</b> &bull; Estoque: <b>' + APP.fmtNumero(estoqueAtual, 3) + '</b> <span class="badge bg-primary-subtle text-primary">' + APP.esc(p.unidade || '') + '</span>');
            trocarTipo();
        });

        $('#buscaProduto').on('input', function () { $('#produtoId').val(''); });
        $('#tipoAjuste').on('change', trocarTipo);
        $('#novaQtd').on('blur', atualizar);

        if ($('#produtoId').val()) {
            estoqueAtual = <?= $produto ? str_replace(',', '.', $produto['estoque_atual']) : '0' ?>;
            $('#estAnterior').val('<?= $produto ? e(formatar_qtde($produto['estoque_atual'])) : '0' ?>');
            $('#novaQtd').val('<?= $produto ? e(formatar_qtde($produto['estoque_atual'])) : '0' ?>');
            atualizar();
        }
    });
</script>
<?php include INC . 'footer.php'; ?>
