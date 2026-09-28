<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
if (!tem_permissao('caixa_ver') && !tem_permissao('relatorios_ver')) {
    exigir_permissao('caixa_ver');
}

$de = parse_data($_GET['de'] ?? '');
$ate = parse_data($_GET['ate'] ?? '');
$filtroTipo = $_GET['tipo'] ?? '';
$filtroCat = $_GET['categoria'] ?? '';

if (!$de) {
    $de = date('Y-m-01');
}
if (!$ate) {
    $ate = date('Y-m-t');
}

// Saldo inicial (tudo anterior ao período, sem estornos)
$stmt = db()->prepare(
    "SELECT COALESCE(SUM(CASE WHEN tipo = 'ENTRADA' THEN valor ELSE -valor END), 0) AS saldo
       FROM fluxo_caixa WHERE estorno = 0 AND data_movimento < ?"
);
$stmt->execute([$de . ' 00:00:00']);
$saldoInicial = (float)$stmt->fetchColumn();

$sql = 'SELECT * FROM fluxo_caixa WHERE estorno = 0 AND DATE(data_movimento) BETWEEN ? AND ?';
$params = [$de, $ate];
if ($filtroTipo !== '') {
    $sql .= ' AND tipo = ?';
    $params[] = $filtroTipo;
}
if ($filtroCat !== '') {
    $sql .= ' AND categoria = ?';
    $params[] = $filtroCat;
}
$sql .= ' ORDER BY data_movimento DESC, id DESC LIMIT 5000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$movimentos = $stmt->fetchAll();

$totalEntradas = $totalSaidas = 0.0;
foreach ($movimentos as $m) {
    if ($m['tipo'] === 'ENTRADA') {
        $totalEntradas += (float)$m['valor'];
    } else {
        $totalSaidas += (float)$m['valor'];
    }
}
$saldoFinal = $saldoInicial + $totalEntradas - $totalSaidas;

// Dados do gráfico por dia
$tags = [];
foreach ($movimentos as $m) {
    $dia = date('d/m', strtotime($m['data_movimento']));
    if (!isset($tags[$dia])) {
        $tags[$dia] = ['entrada' => 0.0, 'saida' => 0.0];
    }
    if ($m['tipo'] === 'ENTRADA') {
        $tags[$dia]['entrada'] += (float)$m['valor'];
    } else {
        $tags[$dia]['saida'] += (float)$m['valor'];
    }
}
krsort($tags);
$dias = array_slice(array_reverse(array_keys($tags)), 0, 31);
$entradasDia = array_map(fn($d) => $tags[$d]['entrada'], $dias);
$saidasDia = array_map(fn($d) => $tags[$d]['saida'], $dias);

$tituloPagina = 'Fluxo de caixa';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-graph-up-arrow me-2"></i>Fluxo de caixa</h1>
        <span class="subtitulo">Movimentações do período</span>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Saldo inicial</div><div class="stat-val"><?= formatar_moeda($saldoInicial) ?></div></div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Entradas</div><div class="stat-val text-success"><?= formatar_moeda($totalEntradas) ?></div></div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Saídas</div><div class="stat-val text-danger"><?= formatar_moeda($totalSaidas) ?></div></div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Saldo final</div><div class="stat-val <?= $saldoFinal >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatar_moeda($saldoFinal) ?></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">De</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= e($de) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($ate) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Tipo</label>
                <select class="form-select form-select-sm" name="tipo">
                    <option value="">Todos</option>
                    <option value="ENTRADA" <?= $filtroTipo === 'ENTRADA' ? 'selected' : '' ?>>Entrada</option>
                    <option value="SAIDA" <?= $filtroTipo === 'SAIDA' ? 'selected' : '' ?>>Saída</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Categoria</label>
                <select class="form-select form-select-sm" name="categoria">
                    <option value="">Todas</option>
                    <?php foreach (['VENDA' => 'Venda', 'RECEBIMENTO' => 'Recebimento', 'COMPRA' => 'Compra', 'CONTA' => 'Conta', 'DESPESA' => 'Despesa', 'OUTRO' => 'Outro'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filtroCat === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-sm btn-soft w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header-custom"><i class="bi bi-bar-chart"></i>Variação diária</div>
    <div class="card-body-custom">
        <canvas id="graficoFluxo" height="90"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="0" data-ordem-dir="desc">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Descrição</th>
                        <th class="text-end">Valor</th>
                        <th>Usuário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimentos as $m): ?>
                    <tr>
                        <td><?= formatar_datahora($m['data_movimento']) ?></td>
                        <td>
                            <?php if ($m['tipo'] === 'ENTRADA'): ?>
                            <span class="badge bg-success">Entrada</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Saída</span>
                            <?php endif; ?>
                            <?php if ($m['estorno']): ?><span class="badge bg-secondary ms-1">estorno</span><?php endif; ?>
                        </td>
                        <td><?= e($m['categoria']) ?></td>
                        <td><?= e($m['descricao']) ?> <small class="text-muted d-block"><?= e($m['referencia'] ?? '') ?></small></td>
                        <td class="text-end fw-semibold <?= $m['tipo'] === 'ENTRADA' ? 'text-success' : 'text-danger' ?>">
                            <?= ($m['tipo'] === 'ENTRADA' ? '+' : '-') . formatar_moeda($m['valor']) ?>
                        </td>
                        <td><?= e(buscar_linha('usuarios', (int)$m['usuario_id'])['nome'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$movimentos): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Sem movimentações no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(function () {
        const dias = <?= json_encode($dias) ?>;
        const entradas = <?= json_encode($entradasDia) ?>;
        const saidas = <?= json_encode($saidasDia) ?>;
        if (dias.length && window.Chart) {
            new Chart(document.getElementById('graficoFluxo'), {
                type: 'bar',
                data: {
                    labels: dias,
                    datasets: [
                        { label: 'Entradas', data: entradas, backgroundColor: 'rgba(25,135,84,.75)', borderRadius: 4 },
                        { label: 'Saídas', data: saidas, backgroundColor: 'rgba(220,53,69,.75)', borderRadius: 4 },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => APP.fmtNumero(v, 0) } } }
                }
            });
        }
    });
</script>
<?php include INC . 'footer.php'; ?>