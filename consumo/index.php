<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('consumo_ver');

$pdo = db();

/* ================= Indicadores do dia ================= */
$hoje = (float)$pdo->query(
    "SELECT COALESCE(SUM(total), 0) FROM comandas
      WHERE status = 'FECHADA' AND DATE(data_fechamento) = CURDATE()"
)->fetchColumn();
$qtdComandasDia = (int)$pdo->query(
    "SELECT COUNT(*) FROM comandas
      WHERE status = 'FECHADA' AND DATE(data_fechamento) = CURDATE()"
)->fetchColumn();
$abertas = (int)$pdo->query("SELECT COUNT(*) FROM comandas WHERE status = 'ABERTA'")->fetchColumn();
$saldo = (float)$pdo->query(
    "SELECT COALESCE(SUM(GREATEST(total - valor_pago, 0)), 0) FROM comandas
      WHERE status = 'FECHADA' AND status_pagamento <> 'PAGO'"
)->fetchColumn();
$filaCozinha = (int)$pdo->query(
    "SELECT COUNT(*) FROM comanda_itens i
       JOIN comandas c ON c.id = i.comanda_id
      WHERE c.status = 'ABERTA' AND i.status IN ('PENDENTE', 'PREPARANDO')"
)->fetchColumn();
$atrasados = (int)$pdo->query(
    "SELECT COUNT(*) FROM comanda_itens i
       JOIN comandas c ON c.id = i.comanda_id
      WHERE c.status = 'ABERTA' AND i.status IN ('PENDENTE', 'PREPARANDO')
        AND TIMESTAMPDIFF(MINUTE, i.data_pedido, NOW()) > 15"
)->fetchColumn();
$ticket = $qtdComandasDia > 0 ? $hoje / $qtdComandasDia : 0.0;

$meses = (float)$pdo->query(
    "SELECT COALESCE(SUM(total), 0) FROM comandas
      WHERE status = 'FECHADA'
        AND data_fechamento >= DATE_FORMAT(CURDATE(), '%Y-%m-01 00:00:00')"
)->fetchColumn();
$mesQtd = (int)$pdo->query(
    "SELECT COUNT(*) FROM comandas
      WHERE status = 'FECHADA'
        AND data_fechamento >= DATE_FORMAT(CURDATE(), '%Y-%m-01 00:00:00')"
)->fetchColumn();

/* ================= Mesas ================= */
$mesas = $pdo->query(
    "SELECT m.*, c.id AS comanda_id, c.numero AS comanda, c.total, c.data_abertura, g.nome AS garcom
       FROM mesas m
       LEFT JOIN comandas c ON c.mesa_id = m.id AND c.status = 'ABERTA'
       LEFT JOIN usuarios g ON g.id = c.garcom_id
      WHERE m.ativo = 1
      ORDER BY m.numero"
)->fetchAll();

$contagem = ['LIVRE' => 0, 'OCUPADA' => 0, 'RESERVADA' => 0];
foreach ($mesas as $m) {
    if (isset($contagem[$m['status']])) {
        $contagem[$m['status']]++;
    }
}

/* ================= Fila da cozinha ================= */
$stmt = $pdo->query(
    "SELECT i.id, i.descricao, i.quantidade, i.status, i.data_pedido,
            c.numero AS comanda, m.numero AS mesa,
            GREATEST(TIMESTAMPDIFF(MINUTE, i.data_pedido, NOW()), 0) AS minutos
       FROM comanda_itens i
       JOIN comandas c ON c.id = i.comanda_id
       JOIN mesas m ON m.id = c.mesa_id
      WHERE c.status = 'ABERTA' AND i.status IN ('PENDENTE', 'PREPARANDO')
      ORDER BY i.data_pedido
      LIMIT 12"
);
$fila = $stmt->fetchAll();

