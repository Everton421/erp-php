<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('vendas_ver');

$filtroStatus = $_GET['status'] ?? '';
$filtroDataIni = parse_data($_GET['de'] ?? '');
$filtroDataFim = parse_data($_GET['ate'] ?? '');
$filtroTipo = (int)($_GET['tipo_pedido_id'] ?? 0);

$tiposPedido = db()->prepare('SELECT id, nome FROM tipos_pedido WHERE modulo = ? AND ativo = 1 ORDER BY nome');
$tiposPedido->execute(['VENDA']);
$tiposPedido = $tiposPedido->fetchAll();

$sql = "SELECT v.*, c.nome AS cliente_nome, u.nome AS vendedor_nome,
               tp.nome AS tipo_nome,
               (SELECT COUNT(*) FROM venda_itens vi WHERE vi.venda_id = v.id) AS qtd_itens,
               (SELECT COUNT(*) FROM venda_pagamentos vp WHERE vp.venda_id = v.id) AS qtd_pgto
          FROM vendas v
          LEFT JOIN clientes c ON c.id = v.cliente_id
          LEFT JOIN usuarios u ON u.id = v.vendedor_id
          LEFT JOIN tipos_pedido tp ON tp.id = v.tipo_pedido_id
         WHERE 1=1";
$params = [];
if ($filtroStatus !== '') {
    $sql .= ' AND v.status = ?';
    $params[] = $filtroStatus;
}
if ($filtroTipo > 0) {
    $sql .= ' AND v.tipo_pedido_id = ?';
    $params[] = $filtroTipo;
}
if ($filtroDataIni) {
    $sql .= ' AND DATE(v.data_venda) >= ?';
    $params[] = $filtroDataIni;
}
if ($filtroDataFim) {
    $sql .= ' AND DATE(v.data_venda) <= ?';
    $params[] = $filtroDataFim;
}
$sql .= ' ORDER BY v.data_venda DESC, v.id DESC LIMIT 2000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

$tituloPagina = 'Vendas';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-cart-check me-2"></i>Vendas</h1>
        <span class="subtitulo"><?= count($vendas) ?> venda(s) encontrada(s)</span>
    </div>
    <?php if (tem_permissao('vendas_criar')): ?>
    <a href="<?= url('vendas/nova.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova venda</a>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">Todos</option>
                    <option value="FINALIZADA" <?= $filtroStatus === 'FINALIZADA' ? 'selected' : '' ?>>Finalizada</option>
                    <option value="ORCAMENTO" <?= $filtroStatus === 'ORCAMENTO' ? 'selected' : '' ?>>Orçamento</option>
                    <option value="CANCELADA" <?= $filtroStatus === 'CANCELADA' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Tipo</label>
                <select class="form-select form-select-sm" name="tipo_pedido_id">
                    <option value="0" <?= $filtroTipo === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($tiposPedido as $tp): ?>
                    <option value="<?= (int)$tp['id'] ?>" <?= $filtroTipo === (int)$tp['id'] ? 'selected' : '' ?>><?= e($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">De</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= e($filtroDataIni ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($filtroDataFim ?? '') ?>">
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-sm btn-soft w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="0" data-ordem-dir="desc">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nº</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th class="text-end">Itens</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendas as $v): ?>
                    <tr>
                        <td><?= formatar_datahora($v['data_venda']) ?></td>
                        <td class="fw-semibold"><?= e($v['numero']) ?></td>
                        <td><?= e($v['tipo_nome'] ?? '-') ?></td>
                        <td><?= e($v['cliente_nome'] ?? 'Consumidor final') ?></td>
                        <td><?= e($v['vendedor_nome'] ?? '-') ?></td>
                        <td class="text-end"><?= (int)$v['qtd_itens'] ?></td>
                        <td class="text-end fw-semibold"><?= formatar_moeda($v['total']) ?></td>
                        <td><?= badge_status($v['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= url('vendas/ver.php?id=' . (int)$v['id']) ?>" class="btn btn-sm btn-soft btn-icone" title="Ver venda"><i class="bi bi-eye"></i></a>
                            <?php if (tem_permissao('vendas_cancelar') && $v['status'] === 'FINALIZADA'): ?>
                            <button class="btn btn-sm btn-soft-danger btn-icone js-confirmar"
                                    data-titulo="Cancelar venda <?= e($v['numero']) ?>?"
                                    data-texto="O estoque será devolvido e a venda marcada como cancelada."
                                    data-url="<?= url('vendas/cancelar.php') ?>"
                                    data-post='<?= e(json_encode(['id' => (int)$v['id']])) ?>'
                                    title="Cancelar venda"><i class="bi bi-x-lg"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include INC . 'footer.php'; ?>