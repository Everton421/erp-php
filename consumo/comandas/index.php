<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('comandas_ver');

$pdo = db();
$statusFiltro = (string)($_GET['status'] ?? 'ABERTA');
$periodo = (string)($_GET['periodo'] ?? 'hoje');
$busca = trim((string)($_GET['busca'] ?? ''));

$where = [];
$params = [];

if (in_array($statusFiltro, ['ABERTA', 'FECHADA', 'CANCELADA'], true)) {
    $where[] = 'c.status = ?';
    $params[] = $statusFiltro;
}
if ($periodo === 'hoje') {
    $where[] = 'DATE(c.data_abertura) = CURDATE()';
} elseif ($periodo === 'semana') {
    $where[] = 'c.data_abertura >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
} elseif ($periodo === 'mes') {
    $where[] = 'c.data_abertura >= DATE_FORMAT(CURDATE(), "%Y-%m-01 00:00:00")';
}
if ($busca !== '') {
    $where[] = '(c.numero LIKE ? OR m.numero = ? OR u.nome LIKE ?)';
    $params[] = '%' . $busca . '%';
    $params[] = (int)$busca;
    $params[] = '%' . $busca . '%';
}
if (!tem_permissao('comandas_ver_todas')) {
    $where[] = 'c.garcom_id = ?';
    $params[] = (int)(usuario_atual()['id'] ?? 0);
}

$sql = "SELECT c.*, m.numero AS mesa_numero, m.nome AS mesa_nome, u.nome AS garcom_nome,
               (SELECT COUNT(*) FROM comanda_itens i WHERE i.comanda_id = c.id) AS qtd_itens,
               (SELECT COUNT(*) FROM comanda_itens i WHERE i.comanda_id = c.id
                 AND i.status IN ('PENDENTE','PREPARANDO')) AS qtd_pendentes
          FROM comandas c
          JOIN mesas m ON m.id = c.mesa_id
          JOIN usuarios u ON u.id = c.garcom_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.data_abertura DESC, c.id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$comandas = $stmt->fetchAll();

$totais = ['quantidade' => 0, 'total' => 0.0, 'pago' => 0.0, 'saldo' => 0.0];
foreach ($comandas as $c) {
    $totais['quantidade']++;
    if ((string)$c['status'] === 'CANCELADA') {
        continue;
    }
    $totais['total'] += (float)$c['total'];
    $totais['pago'] += (float)$c['valor_pago'];
    $totais['saldo'] += max(0, (float)$c['total'] - (float)$c['valor_pago']);
}

$tituloPagina = 'Comandas';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt-cutoff me-2"></i>Comandas</h1>
        <span class="subtitulo">
            <?= $totais['quantidade'] ?> comanda(s) na listagem &middot;
            total <?= formatar_moeda($totais['total']) ?> &middot;
            pago <?= formatar_moeda($totais['pago']) ?>
            <?= $totais['saldo'] > 0 ? ' &middot; a receber ' . formatar_moeda($totais['saldo']) : '' ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-soft">
            <i class="bi bi-grid-3x3-gap me-1"></i>Mesas
        </a>
        <?php if (tem_permissao('comandas_criar')): ?>
        <a href="<?= url('consumo/comandas/nova.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Abrir comanda <kbd class="bg-white text-dark ms-1 d-none d-lg-inline-block"
            style="font-size:0.65rem">F3</kbd>
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="busca">Buscar comanda</label>
                <input type="text" class="form-control" id="busca" name="busca" value="<?= e($busca) ?>"
                       placeholder="Número, mesa ou atendente" maxlength="60">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="ABERTA" <?= $statusFiltro === 'ABERTA' ? 'selected' : '' ?>>Abertas</option>
                    <option value="FECHADA" <?= $statusFiltro === 'FECHADA' ? 'selected' : '' ?>>Fechadas</option>
                    <option value="CANCELADA" <?= $statusFiltro === 'CANCELADA' ? 'selected' : '' ?>>Canceladas</option>
                    <option value="TODAS" <?= $statusFiltro === 'TODAS' ? 'selected' : '' ?>>Todas</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="periodo">Período</label>
                <select class="form-select" id="periodo" name="periodo">
                    <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Últimos 7 dias</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Mês atual</option>
                    <option value="todos" <?= $periodo === 'todos' ? 'selected' : '' ?>>Todo o período</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="<?= url('consumo/comandas/index.php') ?>" class="btn btn-light">Limpar</a>
            </div>
        </form>
    </div>
</div>

<?php if (!$comandas): ?>
<div class="alert alert-info d-flex align-items-center gap-2">
    <i class="bi bi-info-circle"></i>
    Nenhuma comanda encontrada com os filtros aplicados.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Comanda</th>
                        <th>Mesa</th>
                        <th>Atendente</th>
                        <th>Abertura</th>
                        <th class="text-center">Itens</th>
                        <th class="text-end">Total</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comandas as $c): ?>
                    <?php $saldo = max(0, (float)$c['total'] - (float)$c['valor_pago']); ?>
                    <tr class="<?= (string)$c['status'] === 'CANCELADA' ? 'opacity-75' : '' ?>">
                        <td class="fw-semibold"><?= e($c['numero']) ?></td>
                        <td>
                            <?= (int)$c['mesa_numero'] ?>
                            <?php if ($c['mesa_nome']): ?>
                            <small class="text-muted d-block"><?= e($c['mesa_nome']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($c['garcom_nome']) ?></td>
                        <td>
                            <?= formatar_datahora((string)$c['data_abertura']) ?>
                            <?php if ((string)$c['status'] === 'ABERTA'): ?>
                            <small class="text-muted d-block">
                                <i class="bi bi-clock me-1"></i><?= formatar_tempo_decorrido((string)$c['data_abertura']) ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= (int)$c['qtd_itens'] ?>
                            <?php if ((int)$c['qtd_pendentes'] > 0): ?>
                            <span class="badge bg-warning"><?= (int)$c['qtd_pendentes'] ?> na fila</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$c['total'] ?></td>
                        <td>
                            <?= badge_status((string)$c['status_pagamento']) ?>
                            <?php if ($saldo > 0 && (string)$c['status'] !== 'CANCELADA'): ?>
                            <small class="text-danger d-block">saldo <?= formatar_moeda($saldo) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= badge_status((string)$c['status']) ?></td>
                        <td class="text-nowrap no-print">
                            <a href="<?= url('consumo/comandas/ver.php?id=' . (int)$c['id']) ?>"
                               class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Abrir comanda">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (tem_permissao('comandas_imprimir')): ?>
                            <a href="<?= url('consumo/comandas/cupom.php?id=' . (int)$c['id']) ?>" target="_blank"
                               class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Cupom 80mm">
                                <i class="bi bi-printer"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ((string)$c['status'] === 'FECHADA' && $saldo > 0 && tem_permissao('caixa_consumo_pagar')): ?>
                            <a href="<?= url('consumo/caixa/index.php?comanda=' . (int)$c['id']) ?>"
                               class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Registrar pagamento">
                                <i class="bi bi-cash-coin"></i>
                            </a>
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
