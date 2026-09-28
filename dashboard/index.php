<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('dashboard_ver');

$pdo = db();

/* ---- Cards ---- */
$sel = static function (string $cond, string $tabela = 'vendas') use ($pdo) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM " . $tabela . " WHERE status = 'FINALIZADA' AND " . $cond);
    $stmt->execute();
    return (float)$stmt->fetchColumn();
};

$vendasDia  = $sel("DATE(data_venda) = CURDATE()");
$vendasMes  = $sel("YEAR(data_venda) = YEAR(CURDATE()) AND MONTH(data_venda) = MONTH(CURDATE())");
$vendasAno  = $sel("YEAR(data_venda) = YEAR(CURDATE())");
$qtdVendasDia = (int)$pdo->query("SELECT COUNT(*) FROM vendas WHERE status = 'FINALIZADA' AND DATE(data_venda) = CURDATE()")->fetchColumn();

$cr = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento >= CURDATE() THEN valor - valor_pago END),0) AS pendente,
        COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento < CURDATE() THEN valor - valor_pago END),0) AS vencido
     FROM contas_receber"
)->fetch();
$cp = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento >= CURDATE() THEN valor - valor_pago END),0) AS pendente,
        COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento < CURDATE() THEN valor - valor_pago END),0) AS vencido
     FROM contas_pagar"
)->fetch();

$totalEstoque = (float)$pdo->query("SELECT COALESCE(SUM(estoque_atual * preco_custo), 0) FROM produtos WHERE status = 1")->fetchColumn();
$estoqueBaixo = (int)$pdo->query("SELECT COUNT(*) FROM produtos WHERE status = 1 AND estoque_minimo > 0 AND estoque_atual <= estoque_minimo")->fetchColumn();
$totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE status = 1")->fetchColumn();
$totalProdutos = (int)$pdo->query("SELECT COUNT(*) FROM produtos WHERE status = 1")->fetchColumn();

/* ---- Listas ---- */
$topProdutos = $pdo->query(
    "SELECT p.id, p.descricao, SUM(vi.quantidade) AS qtd, SUM(vi.total) AS receita
       FROM venda_itens vi
       JOIN vendas v ON v.id = vi.venda_id AND v.status = 'FINALIZADA'
       JOIN produtos p ON p.id = vi.produto_id
      GROUP BY p.id, p.descricao
      ORDER BY qtd DESC LIMIT 10"
)->fetchAll();

$estoqueBaixoLista = $pdo->query(
    "SELECT p.id, p.descricao, p.estoque_atual, p.estoque_minimo, p.estoque_maximo, p.preco_venda
       FROM produtos p
      WHERE p.status = 1 AND p.estoque_minimo > 0 AND p.estoque_atual <= p.estoque_minimo
      ORDER BY (p.estoque_atual / p.estoque_minimo) LIMIT 10"
)->fetchAll();

$semMovimentacao = $pdo->query(
    "SELECT p.id, p.descricao, p.estoque_atual, p.criado_em
       FROM produtos p
      WHERE p.status = 1
        AND NOT EXISTS (SELECT 1 FROM venda_itens vi WHERE vi.produto_id = p.id)
        AND NOT EXISTS (SELECT 1 FROM estoque_movimentos m WHERE m.produto_id = p.id)
      ORDER BY p.criado_em DESC LIMIT 10"
)->fetchAll();

$tituloPagina = 'Dashboard';
include INC . 'header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
        <span class="subtitulo"><?= e(obter_config('empresa_nome', '')) ?> • <?= date('d/m/Y') ?> <?= date('H:i') ?></span>
    </div>
</div>