/* ================= Comandas recentes ================= */
$stmt = $pdo->query(
    "SELECT c.*, m.numero AS mesa_numero, u.nome AS garcom_nome
       FROM comandas c
       JOIN mesas m ON m.id = c.mesa_id
       JOIN usuarios u ON u.id = c.garcom_id
      WHERE DATE(c.data_abertura) = CURDATE()
      ORDER BY c.data_abertura DESC
      LIMIT 10"
);
$recentes = $stmt->fetchAll();

/* ================= Faturamento dos últimos 7 dias ================= */
$stmt = $pdo->query(
    "SELECT DATE(data_fechamento) AS dia, COUNT(*) AS qtd, COALESCE(SUM(total), 0) AS total
       FROM comandas
      WHERE status = 'FECHADA' AND data_fechamento >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(data_fechamento)
      ORDER BY dia"
);
$porDia = [];
foreach ($stmt->fetchAll() as $l) {
    $porDia[$l['dia']] = $l;
}
$graficoDias = ['labels' => [], 'qtd' => [], 'total' => []];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime('-' . $i . ' days'));
    $graficoDias['labels'][] = date('d/m', strtotime($dia));
    $graficoDias['qtd'][] = (int)($porDia[$dia]['qtd'] ?? 0);
    $graficoDias['total'][] = (float)($porDia[$dia]['total'] ?? 0);
}

/* ================= Itens mais vendidos (30 dias) ================= */
$stmt = $pdo->query(
    "SELECT i.descricao, SUM(i.quantidade) AS qtd, SUM(i.total) AS total
       FROM comanda_itens i
       JOIN comandas c ON c.id = i.comanda_id
      WHERE c.status = 'FECHADA'
        AND i.status <> 'CANCELADO'
        AND c.data_fechamento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      GROUP BY i.descricao
      ORDER BY qtd DESC
      LIMIT 8"
);
$maisVendidos = $stmt->fetchAll();

/* ================= Formas de pagamento (hoje) ================= */
$stmt = $pdo->query(
    "SELECT fp.nome, COALESCE(SUM(pg.valor), 0) AS total
       FROM comanda_pagamentos pg
       JOIN comandas c ON c.id = pg.comanda_id
       JOIN formas_pagamento fp ON fp.id = pg.forma_pagamento_id
      WHERE c.status = 'FECHADA' AND DATE(pg.data_pagamento) = CURDATE()
      GROUP BY fp.nome
      ORDER BY total DESC"
);
$porForma = $stmt->fetchAll();

