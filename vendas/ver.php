<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('vendas_ver');

$id = (int)($_GET['id'] ?? 0);
$venda = buscar_linha('vendas', $id);
if (!$venda) {
    flash('danger', 'Venda não encontrada.');
    redirecionar('vendas/index.php');
}

$cliente = $venda['cliente_id'] ? buscar_linha('clientes', (int)$venda['cliente_id']) : null;
$vendedor = $venda['vendedor_id'] ? buscar_linha('usuarios', (int)$venda['vendedor_id']) : null;
$criador = buscar_linha('usuarios', (int)$venda['criado_por']);
$cancelador = $venda['cancelado_por'] ? buscar_linha('usuarios', (int)$venda['cancelado_por']) : null;
$tipoPedido = $venda['tipo_pedido_id'] ? buscar_linha('tipos_pedido', (int)$venda['tipo_pedido_id']) : null;
$geraFinanceiro = $tipoPedido ? (int)$tipoPedido['gera_financeiro'] === 1 : true;

$stmt = db()->prepare(
    'SELECT vi.*, p.descricao AS produto_desc, p.codigo, p.foto, p.unidade_id, u.sigla AS unidade
       FROM venda_itens vi
       JOIN produtos p ON p.id = vi.produto_id
       LEFT JOIN unidades u ON u.id = p.unidade_id
      WHERE vi.venda_id = ?
      ORDER BY vi.id'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT vp.*, fp.nome AS forma
       FROM venda_pagamentos vp
       JOIN formas_pagamento fp ON fp.id = vp.forma_pagamento_id
      WHERE vp.venda_id = ? ORDER BY vp.id'
);
$stmt->execute([$id]);
$pagamentos = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM contas_receber WHERE venda_id = ? ORDER BY vencimento, id');
$stmt->execute([$id]);
$contas = $stmt->fetchAll();

$empresa = [
    'nome' => obter_config('empresa_nome', 'Minha Empresa'),
    'cnpj' => obter_config('empresa_cnpj', ''),
    'endereco' => obter_config('empresa_endereco', ''),
    'telefone' => obter_config('empresa_telefone', ''),
    'email' => obter_config('empresa_email', ''),
];

$tituloPagina = ($venda['status'] === 'ORCAMENTO' ? 'Orçamento ' : 'Venda ') . $venda['numero'];
include INC . 'header.php';
?>
<div class="page-header print-hide">
    <div>
        <h1>
            <i class="bi <?= $venda['status'] === 'ORCAMENTO' ? 'bi-file-earmark-text' : 'bi-receipt' ?> me-2"></i>
            <?= $venda['status'] === 'ORCAMENTO' ? 'Orçamento ' : 'Venda ' ?><?= e($venda['numero']) ?>
        </h1>
        <span class="subtitulo"><?= $venda['status'] === 'ORCAMENTO' ? 'Proposta comercial / Cotação' : 'Detalhes e pagamentos' ?></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($venda['status'] === 'ORCAMENTO' && tem_permissao('vendas_criar')): ?>
        <button class="btn btn-success js-confirmar"
                data-titulo="Converter orçamento <?= e($venda['numero']) ?> em venda?"
                data-texto="O estoque dos produtos será decrementado e os pagamentos/parcelas serão lançados no contas a receber."
                data-url="<?= url('vendas/converter.php') ?>"
                data-post='<?= e(json_encode(['id' => (int)$venda['id']])) ?>'>
            <i class="bi bi-check2-circle me-1"></i>Aprovar e Converter em Venda
        </button>
        <?php endif; ?>

        <?php if (tem_permissao('vendas_cancelar') && $venda['status'] === 'FINALIZADA'): ?>
        <button class="btn btn-outline-danger js-confirmar"
                data-titulo="Cancelar venda <?= e($venda['numero']) ?>?"
                data-texto="O estoque será devolvido e os pagamentos estornados."
                data-url="<?= url('vendas/cancelar.php') ?>"
                data-post='<?= e(json_encode(['id' => (int)$venda['id']])) ?>'>
            <i class="bi bi-x-lg me-1"></i>Cancelar venda
        </button>
        <?php endif; ?>

        <a href="<?= url('vendas/cupom.php?id=' . (int)$venda['id'] . '&auto=1') ?>" target="_blank" class="btn btn-soft">
            <i class="bi bi-receipt me-1"></i>Cupom Térmico (80mm)
        </a>
        <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir A4</button>
        <a href="<?= url('vendas/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
                <span><i class="bi bi-receipt-cutoff"></i> Documento</span>
                <?= badge_status($venda['status']) ?>
            </div>
            <div class="card-body-custom">
                <div class="row">
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Número</small><b><?= e($venda['numero']) ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Data</small><b><?= formatar_datahora($venda['data_venda']) ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Cliente</small><b><?= e($cliente['nome'] ?? 'Consumidor final') ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Vendedor</small><b><?= e($vendedor['nome'] ?? '-') ?></b></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">Tipo</small><b><?= e($tipoPedido['nome'] ?? '-') ?></b></div>
                    <?php if ($venda['motivo_cancelamento']): ?>
                    <div class="col-12 mt-2">
                        <small class="text-muted d-block">Cancelada em <?= formatar_datahora($venda['cancelado_em']) ?> por <?= e($cancelador['nome'] ?? '-') ?></small>
                        <span class="badge bg-danger-subtle text-danger"><?= e($venda['motivo_cancelamento']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($venda['observacao']): ?>
                    <div class="col-12 mt-2"><small class="text-muted d-block">Observação</small><?= nl2br(e($venda['observacao'])) ?></div>
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
                            <tr><th>Produto</th><th class="text-center">Qtd.</th><th class="text-end">Preço</th><th class="text-end">Desc.</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $i): ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><?= thumb_foto($i['foto']) ?><div><b><?= e($i['produto_desc']) ?></b> <small class="text-muted">(<?= e($i['codigo']) ?>)</small></div></div></td>
                                <td class="text-center"><?= formatar_qtde($i['quantidade']) ?> <?= e($i['unidade'] ?? '') ?></td>
                                <td class="text-end"><?= formatar_moeda($i['preco_unitario']) ?></td>
                                <td class="text-end"><?= formatar_moeda($i['desconto']) ?></td>
                                <td class="text-end fw-semibold"><?= formatar_moeda($i['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= formatar_moeda($venda['subtotal']) ?></td></tr>
                            <tr><td colspan="4" class="text-end">Desconto</td><td class="text-end text-danger">- <?= formatar_moeda($venda['desconto']) ?></td></tr>
                            <tr><td colspan="4" class="text-end">Acréscimo</td><td class="text-end text-success">+ <?= formatar_moeda($venda['acrescimo']) ?></td></tr>
                            <tr class="fw-bold"><td colspan="4" class="text-end">TOTAL</td><td class="text-end text-success fs-5"><?= formatar_moeda($venda['total']) ?></td></tr>
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
            <div class="card-header-custom"><i class="bi bi-cash-stack"></i>Contas a receber (parcelas)</div>
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
    Emitido por <?= e($criador['nome'] ?? '') ?> em <?= formatar_datahora($venda['criado_em']) ?> • <?= e($empresa['nome']) ?>
</div>
<?php include INC . 'footer.php'; ?>