<!-- Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-indigo h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-cart-check me-1"></i>Vendas de hoje</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($vendasDia) ?></div>
                <div class="stat-extra"><?= $qtdVendasDia ?> venda(s) finalizada(s)</div>
                <i class="bi bi-cart-check stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-blue h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-calendar-month me-1"></i>Vendas do mês</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($vendasMes) ?></div>
                <i class="bi bi-calendar-month stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-teal h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-calendar-range me-1"></i>Vendas do ano</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($vendasAno) ?></div>
                <i class="bi bi-calendar-range stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-violet h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-cash-stack me-1"></i>Contas a receber</div>
                <div class="stat-valor mt-1"><?= formatar_moeda((float)$cr['pendente']) ?></div>
                <div class="stat-extra text-warning-emphasis"><?= $cr['vencido'] > 0 ? formatar_moeda((float)$cr['vencido']) . ' vencido' : 'Sem contas vencidas' ?></div>
                <i class="bi bi-cash-stack stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-red h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-receipt me-1"></i>Contas a pagar</div>
                <div class="stat-valor mt-1"><?= formatar_moeda((float)$cp['pendente']) ?></div>
                <div class="stat-extra"><?= $cp['vencido'] > 0 ? formatar_moeda((float)$cp['vencido']) . ' vencido' : 'Sem contas vencidas' ?></div>
                <i class="bi bi-receipt stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-green h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-box-seam me-1"></i>Total em estoque</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($totalEstoque) ?></div>
                <div class="stat-extra">valorizado pelo custo</div>
                <i class="bi bi-box-seam stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-orange h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-exclamation-triangle me-1"></i>Estoque baixo</div>
                <div class="stat-valor mt-1"><?= $estoqueBaixo ?></div>
                <div class="stat-extra">produto(s) no mínimo</div>
                <i class="bi bi-exclamation-triangle stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card bg-grad-slate h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-people me-1"></i>Clientes</div>
                <div class="stat-valor mt-1"><?= $totalClientes ?></div>
                <div class="stat-extra"><?= $totalProdutos ?> produto(s) cadastrados</div>
                <i class="bi bi-people stat-ico"></i>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header-custom">
                <span><i class="bi bi-bar-chart-line me-2"></i>Vendas por período</span>
                <select class="form-select form-select-sm ms-auto w-auto" id="periodoVendas">
                    <option value="dia" selected>Últimos 7 dias</option>
                    <option value="semana">Últimas 4 semanas</option>
                    <option value="mes">Últimos 12 meses</option>
                    <option value="ano">Últimos 5 anos</option>
                </select>
            </div>
            <div class="card-body-custom">
                <div class="chart-periodo-wrap">
                    <canvas id="chartVendas"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-pie-chart me-2"></i>Situação financeira</div>
            <div class="card-body-custom text-center">
                <div class="chart-financeiro-wrap">
                    <canvas id="chartSituacao"></canvas>
                </div>
                <div class="row text-center mt-2 small" id="resumoFin"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card mb-3 h-100">
            <div class="card-header-custom"><i class="bi bi-graph-up-arrow me-2"></i>Fluxo de caixa (12 meses)</div>
            <div class="card-body-custom">
                <div class="chart-fluxo-wrap">
                    <canvas id="chartFluxo"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-trophy me-2"></i>Produtos mais vendidos</div>
            <div class="card-body-custom p-0">
                <?php if (!$topProdutos): ?>
                <p class="text-muted p-3 mb-0">Nenhuma venda registrada.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Produto</th><th class="text-end">Qtd.</th><th class="text-end">Receita</th></tr></thead>
                        <tbody>
                        <?php foreach ($topProdutos as $i => $p): ?>
                            <tr>
                                <td><span class="me-2 badge bg-primary-subtle text-primary"><?= $i + 1 ?></span><?= e($p['descricao']) ?></td>
                                <td class="text-end"><?= formatar_qtde($p['qtd']) ?></td>
                                <td class="text-end" data-moeda-exibir><?= (float)$p['receita'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-exclamation-diamond me-2 text-danger"></i>Estoque baixo</div>
            <div class="card-body-custom p-0">
                <?php if (!$estoqueBaixoLista): ?>
                <p class="text-muted p-3 mb-0">Nenhum produto abaixo do mínimo.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Produto</th><th class="text-end">Atual</th><th class="text-end">Mínimo</th><th class="text-end">Venda</th></tr></thead>
                        <tbody>
                        <?php foreach ($estoqueBaixoLista as $p): ?>
                            <tr>
                                <td><?= e($p['descricao']) ?></td>
                                <td class="text-end fw-semibold text-danger"><?= formatar_qtde($p['estoque_atual']) ?></td>
                                <td class="text-end"><?= formatar_qtde($p['estoque_minimo']) ?></td>
                                <td class="text-end" data-moeda-exibir><?= (float)$p['preco_venda'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header-custom"><i class="bi bi-hourglass-split me-2"></i>Sem movimentação</div>
            <div class="card-body-custom p-0">
                <?php if (!$semMovimentacao): ?>
                <p class="text-muted p-3 mb-0">Todos os produtos já possuem movimentação.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Produto</th><th class="text-end">Estoque</th><th>Cadastro</th></tr></thead>
                        <tbody>
                        <?php foreach ($semMovimentacao as $p): ?>
                            <tr>
                                <td><?= e($p['descricao']) ?></td>
                                <td class="text-end"><?= formatar_qtde($p['estoque_atual']) ?></td>
                                <td><?= formatar_data($p['criado_em']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const dashboardBaseUrl = <?= json_encode(BASE_URL) ?>;
    const fmtBRL = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    let chartVendas = null, chartSituacao = null, chartFluxo = null;

    function carregarVendas(periodo) {
        $.getJSON(dashboardBaseUrl + '/dashboard/dados.php', { grafico: 'vendas', periodo: periodo }).done(function (res) {
            if (!chartVendas) {
                chartVendas = new Chart(document.getElementById('chartVendas'), {
                    type: 'line',
                    data: { labels: res.dados.labels, datasets: [{ label: 'Vendas (R$)', data: res.dados.valores, borderColor: '#4f6ef7', backgroundColor: 'rgba(79,110,247,.12)', fill: true, tension: .35, pointRadius: 3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (c) => fmtBRL(c.parsed.y) } } }, scales: { y: { grid: { color: '#eef1f8' } } } }
                });
            } else {
                chartVendas.data.labels = res.dados.labels;
                chartVendas.data.datasets[0].data = res.dados.valores;
                chartVendas.update();
            }
        });
    }

    function carregarSituacao() {
        $.getJSON(dashboardBaseUrl + '/dashboard/dados.php', { grafico: 'financeiro', tipo: 'situacao' }).done(function (res) {
            const d = res.dados;
            chartSituacao = new Chart(document.getElementById('chartSituacao'), {
                type: 'doughnut',
                data: {
                    labels: ['Receber pendente', 'Receber vencido', 'Pagar pendente', 'Pagar vencido'],
                    datasets: [{ data: [d.receber_pendente, d.receber_vencida, d.pagar_pendente, d.pagar_vencida],
                        backgroundColor: ['#4f6ef7', '#ff6b81', '#00c9a7', '#f7a541'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }, tooltip: { callbacks: { label: (c) => ' ' + c.label + ': ' + fmtBRL(c.parsed) } } } }
            });
            $('#resumoFin').html(
                '<div class="col"><i class="bi bi-cash-coin text-primary"></i> <b>' + fmtBRL(d.receber_pendente) + '</b><br><small>Receber</small></div>' +
                '<div class="col"><i class="bi bi-receipt text-success"></i> <b>' + fmtBRL(d.pagar_pendente) + '</b><br><small>Pagar</small></div>' +
                '<div class="col"><i class="bi bi-check-circle text-success"></i> <b>' + fmtBRL(d.receber_paga) + '</b><br><small>Recebido</small></div>' +
                '<div class="col"><i class="bi bi-check2 text-secondary"></i> <b>' + fmtBRL(d.pagar_paga) + '</b><br><small>Pago</small></div>'
            );
        });
    }

    function carregarFluxo() {
        $.getJSON(dashboardBaseUrl + '/dashboard/dados.php', { grafico: 'financeiro', tipo: 'fluxo' }).done(function (res) {
            chartFluxo = new Chart(document.getElementById('chartFluxo'), {
                type: 'bar',
                data: { labels: res.dados.labels, datasets: [
                    { label: 'Entradas', data: res.dados.entradas, backgroundColor: '#00c9a7', borderRadius: 5 },
                    { label: 'Saídas', data: res.dados.saidas, backgroundColor: '#ff6b81', borderRadius: 5 }
                ] },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: false }, y: { grid: { color: '#eef1f8' } } }, plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + fmtBRL(c.parsed.y) } } } }
            });
        });
    }

    $('#periodoVendas').on('change', function () { carregarVendas($(this).val()); });
    carregarVendas('dia');
    carregarSituacao();
    carregarFluxo();
</script>
<?php include INC . 'footer.php'; ?>