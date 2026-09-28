<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('estoque_ver');

$pdo = db();
$fornecId = (int)($_GET['fornecedor_id'] ?? 0);

$sql = "SELECT p.*, u.sigla AS unidade, f.razao_social AS fornecedor_nome
          FROM produtos p
          LEFT JOIN unidades u ON u.id = p.unidade_id
          LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
         WHERE p.status = 1 AND p.estoque_minimo > 0 AND p.estoque_atual <= p.estoque_minimo";
$params = [];

if ($fornecId > 0) {
    $sql .= " AND p.fornecedor_id = ?";
    $params[] = $fornecId;
}

$sql .= " ORDER BY (p.estoque_atual / p.estoque_minimo) ASC, p.descricao ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Totais e cálculos
$totalItens = count($produtos);
$custoTotalReposicao = 0.0;
$qtdZerados = 0;

foreach ($produtos as &$p) {
    $atual = (float)$p['estoque_atual'];
    $min = (float)$p['estoque_minimo'];
    $max = (float)$p['estoque_maximo'];
    $custo = (float)$p['preco_custo'];

    if ($atual <= 0) {
        $qtdZerados++;
    }

    // Se tem máximo definido e max > atual, compra até o máximo; caso contrário, repõe até o dobro do mínimo
    $sugestao = $max > $atual ? ($max - $atual) : ($min * 2 - $atual);
    if ($sugestao <= 0) {
        $sugestao = 1.0;
    }

    $p['sugestao_compra'] = $sugestao;
    $p['custo_estimado'] = $sugestao * $custo;
    $custoTotalReposicao += $p['custo_estimado'];
}
unset($p);

$fornecedores = $pdo->query("SELECT id, razao_social FROM fornecedores WHERE status = 1 ORDER BY razao_social")->fetchAll();

$tituloPagina = 'Sugestão de Reposição de Estoque';
include INC . 'header.php';
?>
<div class="page-header print-hide">
    <div>
        <h1><i class="bi bi-box-arrow-in-down me-2"></i>Sugestão de Reposição de Estoque</h1>
        <span class="subtitulo">Produtos com estoque no mínimo ou esgotado que necessitam de novas compras</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (tem_permissao('compras_criar')): ?>
        <a href="<?= url('compras/nova.php') ?>" class="btn btn-primary"><i class="bi bi-basket me-1"></i>Lançar compra</a>
        <?php endif; ?>
        <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
        <a href="<?= url('estoque/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>
</div>

<!-- Cards de Resumo -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-red h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-exclamation-triangle me-1"></i>Produtos críticos</div>
                <div class="stat-valor mt-1"><?= $totalItens ?></div>
                <div class="stat-extra">abaixo do estoque mínimo</div>
                <i class="bi bi-exclamation-triangle stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-orange h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-x-octagon me-1"></i>Estoque zerado</div>
                <div class="stat-valor mt-1"><?= $qtdZerados ?></div>
                <div class="stat-extra">produtos sem saldo</div>
                <i class="bi bi-x-octagon stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card stat-card bg-grad-indigo h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-cash-stack me-1"></i>Investimento estimado para reposição</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($custoTotalReposicao) ?></div>
                <div class="stat-extra">recompondo até o estoque máximo ideal pelo custo</div>
                <i class="bi bi-cash-stack stat-ico"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filtro por Fornecedor -->
<div class="card mb-3 print-hide">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label">Filtrar por Fornecedor Padrão</label>
                <select class="form-select form-select-sm" name="fornecedor_id" onchange="this.form.submit()">
                    <option value="">Todos os fornecedores</option>
                    <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= $fornecId === (int)$f['id'] ? 'selected' : '' ?>>
                        <?= e($f['razao_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <a href="<?= url('estoque/reposicao.php') ?>" class="btn btn-sm btn-light w-100">Limpar filtro</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!count($produtos)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-check2-circle fs-1 text-success d-block mb-2"></i>
            <h5>Parabéns! O estoque está equilibrado.</h5>
            <p class="small mb-0">Nenhum produto está abaixo do estoque mínimo configurado.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="3">
                <thead class="table-light">
                    <tr>
                        <th>Cód</th>
                        <th>Produto</th>
                        <th>Fornecedor</th>
                        <th class="text-end">Atual</th>
                        <th class="text-end">Mínimo</th>
                        <th class="text-end">Máximo</th>
                        <th class="text-end text-primary fw-bold">Sugerido</th>
                        <th class="text-end">Custo Un.</th>
                        <th class="text-end">Investimento</th>
                        <th class="text-end no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td><small class="text-muted"><?= e($p['codigo'] ?: '-' ) ?></small></td>
                        <td>
                            <b><?= e($p['descricao']) ?></b>
                            <?php if ($p['localizacao']): ?><br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= e($p['localizacao']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($p['fornecedor_nome'] ?: 'Não definido') ?></td>
                        <td class="text-end fw-bold <?= (float)$p['estoque_atual'] <= 0 ? 'text-danger' : 'text-warning' ?>">
                            <?= formatar_qtde($p['estoque_atual']) ?> <?= e($p['unidade']) ?>
                        </td>
                        <td class="text-end"><?= formatar_qtde($p['estoque_minimo']) ?></td>
                        <td class="text-end"><?= (float)$p['estoque_maximo'] > 0 ? formatar_qtde($p['estoque_maximo']) : '-' ?></td>
                        <td class="text-end fw-bold text-primary fs-6">
                            + <?= formatar_qtde($p['sugestao_compra']) ?> <?= e($p['unidade']) ?>
                        </td>
                        <td class="text-end"><?= formatar_moeda($p['preco_custo']) ?></td>
                        <td class="text-end fw-semibold"><?= formatar_moeda($p['custo_estimado']) ?></td>
                        <td class="text-end text-nowrap no-print">
                            <a href="<?= url('produtos/form.php?id=' . (int)$p['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar produto">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if (tem_permissao('estoque_ajuste')): ?>
                            <a href="<?= url('estoque/ajuste.php?produto=' . (int)$p['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Ajustar estoque manual">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include INC . 'footer.php'; ?>
