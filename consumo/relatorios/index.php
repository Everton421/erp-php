<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('consumo_relatorios');

$pdo = db();

$de = (string)($_GET['de'] ?? date('Y-m-01'));
$ate = (string)($_GET['ate'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $de = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $ate = date('Y-m-d');
}
if ($de > $ate) {
    [$de, $ate] = [$ate, $de];
}
$aberto = (string)($_GET['status'] ?? 'ABERTA');
$turno = (string)($_GET['turno'] ?? 'TODOS');

/* Condições de filtro compartilhadas */
$condComanda = 'c.data_abertura BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
$paramsData = [$de . ' 00:00:00', $ate];

$condTurno = '';
$paramsTurno = [];
if ($turno === 'ALMOCO') {
    $condTurno = ' AND HOUR(c.data_abertura) BETWEEN 11 AND 14';
} elseif ($turno === 'JANTAR') {
    $condTurno = ' AND (HOUR(c.data_abertura) BETWEEN 18 AND 23 OR HOUR(c.data_abertura) BETWEEN 0 AND 1)';
} elseif ($turno === 'MADRUGADA') {
    $condTurno = ' AND HOUR(c.data_abertura) BETWEEN 0 AND 5';
}

/* ================= Resumo ================= */
$sql = "SELECT COUNT(*) AS qtd,
               COALESCE(SUM(c.total), 0) AS total,
               COALESCE(SUM(c.desconto), 0) AS desconto,
               COALESCE(SUM(c.acrescimo), 0) AS acrescimo,
               COALESCE(SUM(c.valor_pago), 0) AS pago,
               COALESCE(SUM(GREATEST(c.total - c.valor_pago, 0)), 0) AS saldo
          FROM comandas c
         WHERE c.status <> 'CANCELADA' AND $condComanda $condTurno";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($paramsData, $paramsTurno));
$resumo = $stmt->fetch() ?: ['qtd' => 0, 'total' => 0.0, 'desconto' => 0.0, 'acrescimo' => 0.0, 'pago' => 0.0, 'saldo' => 0.0];

$qtd = (int)$resumo['qtd'];
$total = (float)$resumo['total'];
$ticket = $qtd > 0 ? $total / $qtd : 0.0;

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM comandas c
      WHERE c.status = 'CANCELADA' AND $condComanda $condTurno"
);
$stmt->execute(array_merge($paramsData, $paramsTurno));
$canceladas = (int)$stmt->fetchColumn();

/* ================= Faturamento por dia ================= */
$sql = "SELECT DATE(c.data_abertura) AS dia, COUNT(*) AS qtd, COALESCE(SUM(c.total), 0) AS total
          FROM comandas c
         WHERE c.status <> 'CANCELADA' AND $condComanda $condTurno
         GROUP BY DATE(c.data_abertura)
         ORDER BY dia";
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsData);
$porDia = $stmt->fetchAll();

$labelsDia = [];
$dadosQtd = [];
$dadosTotal = [];
foreach ($porDia as $l) {
    $labelsDia[] = date('d/m', strtotime((string)$l['dia']));
    $dadosQtd[] = (int)$l['qtd'];
    $dadosTotal[] = (float)$l['total'];
}

/* ================= Itens mais vendidos ================= */
$sql = "SELECT i.descricao, SUM(i.quantidade) AS qtd, SUM(i.total) AS total
          FROM comanda_itens i
          JOIN comandas c ON c.id = i.comanda_id
         WHERE c.status <> 'CANCELADA' AND i.status <> 'CANCELADO'
           AND $condComanda $condTurno
         GROUP BY i.descricao
         ORDER BY qtd DESC
         LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsData);
$itensVendidos = $stmt->fetchAll();

