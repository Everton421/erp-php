<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('compras_ver');

$filtroStatus = $_GET['status'] ?? '';
$filtroDataIni = parse_data($_GET['de'] ?? '');
$filtroDataFim = parse_data($_GET['ate'] ?? '');
$filtroTipo = (int)($_GET['tipo_pedido_id'] ?? 0);

$tiposPedido = db()->prepare('SELECT id, nome FROM tipos_pedido WHERE modulo = ? AND ativo = 1 ORDER BY nome');
$tiposPedido->execute(['COMPRA']);
$tiposPedido = $tiposPedido->fetchAll();

$sql = "SELECT cp.*, f.razao_social AS fornecedor_nome, u.nome AS criado_nome,
               tp.nome AS tipo_nome
          FROM compras cp
          LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id
          LEFT JOIN usuarios u ON u.id = cp.criado_por
          LEFT JOIN tipos_pedido tp ON tp.id = cp.tipo_pedido_id
         WHERE 1=1";
$params = [];
if ($filtroStatus !== '') {
    $sql .= ' AND cp.status = ?';
    $params[] = $filtroStatus;
}
if ($filtroTipo > 0) {
    $sql .= ' AND cp.tipo_pedido_id = ?';
    $params[] = $filtroTipo;
}
if ($filtroDataIni) {
    $sql .= ' AND DATE(cp.data_compra) >= ?';
    $params[] = $filtroDataIni;
}
if ($filtroDataFim) {
    $sql .= ' AND DATE(cp.data_compra) <= ?';
    $params[] = $filtroDataFim;
}
$sql .= ' ORDER BY cp.data_compra DESC, cp.id DESC LIMIT 2000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$compras = $stmt->fetchAll();

$tituloPagina = 'Compras';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-basket me-2"></i>Compras</h1>
        <span class="subtitulo"><?= count($compras) ?> compra(s) encontrada(s)</span>
    </div>
    <?php if (tem_permissao('compras_criar')): ?>
    <a href="<?= url('compras/nova.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova compra</a>
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
                        <th>Fornecedor</th>
                        <th>Usuário</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $c): ?>
                    <tr>
                        <td><?= formatar_datahora($c['data_compra']) ?></td>
                        <td class="fw-semibold"><?= e($c['numero']) ?></td>
                        <td><?= e($c['tipo_nome'] ?? '-') ?></td>
                        <td><?= e($c['fornecedor_nome'] ?? '-') ?></td>
                        <td><?= e($c['criado_nome'] ?? '-') ?></td>
                        <td class="text-end fw-semibold"><?= formatar_moeda($c['total']) ?></td>
                        <td><?= badge_status($c['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= url('compras/ver.php?id=' . (int)$c['id']) ?>" class="btn btn-sm btn-soft btn-icone" title="Ver compra"><i class="bi bi-eye"></i></a>
                            <?php if (tem_permissao('compras_cancelar') && $c['status'] === 'FINALIZADA'): ?>
                            <button class="btn btn-sm btn-soft-danger btn-icone js-confirmar"
                                    data-titulo="Cancelar compra <?= e($c['numero']) ?>?"
                                    data-texto="O estoque será baixado e o lançamento estornado."
                                    data-url="<?= url('compras/cancelar.php') ?>"
                                    data-post='<?= e(json_encode(['id' => (int)$c['id']])) ?>'
                                    title="Cancelar compra"><i class="bi bi-x-lg"></i></button>
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