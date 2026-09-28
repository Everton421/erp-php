<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('relatorios_ver');

$rel = $_GET['rel'] ?? 'vendas';
if (!in_array($rel, ['vendas', 'compras', 'estoque', 'financeiro', 'dre', 'curva_abc'], true)) {
    $rel = 'vendas';
}

$de = parse_data($_GET['de'] ?? '');
$ate = parse_data($_GET['ate'] ?? '');
$tipoPedidoFiltro = (int)($_GET['tipo_pedido_id'] ?? 0);
if (!$de) {
    $de = date('Y-m-01');
}
if (!$ate) {
    $ate = date('Y-m-t');
}

$pdo = db();

$tiposPedido = [];
if (in_array($rel, ['vendas', 'compras'], true)) {
    $moduloTipo = $rel === 'vendas' ? 'VENDA' : 'COMPRA';
    $tiposPedido = $pdo->prepare('SELECT id, nome FROM tipos_pedido WHERE modulo = ? AND ativo = 1 ORDER BY nome');
    $tiposPedido->execute([$moduloTipo]);
    $tiposPedido = $tiposPedido->fetchAll();
}

// Dados por relatório
$dados = null;
$subtotais = null;
switch ($rel) {
    case 'vendas':
        if (tem_permissao('vendas_ver')) {
            $sql = 'SELECT v.id, v.numero, v.data_venda, v.total, v.status, c.nome AS cliente,
                           tp.nome AS tipo,
                           GROUP_CONCAT(DISTINCT CONCAT(fp.nome, " (", vp.valor, ")" ) SEPARATOR ", ") AS formas
                      FROM vendas v
                      LEFT JOIN clientes c ON c.id = v.cliente_id
                      LEFT JOIN tipos_pedido tp ON tp.id = v.tipo_pedido_id
                      LEFT JOIN venda_pagamentos vp ON vp.venda_id = v.id
                      LEFT JOIN formas_pagamento fp ON fp.id = vp.forma_pagamento_id
                     WHERE DATE(v.data_venda) BETWEEN ? AND ? AND v.status <> \'CANCELADA\'';
            $params = [$de, $ate];
            if ($tipoPedidoFiltro > 0) {
                $sql .= ' AND v.tipo_pedido_id = ?';
                $params[] = $tipoPedidoFiltro;
            }
            $sql .= ' GROUP BY v.id ORDER BY v.data_venda, v.id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();
            $subtotais = [0, 0];
            foreach ($dados as $v) {
                $subtotais[0]++;
                $subtotais[1] += (float)$v['total'];
            }
        }
        break;

    case 'compras':
        if (tem_permissao('compras_ver')) {
            $sql = 'SELECT cp.id, cp.numero, cp.data_compra, cp.total, cp.status,
                           f.razao_social AS fornecedor,
                           tp.nome AS tipo
                      FROM compras cp
                      LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id
                      LEFT JOIN tipos_pedido tp ON tp.id = cp.tipo_pedido_id
                     WHERE DATE(cp.data_compra) BETWEEN ? AND ? AND cp.status <> \'CANCELADA\'';
            $params = [$de, $ate];
            if ($tipoPedidoFiltro > 0) {
                $sql .= ' AND cp.tipo_pedido_id = ?';
                $params[] = $tipoPedidoFiltro;
            }
            $sql .= ' ORDER BY cp.data_compra, cp.id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();
            $subtotais = [0, 0];
            foreach ($dados as $c) {
                $subtotais[0]++;
                $subtotais[1] += (float)$c['total'];
            }
        }
        break;

    case 'estoque':
        if (tem_permissao('produtos_ver') || tem_permissao('estoque_ver')) {
            $dados = $pdo->query(
                'SELECT p.id, p.codigo, p.descricao, p.estoque_atual, p.estoque_minimo,
                        p.preco_custo, p.preco_venda, u.sigla AS unidade
                   FROM produtos p LEFT JOIN unidades u ON u.id = p.unidade_id
                  ORDER BY p.descricao'
            )->fetchAll();
            $subtotais = [0, 0];
            foreach ($dados as $p) {
                $subtotais[1] += (float)$p['estoque_atual'] * (float)$p['preco_custo'];
            }
        }
        break;

    case 'financeiro':
        if (tem_permissao('contas_receber_ver') || tem_permissao('contas_pagar_ver')) {
            $receber = [];
            $pagar = [];
            if (tem_permissao('contas_receber_ver')) {
                $stmt = $pdo->prepare(
                    'SELECT cr.id, cr.documento, cr.valor, cr.vencimento, cr.valor_pago, cr.status, c.nome AS cliente
                       FROM contas_receber cr LEFT JOIN clientes c ON c.id = cr.cliente_id
                      WHERE cr.vencimento BETWEEN ? AND ? AND cr.status IN (\'PENDENTE\',\'VENCIDO\',\'PARCIAL\')
                      ORDER BY cr.vencimento'
                );
                $stmt->execute([$de, $ate]);
                $receber = $stmt->fetchAll();
            }
            if (tem_permissao('contas_pagar_ver')) {
                $stmt = $pdo->prepare(
                    'SELECT cp.id, cp.descricao, cp.documento, cp.valor, cp.vencimento, cp.valor_pago, cp.status, f.razao_social AS fornecedor
                       FROM contas_pagar cp LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id
                      WHERE cp.vencimento BETWEEN ? AND ? AND cp.status IN (\'PENDENTE\',\'VENCIDO\',\'PARCIAL\')
                      ORDER BY cp.vencimento'
                );
                $stmt->execute([$de, $ate]);
                $pagar = $stmt->fetchAll();
            }
            $dados = ['receber' => $receber, 'pagar' => $pagar];
            $subtotais = [0, 0];
            foreach ($receber as $r) {
                $subtotais[0] += max($r['valor'] - $r['valor_pago'], 0);
            }
            foreach ($pagar as $p) {
                $subtotais[1] += max($p['valor'] - $p['valor_pago'], 0);
            }
        }
        break;

    case 'dre':
        if (tem_permissao('relatorios_ver')) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(subtotal), 0) AS subtotal,
                        COALESCE(SUM(desconto), 0) AS desconto,
                        COALESCE(SUM(acrescimo), 0) AS acrescimo,
                        COALESCE(SUM(total), 0) AS total
                   FROM vendas
                  WHERE DATE(data_venda) BETWEEN ? AND ? AND status = 'FINALIZADA'"
            );
            $stmt->execute([$de, $ate]);
            $totaisVendas = $stmt->fetch();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(vi.quantidade * p.preco_custo), 0) AS cmv
                   FROM venda_itens vi
                   JOIN vendas v ON v.id = vi.venda_id
                   JOIN produtos p ON p.id = vi.produto_id
                  WHERE DATE(v.data_venda) BETWEEN ? AND ? AND v.status = 'FINALIZADA'"
            );
            $stmt->execute([$de, $ate]);
            $cmv = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(cf.nome, 'Outras Despesas') AS categoria,
                        SUM(cp.valor_pago) AS total_despesa
                   FROM contas_pagar cp
                   LEFT JOIN categorias_financeiras cf ON cf.id = cp.categoria_id
                  WHERE DATE(cp.data_pagamento) BETWEEN ? AND ? AND cp.status = 'PAGO'
                  GROUP BY cp.categoria_id, cf.nome
                  ORDER BY total_despesa DESC"
            );
            $stmt->execute([$de, $ate]);
            $despesas = $stmt->fetchAll();

            $totalDespesas = 0.0;
            foreach ($despesas as $d) {
                $totalDespesas += (float)$d['total_despesa'];
            }

            $recBruta = (float)$totaisVendas['subtotal'] + (float)$totaisVendas['acrescimo'];
            $deducoes = (float)$totaisVendas['desconto'];
            $recLiquida = (float)$totaisVendas['total'];
            $lucroBruto = $recLiquida - $cmv;
            $lucroLiquido = $lucroBruto - $totalDespesas;

            $dados = [
                'receita_bruta' => $recBruta,
                'descontos' => $deducoes,
                'receita_liquida' => $recLiquida,
                'cmv' => $cmv,
                'lucro_bruto' => $lucroBruto,
                'margem_bruta' => $recLiquida > 0 ? ($lucroBruto / $recLiquida) * 100 : 0.0,
                'despesas' => $despesas,
                'total_despesas' => $totalDespesas,
                'lucro_liquido' => $lucroLiquido,
                'margem_liquida' => $recLiquida > 0 ? ($lucroLiquido / $recLiquida) * 100 : 0.0,
            ];
        }
        break;

    case 'curva_abc':
        if (tem_permissao('relatorios_ver') || tem_permissao('produtos_ver')) {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.codigo, p.descricao, u.sigla AS unidade,
                        SUM(vi.quantidade) AS qtd_total,
                        SUM(vi.total) AS faturamento_total
                   FROM venda_itens vi
                   JOIN vendas v ON v.id = vi.venda_id
                   JOIN produtos p ON p.id = vi.produto_id
                   LEFT JOIN unidades u ON u.id = p.unidade_id
                  WHERE DATE(v.data_venda) BETWEEN ? AND ? AND v.status = 'FINALIZADA'
                  GROUP BY p.id, p.codigo, p.descricao, u.sigla
                  ORDER BY faturamento_total DESC"
            );
            $stmt->execute([$de, $ate]);
            $lista = $stmt->fetchAll();

            $faturamentoGeral = 0.0;
            foreach ($lista as $item) {
                $faturamentoGeral += (float)$item['faturamento_total'];
            }

            $acumulado = 0.0;
            foreach ($lista as &$item) {
                $fat = (float)$item['faturamento_total'];
                $acumulado += $fat;
                $pct = $faturamentoGeral > 0 ? ($fat / $faturamentoGeral) * 100 : 0.0;
                $pctAcum = $faturamentoGeral > 0 ? ($acumulado / $faturamentoGeral) * 100 : 0.0;

                if ($pctAcum <= 80.0) {
                    $classe = 'A';
                } elseif ($pctAcum <= 95.0) {
                    $classe = 'B';
                } else {
                    $classe = 'C';
                }

                $item['pct'] = $pct;
                $item['pct_acumulado'] = $pctAcum;
                $item['classe'] = $classe;
            }
            unset($item);

            $dados = [
                'itens' => $lista,
                'faturamento_geral' => $faturamentoGeral,
            ];
        }
        break;
}