/* ================= Por categoria ================= */
$sql = "SELECT COALESCE(cat.nome, pcat.nome, 'SEM CATEGORIA') AS categoria,
               SUM(i.quantidade) AS qtd, SUM(i.total) AS total
          FROM comanda_itens i
          JOIN comandas c ON c.id = i.comanda_id
          LEFT JOIN produtos p ON p.id = i.produto_id
          LEFT JOIN categorias pcat ON pcat.id = p.categoria_id
          LEFT JOIN cardapio_itens ci ON ci.id = i.cardapio_item_id
          LEFT JOIN cardapio_categorias cat ON cat.id = ci.categoria_id
         WHERE c.status <> 'CANCELADA' AND i.status <> 'CANCELADO'
           AND $condComanda $condTurno
         GROUP BY categoria
         ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsData);
$porCategoria = $stmt->fetchAll();

/* ================= Formas de pagamento ================= */
$sql = "SELECT fp.nome, COALESCE(SUM(pg.valor), 0) AS total, COUNT(*) AS qtd
          FROM comanda_pagamentos pg
          JOIN comandas c ON c.id = pg.comanda_id
          JOIN formas_pagamento fp ON fp.id = pg.forma_pagamento_id
         WHERE c.status <> 'CANCELADA'
           AND $condComanda $condTurno
         GROUP BY fp.nome
         ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsData);
$porForma = $stmt->fetchAll();

/* ================= Garçons ================= */
$sql = "SELECT u.nome, COUNT(*) AS qtd, COALESCE(SUM(c.total), 0) AS total,
               COALESCE(SUM(c.desconto), 0) AS desconto
          FROM comandas c
          JOIN usuarios u ON u.id = c.garcom_id
         WHERE c.status <> 'CANCELADA' AND $condComanda $condTurno
         GROUP BY u.id, u.nome
         ORDER BY total DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsData);
$porGarcom = $stmt->fetchAll();

/* ================= Desempenho da fila de preparo ================= */
$stmt = $pdo->prepare(
    "SELECT i.status, COUNT(*) AS qtd,
            COALESCE(AVG(TIMESTAMPDIFF(MINUTE, i.data_pedido, i.data_atualizacao)), 0) AS media_min
       FROM comanda_itens i
       JOIN comandas c ON c.id = i.comanda_id
      WHERE c.status <> 'CANCELADA' AND $condComanda $condTurno
      GROUP BY i.status"
);
$stmt->execute($paramsData);
$porStatusItem = [];
foreach ($stmt->fetchAll() as $l) {
    $porStatusItem[$l['status']] = $l;
}

$stmt = $pdo->prepare(
    "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, i.data_pedido, i.data_atualizacao)), 0) AS media
       FROM comanda_itens i
       JOIN comandas c ON c.id = i.comanda_id
      WHERE c.status <> 'CANCELADA' AND i.status IN ('PRONTO', 'ENTREGUE')
        AND $condComanda $condTurno"
);
$stmt->execute($paramsData);
$tempoMedio = (float)$stmt->fetchColumn();

