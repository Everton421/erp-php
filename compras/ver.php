<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('compras_ver');

$id = (int)($_GET['id'] ?? 0);
$compra = buscar_linha('compras', $id);
if (!$compra) {
    flash('danger', 'Compra não encontrada.');
    redirecionar('compras/index.php');
}

$fornecedor = $compra['fornecedor_id'] ? buscar_linha('fornecedores', (int)$compra['fornecedor_id']) : null;
$criador = buscar_linha('usuarios', (int)$compra['criado_por']);
$cancelador = $compra['cancelado_por'] ? buscar_linha('usuarios', (int)$compra['cancelado_por']) : null;
$tipoPedido = $compra['tipo_pedido_id'] ? buscar_linha('tipos_pedido', (int)$compra['tipo_pedido_id']) : null;
$geraFinanceiro = $tipoPedido ? (int)$tipoPedido['gera_financeiro'] === 1 : true;

$stmt = db()->prepare(
    'SELECT ci.*, p.descricao AS produto_desc, p.codigo, p.foto, u.sigla AS unidade
       FROM compra_itens ci
       JOIN produtos p ON p.id = ci.produto_id
       LEFT JOIN unidades u ON u.id = p.unidade_id
      WHERE ci.compra_id = ? ORDER BY ci.id'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT cp.*, fp.nome AS forma
       FROM compra_pagamentos cp
       JOIN formas_pagamento fp ON fp.id = cp.forma_pagamento_id
      WHERE cp.compra_id = ? ORDER BY cp.id'
);
$stmt->execute([$id]);
$pagamentos = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM contas_pagar WHERE compra_id = ? ORDER BY vencimento, id');
$stmt->execute([$id]);
$contas = $stmt->fetchAll();

$tituloPagina = 'Compra ' . $compra['numero'];
include INC . 'header.php';
?>
<div class="page-header print-hide">
    <div>
        <h1><i class="bi bi-receipt me-2"></i>Compra <?= e($compra['numero']) ?></h1>
        <span class="subtitulo">Detalhes e contas a pagar</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (tem_permissao('compras_cancelar') && $compra['status'] === 'FINALIZADA'): ?>
        <button class="btn btn-outline-danger js-confirmar"
                data-titulo="Cancelar compra <?= e($compra['numero']) ?>?"
                data-texto="O estoque será baixado e os pagamentos estornados."
                data-url="<?= url('compras/cancelar.php') ?>"
                data-post='<?= e(json_encode(['id' => (int)$compra['id']])) ?>'>
            <i class="bi bi-x-lg me-1"></i>Cancelar compra
        </button>
        <?php endif; ?>
        <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
        <a href="<?= url('compras/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
                <span><i class="bi bi-receipt-cutoff"></i> Documento</span>
                <?= badge_status($compra['status']) ?>
            </div>
            <div class="card-body-custom">
                <div class="row">
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Número</small><b><?= e($compra['numero']) ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Data</small><b><?= formatar_datahora($compra['data_compra']) ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Fornecedor</small><b><?= e($fornecedor['razao_social'] ?? '-') ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Criado por</small><b><?= e($criador['nome'] ?? '-') ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Tipo</small><b><?= e($tipoPedido['nome'] ?? '-') ?></b></div>
                    <?php if ($compra['motivo_cancelamento']): ?>
                    <div class="col-12 mt-2">
                        <small class="text-muted d-block">Cancelada em <?= formatar_datahora($compra['cancelado_em']) ?> por <?= e($cancelador['nome'] ?? '-') ?></small>
                        <span class="badge bg-danger-subtle text-danger"><?= e($compra['motivo_cancelamento']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($compra['observacao']): ?>
                    <div class="col-12 mt-2"><small class="text-muted d-block">Observação</small><?= nl2br(e($compra['observacao'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header-custom"><i class="bi bi-box-seam"></i>Itens</div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Produto</th><th class="text-center">Qtd.</th><th class="text-end">Custo</th><th class="text-end">Desc.</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $i): ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><?= thumb_foto($i['foto']) ?><div><b><?= e($i['produto_desc']) ?></b> <small class="text-muted">(<?= e($i['codigo']) ?>)</small></div></div></td>
                                <td class="text-center"><?= formatar_qtde($i['quantidade']) ?> <?= e($i['unidade'] ?? '') ?></td>
                                <td class="text-end"><?= formatar_moeda($i['custo_unitario']) ?></td>
                                <td class="text-end"><?= formatar_moeda($i['desconto']) ?></td>
                                <td class="text-end fw-semibold"><?= formatar_moeda($i['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= formatar_moeda($compra['subtotal']) ?></td></tr>
                            <tr><td colspan="4" class="text-end">Desconto</td><td class="text-end text-danger">- <?= formatar_moeda($compra['desconto']) ?></td></tr>
                            <tr><td colspan="4" class="text-end">Acréscimo</td><td class="text-end text-success">+ <?= formatar_moeda($compra['acrescimo']) ?></td></tr>
                            <tr class="fw-bold"><td colspan="4" class="text-end">TOTAL</td><td class="text-end text-success fs-5"><?= formatar_moeda($compra['total']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <?php if ($geraFinanceiro): ?>
        <div class="card mb-3">
            <div class="card-header-custom"><i class="bi bi-cash-coin"></i>Pagamentos</div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($pagamentos as $pg): ?>
                            <tr>
                                <td><?= e($pg['forma']) ?><?= $pg['qtde_parcelas'] > 1 ? ' <span class="badge bg-info-subtle text-info-emphasis">' . (int)$pg['qtde_parcelas'] . 'x</span>' : '' ?></td>
                                <td class="text-end fw-semibold"><?= formatar_moeda($pg['valor']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3 print-hide">
            <div class="card-header-custom"><i class="bi bi-cash-stack"></i>Contas a pagar (parcelas)</div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Parc.</th><th>Vencimento</th><th class="text-end">Valor</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas as $cr): ?>
                            <tr>
                                <td><?= e($cr['parcela_numero'] ?? '-') ?></td>
                                <td><?= formatar_data($cr['vencimento']) ?></td>
                                <td class="text-end"><?= formatar_moeda($cr['valor']) ?></td>
                                <td><?= badge_status($cr['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="d-none d-print-block mt-4 text-center small text-muted">
    Emitido em <?= formatar_datahora($compra['criado_em']) ?>
</div>
<?php include INC . 'footer.php'; ?>