$tituloPagina = 'Relatórios';
include INC . 'header.php';
?>
<style>
    .group-title { font-size: 1.05rem; font-weight: 700; color: var(--accent); }
</style>

<div class="page-header print-hide">
    <div>
        <h1><i class="bi bi-file-earmark-bar-graph me-2"></i>Relatórios</h1>
        <span class="subtitulo">Análises operacionais, gerenciais e financeiras</span>
    </div>
    <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
</div>

<div class="card mb-3 print-hide">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Relatório</label>
                <select class="form-select form-select-sm" name="rel" onchange="this.form.submit()">
                    <option value="vendas" <?= $rel === 'vendas' ? 'selected' : '' ?>>Vendas por período</option>
                    <option value="compras" <?= $rel === 'compras' ? 'selected' : '' ?>>Compras por período</option>
                    <option value="estoque" <?= $rel === 'estoque' ? 'selected' : '' ?>>Posição de estoque</option>
                    <option value="financeiro" <?= $rel === 'financeiro' ? 'selected' : '' ?>>Contas em aberto</option>
                    <option value="dre" <?= $rel === 'dre' ? 'selected' : '' ?>>DRE Gerencial (Resultado)</option>
                    <option value="curva_abc" <?= $rel === 'curva_abc' ? 'selected' : '' ?>>Curva ABC de Produtos</option>
                </select>
            </div>
            <?php if ($rel !== 'estoque'): ?>
            <div class="col-6 col-md-3">
                <label class="form-label">De</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= e($de) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($ate) ?>">
            </div>
            <?php endif; ?>
            <?php if (count($tiposPedido)): ?>
            <div class="col-6 col-md-3">
                <label class="form-label">Tipo</label>
                <select class="form-select form-select-sm" name="tipo_pedido_id">
                    <option value="0" <?= $tipoPedidoFiltro === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($tiposPedido as $tp): ?>
                    <option value="<?= (int)$tp['id'] ?>" <?= $tipoPedidoFiltro === (int)$tp['id'] ? 'selected' : '' ?>><?= e($tp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button class="btn btn-sm btn-soft"><i class="bi bi-funnel me-1"></i>Gerar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($dados === null): ?>
    <div class="alert alert-warning">Você não tem permissão para visualizar este relatório.</div>
<?php elseif ($rel === 'vendas'): ?>
    <div class="card">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="bi bi-cart-check"></i> Vendas de <?= formatar_data($de) ?> a <?= formatar_data($ate) ?></span>
            <span class="fs-5 fw-bold text-success"><?= (int)$subtotais[0] ?> venda(s) • <?= formatar_moeda($subtotais[1]) ?></span>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Data</th><th>Nº</th><th>Tipo</th><th>Cliente</th><th>Formas de pagamento</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados as $v): ?>
                        <tr>
                            <td><?= formatar_datahora($v['data_venda']) ?></td>
                            <td><?= e($v['numero']) ?></td>
                            <td><?= e($v['tipo'] ?? '-') ?></td>
                            <td><?= e($v['cliente'] ?? 'Consumidor final') ?></td>
                            <td class="small"><?= e($v['formas'] ?? '-') ?></td>
                            <td class="text-end fw-semibold"><?= formatar_moeda($v['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($rel === 'compras'): ?>
    <div class="card">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="bi bi-basket"></i> Compras de <?= formatar_data($de) ?> a <?= formatar_data($ate) ?></span>
            <span class="fs-5 fw-bold text-success"><?= (int)$subtotais[0] ?> compra(s) • <?= formatar_moeda($subtotais[1]) ?></span>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Data</th><th>Nº</th><th>Tipo</th><th>Fornecedor</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados as $c): ?>
                        <tr>
                            <td><?= formatar_datahora($c['data_compra']) ?></td>
                            <td><?= e($c['numero']) ?></td>
                            <td><?= e($c['tipo'] ?? '-') ?></td>
                            <td><?= e($c['fornecedor'] ?? '-') ?></td>
                            <td class="text-end fw-semibold"><?= formatar_moeda($c['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($rel === 'estoque'): ?>
    <div class="card">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="bi bi-box-seam"></i> Posição de estoque</span>
            <span class="fs-5 fw-bold text-success"><?= (int)$subtotais[0] ?> produto(s) • Custo total: <?= formatar_moeda($subtotais[1]) ?></span>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Código</th><th>Produto</th><th class="text-center">Estoque</th><th class="text-center">Mínimo</th><th class="text-end">Custo</th><th class="text-end">Venda</th><th class="text-center">Situação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados as $p): ?>
                        <tr>
                            <td><?= e($p['codigo'] ?? '-') ?></td>
                            <td><?= e($p['descricao']) ?></td>
                            <td class="text-center"><?= formatar_qtde($p['estoque_atual']) ?> <?= e($p['unidade'] ?? '') ?></td>
                            <td class="text-center"><?= formatar_qtde($p['estoque_minimo']) ?></td>
                            <td class="text-end"><?= formatar_moeda($p['preco_custo']) ?></td>
                            <td class="text-end"><?= formatar_moeda($p['preco_venda']) ?></td>
                            <td class="text-center">
                                <?php if ($p['estoque_atual'] <= 0): ?>
                                <span class="badge bg-danger">Sem estoque</span>
                                <?php elseif ($p['estoque_atual'] <= $p['estoque_minimo']): ?>
                                <span class="badge bg-warning text-dark">Baixo</span>
                                <?php else: ?>
                                <span class="badge bg-success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($rel === 'financeiro'): ?>
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
                    <span><i class="bi bi-cash-coin"></i> A receber em aberto</span>
                    <span class="fs-6 fw-bold text-danger"><?= formatar_moeda($subtotais[0]) ?></span>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Cliente</th><th>Venc.</th><th class="text-end">Valor</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($dados['receber'] as $r): ?>
                                <tr>
                                    <td><?= e($r['cliente'] ?? '-') ?></td>
                                    <td><?= formatar_data($r['vencimento']) ?></td>
                                    <td class="text-end"><?= formatar_moeda(max($r['valor'] - $r['valor_pago'], 0)) ?></td>
                                    <td><?= badge_status($r['status'] === 'PENDENTE' && $r['vencimento'] < hoje() ? 'VENCIDO' : $r['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
                    <span><i class="bi bi-receipt"></i> A pagar em aberto</span>
                    <span class="fs-6 fw-bold text-danger"><?= formatar_moeda($subtotais[1]) ?></span>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Descrição</th><th>Venc.</th><th class="text-end">Valor</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($dados['pagar'] as $p): ?>
                                <tr>
                                    <td><?= e($p['descricao']) ?></td>
                                    <td><?= formatar_data($p['vencimento']) ?></td>
                                    <td class="text-end"><?= formatar_moeda(max($p['valor'] - $p['valor_pago'], 0)) ?></td>
                                    <td><?= badge_status($p['status'] === 'PENDENTE' && $p['vencimento'] < hoje() ? 'VENCIDO' : $p['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($rel === 'dre' && $dados): ?>
    <!-- DRE Gerencial -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card bg-grad-indigo h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-cart-check me-1"></i>Receita líquida</div>
                    <div class="stat-valor mt-1"><?= formatar_moeda($dados['receita_liquida']) ?></div>
                    <div class="stat-extra">faturamento após descontos</div>
                    <i class="bi bi-cart-check stat-ico"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card bg-grad-teal h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-pie-chart me-1"></i>Lucro bruto</div>
                    <div class="stat-valor mt-1"><?= formatar_moeda($dados['lucro_bruto']) ?></div>
                    <div class="stat-extra">margem: <?= number_format($dados['margem_bruta'], 1, ',', '.') ?>%</div>
                    <i class="bi bi-pie-chart stat-ico"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card bg-grad-red h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-receipt me-1"></i>Despesas pagas</div>
                    <div class="stat-valor mt-1"><?= formatar_moeda($dados['total_despesas']) ?></div>
                    <div class="stat-extra">contas operacionais</div>
                    <i class="bi bi-receipt stat-ico"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card <?= $dados['lucro_liquido'] >= 0 ? 'bg-grad-green' : 'bg-grad-red' ?> h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-trophy me-1"></i>Lucro líquido</div>
                    <div class="stat-valor mt-1"><?= formatar_moeda($dados['lucro_liquido']) ?></div>
                    <div class="stat-extra">margem: <?= number_format($dados['margem_liquida'], 1, ',', '.') ?>%</div>
                    <i class="bi bi-trophy stat-ico"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header-custom"><i class="bi bi-calculator me-1"></i>Demonstração do Resultado do Exercício (DRE)</div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <tbody>
                                <tr class="table-light fw-bold">
                                    <td>(=) RECEITA OPERACIONAL BRUTA</td>
                                    <td class="text-end text-success"><?= formatar_moeda($dados['receita_bruta']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4 text-muted">(-) Deduções e Descontos Concedidos</td>
                                    <td class="text-end text-danger">- <?= formatar_moeda($dados['descontos']) ?></td>
                                </tr>
                                <tr class="fw-semibold">
                                    <td>(=) RECEITA OPERACIONAL LÍQUIDA</td>
                                    <td class="text-end"><?= formatar_moeda($dados['receita_liquida']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4 text-muted">(-) Custo das Mercadorias Vendidas (CMV)</td>
                                    <td class="text-end text-danger">- <?= formatar_moeda($dados['cmv']) ?></td>
                                </tr>
                                <tr class="table-primary fw-bold">
                                    <td>(=) LUCRO OPERACIONAL BRUTO</td>
                                    <td class="text-end text-primary"><?= formatar_moeda($dados['lucro_bruto']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4 text-muted">(-) Despesas Operacionais / Administrativas</td>
                                    <td class="text-end text-danger">- <?= formatar_moeda($dados['total_despesas']) ?></td>
                                </tr>
                                <tr class="fw-bold fs-5 <?= $dados['lucro_liquido'] >= 0 ? 'table-success text-success' : 'table-danger text-danger' ?>">
                                    <td>(=) RESULTADO LÍQUIDO DO PERÍODO</td>
                                    <td class="text-end"><?= formatar_moeda($dados['lucro_liquido']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header-custom"><i class="bi bi-tags me-1"></i>Despesas por Categoria</div>
                <div class="card-body-custom p-0">
                    <?php if (!count($dados['despesas'])): ?>
                    <div class="p-3 text-muted text-center small">Nenhuma despesa quitada no período.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Categoria</th><th class="text-end">Valor Pago</th></tr></thead>
                            <tbody>
                                <?php foreach ($dados['despesas'] as $d): ?>
                                <tr>
                                    <td><?= e($d['categoria']) ?></td>
                                    <td class="text-end fw-semibold text-danger"><?= formatar_moeda((float)$d['total_despesa']) ?></td>
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

<?php elseif ($rel === 'curva_abc' && $dados): ?>
    <!-- Curva ABC -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-grad-green h-100">
                <div class="card-body">
                    <div class="stat-label">Classe A (Alta relevância)</div>
                    <div class="stat-valor mt-1">Até 80%</div>
                    <div class="stat-extra">dos produtos mais rentáveis</div>
                    <i class="bi bi-star-fill stat-ico"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-grad-orange h-100">
                <div class="card-body">
                    <div class="stat-label">Classe B (Média relevância)</div>
                    <div class="stat-valor mt-1">80% a 95%</div>
                    <div class="stat-extra">do faturamento acumulado</div>
                    <i class="bi bi-bar-chart-fill stat-ico"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-grad-slate h-100">
                <div class="card-body">
                    <div class="stat-label">Classe C (Cauda longa)</div>
                    <div class="stat-valor mt-1">95% a 100%</div>
                    <div class="stat-extra">baixo impacto no faturamento</div>
                    <i class="bi bi-box stat-ico"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="bi bi-graph-up-arrow me-1"></i>Classificação Curva ABC de Produtos</span>
            <span class="fw-bold">Faturamento no período: <?= formatar_moeda($dados['faturamento_geral']) ?></span>
        </div>
        <div class="card-body-custom p-0">
            <?php if (!count($dados['itens'])): ?>
            <div class="p-4 text-center text-muted">Nenhum produto vendido no período informado.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-geral mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">Classe</th>
                            <th>Código</th>
                            <th>Produto</th>
                            <th class="text-end">Qtd Vendida</th>
                            <th class="text-end">Faturamento</th>
                            <th class="text-end">% Part.</th>
                            <th class="text-end">% Acumulada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados['itens'] as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['classe'] === 'A'): ?>
                                    <span class="badge bg-success fs-6 px-2">A</span>
                                <?php elseif ($p['classe'] === 'B'): ?>
                                    <span class="badge bg-warning text-dark fs-6 px-2">B</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6 px-2">C</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e($p['codigo'] ?: '-') ?></small></td>
                            <td><b><?= e($p['descricao']) ?></b></td>
                            <td class="text-end"><?= formatar_qtde($p['qtd_total']) ?> <?= e($p['unidade']) ?></td>
                            <td class="text-end fw-bold"><?= formatar_moeda((float)$p['faturamento_total']) ?></td>
                            <td class="text-end"><?= number_format((float)$p['pct'], 2, ',', '.') ?>%</td>
                            <td class="text-end text-muted fw-semibold"><?= number_format((float)$p['pct_acumulado'], 2, ',', '.') ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include INC . 'footer.php'; ?>