$tituloPagina = 'Relatórios do ' . rotulo_consumo();
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-graph-up me-2"></i>Relatórios do <?= e(rotulo_consumo()) ?></h1>
        <span class="subtitulo">
            <?= formatar_data($de) ?> a <?= formatar_data($ate) ?>
            &middot; <?= $qtd ?> comanda(s) &middot; ticket médio <?= formatar_moeda($ticket) ?>
        </span>
    </div>
    <a href="<?= url('consumo/index.php') ?>" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i>Painel
    </a>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label" for="de">De</label>
                <input type="date" class="form-control" id="de" name="de" value="<?= e($de) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="ate">Até</label>
                <input type="date" class="form-control" id="ate" name="ate" value="<?= e($ate) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="turno">Turno</label>
                <select class="form-select" id="turno" name="turno">
                    <option value="TODOS" <?= $turno === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                    <option value="MADRUGADA" <?= $turno === 'MADRUGADA' ? 'selected' : '' ?>>Madrugada (0h-5h)</option>
                    <option value="ALMOCO" <?= $turno === 'ALMOCO' ? 'selected' : '' ?>>Almoço (11h-14h)</option>
                    <option value="JANTAR" <?= $turno === 'JANTAR' ? 'selected' : '' ?>>Jantar (18h-1h)</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Aplicar</button>
                <a href="<?= url('consumo/relatorios/index.php') ?>" class="btn btn-light">Limpar</a>
            </div>
        </form>
        <div class="btn-group btn-group-sm mt-3">
            <a href="<?= url('consumo/relatorios/index.php?de=' . date('Y-m-d') . '&ate=' . date('Y-m-d')) ?>"
               class="btn btn-light">Hoje</a>
            <a href="<?= url('consumo/relatorios/index.php?de=' . date('Y-m-d', strtotime('-6 days')) . '&ate=' . date('Y-m-d')) ?>"
               class="btn btn-light">7 dias</a>
            <a href="<?= url('consumo/relatorios/index.php?de=' . date('Y-m-01') . '&ate=' . date('Y-m-d')) ?>"
               class="btn btn-light">Mês</a>
            <a href="<?= url('consumo/relatorios/index.php?de=' . date('Y-m-01', strtotime('-1 month')) . '&ate=' . date('Y-m-t', strtotime('-1 month'))) ?>"
               class="btn btn-light">Mês anterior</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-indigo h-100">
            <div class="stat-label"><i class="bi bi-cash-coin me-1"></i>Faturamento</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($total) ?></div>
            <div class="stat-extra"><?= $qtd ?> comanda(s)</div>
            <i class="bi bi-cash-coin stat-ico"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-teal h-100">
            <div class="stat-label"><i class="bi bi-graph-up me-1"></i>Ticket médio</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($ticket) ?></div>
            <div class="stat-extra">por comanda</div>
            <i class="bi bi-graph-up stat-ico"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-blue h-100">
            <div class="stat-label"><i class="bi bi-percent me-1"></i>Descontos</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($resumo['desconto']) ?></div>
            <div class="stat-extra">
                acréscimos <?= formatar_moeda($resumo['acrescimo']) ?>
            </div>
            <i class="bi bi-percent stat-ico"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card bg-grad-red h-100">
            <div class="stat-label"><i class="bi bi-cash-stack me-1"></i>A receber</div>
            <div class="stat-valor mt-1"><?= formatar_moeda($resumo['saldo']) ?></div>
            <div class="stat-extra"><?= $canceladas ?> comanda(s) cancelada(s)</div>
            <i class="bi bi-cash-stack stat-ico"></i>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-bar-chart-line me-2"></i>Movimento por dia</div>
            <div class="card-body-custom">
                <div class="chart-periodo-wrap">
                    <canvas id="chartDia"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-pie-chart me-2"></i>Por categoria</div>
            <div class="card-body-custom text-center">
                <div class="chart-financeiro-wrap">
                    <canvas id="chartCategoria"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-list-ol me-2"></i>Itens mais vendidos</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-geral responsive nowrap mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th class="text-end">Qtd.</th>
                                <th class="text-end">Faturamento</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $pos = 0; foreach ($itensVendidos as $iv): $pos++; ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= $pos ?></td>
                            <td><?= e($iv['descricao']) ?></td>
                            <td class="text-end"><?= formatar_qtde($iv['qtd']) ?></td>
                            <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$iv['total'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-people me-2"></i>Desempenho dos atendentes</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-geral responsive nowrap mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Atendente</th>
                                <th class="text-end">Comandas</th>
                                <th class="text-end">Faturamento</th>
                                <th class="text-end">Ticket</th>
                                <th class="text-end">Descontos</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porGarcom as $g): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($g['nome']) ?></td>
                            <td class="text-end"><?= (int)$g['qtd'] ?></td>
                            <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$g['total'] ?></td>
                            <td class="text-end" data-moeda-exibir>
                                <?= (int)$g['qtd'] > 0 ? (float)$g['total'] / (int)$g['qtd'] : 0 ?>
                            </td>
                            <td class="text-end text-danger" data-moeda-exibir><?= (float)$g['desconto'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-credit-card me-2"></i>Formas de pagamento</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-geral responsive nowrap mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Forma</th>
                                <th class="text-end">Lançamentos</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Participação</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $somaFormas = 0.0;
                        foreach ($porForma as $f) {
                            $somaFormas += (float)$f['total'];
                        }
                        ?>
                        <?php foreach ($porForma as $f): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($f['nome']) ?></td>
                            <td class="text-end"><?= (int)$f['qtd'] ?></td>
                            <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$f['total'] ?></td>
                            <td class="text-end">
                                <?= $somaFormas > 0 ? formatar_numero(((float)$f['total'] / $somaFormas) * 100, 1) . '%' : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-stopwatch me-2"></i>Desempenho da <?= e(rotulo_producao()) ?></div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-4 text-center">
                        <div class="small text-muted">Tempo médio</div>
                        <div class="h5 mb-0"><?= formatar_numero($tempoMedio, 1) ?> min</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="small text-muted">Prontos</div>
                        <div class="h5 mb-0"><?= (int)($porStatusItem['PRONTO']['qtd'] ?? 0) ?></div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="small text-muted">Cancelados</div>
                        <div class="h5 mb-0"><?= (int)($porStatusItem['CANCELADO']['qtd'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Itens</th>
                                <th class="text-end">Tempo médio</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rotulo = [
                            'PENDENTE' => 'Aguardando',
                            'PREPARANDO' => 'Preparando',
                            'PRONTO' => 'Pronto',
                            'ENTREGUE' => 'Entregue',
                            'CANCELADO' => 'Cancelado',
                        ];
                        ?>
                        <?php foreach ($rotulo as $chave => $nome): ?>
                        <tr>
                            <td><?= badge_status($chave) ?> <span class="small text-muted"><?= $nome ?></span></td>
                            <td class="text-end"><?= (int)($porStatusItem[$chave]['qtd'] ?? 0) ?></td>
                            <td class="text-end">
                                <?= (int)($porStatusItem[$chave]['qtd'] ?? 0) > 0
                                    ? formatar_numero((float)$porStatusItem[$chave]['media_min'], 1) . ' min'
                                    : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        CONSUMO.grafico('chartDia', {
            labels: <?= json_encode($labelsDia, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Faturamento',
                data: <?= json_encode($dadosTotal) ?>,
                backgroundColor: 'rgba(0, 184, 148, .35)',
                borderColor: '#00b894',
                borderWidth: 2,
                borderRadius: 6
            }, {
                label: 'Comandas',
                data: <?= json_encode($dadosQtd) ?>,
                type: 'line',
                borderColor: '#4f6ef7',
                backgroundColor: 'rgba(79, 110, 247, .1)',
                borderWidth: 2,
                tension: .35,
                yAxisID: 'y1',
                pointRadius: 3
            }]
        }, {
            scales: {
                y: { beginAtZero: true, ticks: { callback: (v) => APP.fmtMoeda(v) } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } }
            }
        });

        const categorias = <?= json_encode(array_column($porCategoria, 'categoria'), JSON_UNESCAPED_UNICODE) ?>;
        const totais = <?= json_encode(array_map('floatval', array_column($porCategoria, 'total'))) ?>;
        if (categorias.length) {
            CONSUMO.grafico('chartCategoria', {
                labels: categorias,
                datasets: [{
                    data: totais,
                    backgroundColor: [
                        '#4f6ef7', '#00b894', '#f7a541', '#ff4d6d', '#0ea5e9',
                        '#a855f7', '#14b8a6', '#f97316', '#64748b', '#eab308'
                    ]
                }]
            }, { type: 'doughnut', scales: {}, plugins: { legend: { position: 'bottom' } } });
        }
    });
</script>
<?php include INC . 'footer.php'; ?>