$tituloPagina = 'Painel do ' . rotulo_consumo();
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-shop-window me-2"></i>Painel do <?= e(rotulo_consumo()) ?></h1>
        <span class="subtitulo">
            Operação de <?= date('d/m/Y') ?> &middot;
            <span data-relogio class="text-muted"><?= date('H:i') ?></span>
        </span>
    </div>
    <div class="d-flex gap-2">
        <?php if (tem_permissao('mesas_ver')): ?>
        <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-soft">
            <i class="bi bi-grid-3x3-gap me-1"></i>Mesas
        </a>
        <?php endif; ?>
        <?php if (tem_permissao('comandas_criar')): ?>
        <a href="<?= url('consumo/comandas/nova.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Abrir comanda <kbd class="bg-white text-dark ms-1 d-none d-lg-inline-block"
            style="font-size:0.65rem">F3</kbd>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($atrasados > 0 && tem_permissao('cozinha_ver')): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <b><?= $atrasados ?> item(ns) passaram de 15 minutos na fila.</b>
    <a href="<?= url('consumo/cozinha/index.php') ?>" class="btn btn-sm btn-danger ms-auto">Ver na <?= e(rotulo_producao()) ?></a>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-indigo h-100">
            <div class="stat-label"><i class="bi bi-cash-coin me-1"></i>Faturamento hoje</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($hoje) ?></div>
            <div class="stat-extra"><?= $qtdComandasDia ?> comanda(s) finalizada(s)</div>
            <i class="bi bi-cash-coin stat-ico"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-teal h-100">
            <div class="stat-label"><i class="bi bi-receipt me-1"></i>Ticket médio</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($ticket) ?></div>
            <div class="stat-extra">mês: <?= formatar_moeda($mesQtd > 0 ? $meses / $mesQtd : 0) ?> em <?= $mesQtd ?> comanda(s)</div>
            <i class="bi bi-receipt stat-ico"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-blue h-100">
            <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Comandas abertas</div>
            <div class="stat-valor mt-1"><?= $abertas ?></div>
            <div class="stat-extra"><?= $filaCozinha ?> item(ns) na fila</div>
            <i class="bi bi-hourglass-split stat-ico"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-red h-100">
            <div class="stat-label"><i class="bi bi-cash-stack me-1"></i>Saldo a receber</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($saldo) ?></div>
            <div class="stat-extra">
                <?= $saldo > 0 ? 'comandas fechadas em aberto' : 'nenhuma pendência no caixa' ?>
            </div>
            <i class="bi bi-cash-stack stat-ico"></i>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body-custom d-flex align-items-center gap-3">
                <i class="bi bi-door-open fs-2 text-success"></i>
                <div>
                    <div class="h4 mb-0"><?= $contagem['LIVRE'] ?></div>
                    <div class="small text-muted">Mesas livres</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body-custom d-flex align-items-center gap-3">
                <i class="bi bi-people fs-2 text-warning"></i>
                <div>
                    <div class="h4 mb-0"><?= $contagem['OCUPADA'] ?></div>
                    <div class="small text-muted">Mesas ocupadas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body-custom d-flex align-items-center gap-3">
                <i class="bi bi-bookmark fs-2 text-info"></i>
                <div>
                    <div class="h4 mb-0"><?= $contagem['RESERVADA'] ?></div>
                    <div class="small text-muted">Reservadas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body-custom d-flex align-items-center gap-3">
                <i class="bi bi-grid-3x3-gap fs-2 text-secondary"></i>
                <div>
                    <div class="h4 mb-0"><?= count($mesas) ?></div>
                    <div class="small text-muted">Mesas ativas</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php if (tem_permissao('mesas_ver')): ?>
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header-custom">
                <i class="bi bi-grid-3x3-gap me-2"></i>Atendimento
                <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-sm btn-soft ms-auto">Ver mesas</a>
            </div>
            <div class="card-body-custom">
                <div class="mesa-grid" data-mesa-grid data-intervalo="20">
                    <?php foreach ($mesas as $m): ?>
                    <div class="mesa-card <?= e($m['status']) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="mesa-numero"><?= (int)$m['numero'] ?></div>
                                <?php if ($m['nome']): ?>
                                <div class="mesa-nome"><?= e($m['nome']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?= badge_status((string)$m['status']) ?>
                        </div>
                        <?php if ($m['comanda_id']): ?>
                        <div class="mesa-meta">
                            <span><i class="bi bi-person me-1"></i><?= e($m['garcom']) ?></span>
                            <span><i class="bi bi-clock me-1"></i><?= formatar_tempo_decorrido((string)$m['data_abertura']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="mesa-rodape">
                            <span class="valor">
                                <?= $m['comanda_id'] ? formatar_moeda($m['total']) : '&nbsp;' ?>
                            </span>
                            <a href="<?= $m['comanda_id']
                                ? url('consumo/comandas/ver.php?id=' . (int)$m['comanda_id'])
                                : url('consumo/comandas/nova.php?mesa_id=' . (int)$m['id']) ?>"
                               class="btn btn-sm <?= $m['comanda_id'] ? 'btn-warning' : 'btn-primary' ?>">
                                <?= $m['comanda_id'] ? 'Abrir' : 'Comanda' ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-12 <?= tem_permissao('mesas_ver') ? 'col-xl-5' : 'col-xl-6' ?>">
        <div class="card h-100">
            <div class="card-header-custom">
                <i class="bi bi-fire me-2"></i>Fila de <?= e(rotulo_producao()) ?>
                <?php if (tem_permissao('cozinha_ver')): ?>
                <a href="<?= url('consumo/cozinha/index.php') ?>" class="btn btn-sm btn-soft ms-auto">Abrir <?= e(rotulo_producao()) ?></a>
                <?php endif; ?>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Mesa</th>
                                <th>Item</th>
                                <th class="text-center">Tempo</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$fila): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-check2-all d-block fs-3 mb-1"></i>
                                    Nenhum pedido na fila.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($fila as $f): ?>
                        <tr>
                            <td class="fw-semibold"><?= (int)$f['mesa'] ?></td>
                            <td>
                                <?= formatar_qtde($f['quantidade']) ?>x <?= e($f['descricao']) ?>
                                <small class="text-muted d-block">comanda <?= e($f['comanda']) ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= (int)$f['minutos'] > 15 ? 'danger' : 'light' ?> text-<?= (int)$f['minutos'] > 15 ? '' : 'dark' ?>">
                                    <?= (int)$f['minutos'] ?> min
                                </span>
                            </td>
                            <td class="text-end"><?= badge_status((string)$f['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-bar-chart-line me-2"></i>Faturamento dos últimos 7 dias</div>
            <div class="card-body-custom">
                <div class="chart-periodo-wrap">
                    <canvas id="chartDias"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-trophy me-2"></i>Mais vendidos (30 dias)</div>
            <div class="card-body-custom">
                <?php if (!$maisVendidos): ?>
                <p class="text-muted mb-0">
                    <i class="bi bi-inbox me-1"></i>Sem vendas finalizadas nos últimos 30 dias.
                </p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php $pos = 0; foreach ($maisVendidos as $mv): $pos++; ?>
                    <tr>
                        <td style="width:28px" class="text-muted fw-bold"><?= $pos ?>.</td>
                        <td><?= e($mv['descricao']) ?></td>
                        <td class="text-end text-muted"><?= formatar_qtde($mv['qtd']) ?></td>
                        <td class="text-end fw-semibold"><?= formatar_moeda($mv['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($recentes): ?>
<div class="card mt-3">
    <div class="card-header-custom">
        <i class="bi bi-clock-history me-2"></i>Comandas de hoje
        <?php if (tem_permissao('comandas_ver')): ?>
        <a href="<?= url('consumo/comandas/index.php') ?>" class="btn btn-sm btn-soft ms-auto">Ver todas</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral responsive nowrap mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Comanda</th>
                        <th>Mesa</th>
                        <th>Atendente</th>
                        <th>Abertura</th>
                        <th class="text-end">Total</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentes as $c): ?>
                <tr>
                    <td class="fw-semibold"><?= e($c['numero']) ?></td>
                    <td><?= (int)$c['mesa_numero'] ?></td>
                    <td><?= e($c['garcom_nome']) ?></td>
                    <td><?= formatar_datahora((string)$c['data_abertura']) ?></td>
                    <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$c['total'] ?></td>
                    <td><?= badge_status((string)$c['status_pagamento']) ?></td>
                    <td><?= badge_status((string)$c['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    $(function () {
        const dias = <?= json_encode($graficoDias, JSON_UNESCAPED_UNICODE) ?>;
        const el = document.getElementById('chartDias');
        if (!el || typeof Chart === 'undefined') return;

        new Chart(el, {
            data: {
                labels: dias.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Comandas',
                        data: dias.qtd,
                        backgroundColor: 'rgba(79, 110, 247, .25)',
                        borderColor: '#4f6ef7',
                        borderWidth: 2,
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Faturamento',
                        data: dias.total,
                        borderColor: '#00b894',
                        backgroundColor: 'rgba(0, 184, 148, .12)',
                        borderWidth: 2,
                        tension: .35,
                        fill: true,
                        pointRadius: 3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { callback: (v) => APP.fmtMoeda(v) }
                    }
                }
            }
        });
    });
</script>
<?php include INC . 'footer.php'; ?>
