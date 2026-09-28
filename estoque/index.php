<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('estoque_ver');

$filtroTipo = $_GET['tipo'] ?? '';
$filtroProduto = (int)($_GET['produto'] ?? 0);
$filtroDataIni = parse_data($_GET['de'] ?? '');
$filtroDataFim = parse_data($_GET['ate'] ?? '');

$sql = "SELECT m.*, p.descricao AS produto_nome, p.codigo AS produto_codigo, u.nome AS usuario_nome
          FROM estoque_movimentos m
          JOIN produtos p ON p.id = m.produto_id
          LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE 1=1";
$params = [];
if ($filtroTipo !== '') {
    $sql .= ' AND m.tipo = ?';
    $params[] = $filtroTipo;
}
if ($filtroProduto > 0) {
    $sql .= ' AND m.produto_id = ?';
    $params[] = $filtroProduto;
}
if ($filtroDataIni) {
    $sql .= ' AND DATE(m.data) >= ?';
    $params[] = $filtroDataIni;
}
if ($filtroDataFim) {
    $sql .= ' AND DATE(m.data) <= ?';
    $params[] = $filtroDataFim;
}
$sql .= ' ORDER BY m.data DESC, m.id DESC LIMIT 2000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$movimentos = $stmt->fetchAll();

$produtos = $filtroProduto > 0 ? [buscar_linha('produtos', $filtroProduto)] : [];

$tituloPagina = 'Estoque';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-archive me-2"></i>Controle de estoque</h1>
        <span class="subtitulo">Histórico de movimentações</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('estoque/reposicao.php') ?>" class="btn btn-outline-warning text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Sugestão de Reposição</a>
        <?php if (tem_permissao('estoque_saida')): ?>
        <a href="<?= url('estoque/saida.php') ?>" class="btn btn-outline-danger"><i class="bi bi-box-arrow-up me-1"></i>Saída</a>
        <?php endif; ?>
        <?php if (tem_permissao('estoque_ajuste')): ?>
        <a href="<?= url('estoque/ajuste.php') ?>" class="btn btn-soft"><i class="bi bi-arrow-repeat me-1"></i>Ajuste</a>
        <?php endif; ?>
        <?php if (tem_permissao('estoque_entrada')): ?>
        <a href="<?= url('estoque/entrada.php') ?>" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i>Entrada</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">Tipo</label>
                <select class="form-select form-select-sm" name="tipo">
                    <option value="">Todos</option>
                    <option value="ENTRADA" <?= $filtroTipo === 'ENTRADA' ? 'selected' : '' ?>>Entrada</option>
                    <option value="SAIDA" <?= $filtroTipo === 'SAIDA' ? 'selected' : '' ?>>Saída</option>
                    <option value="AJUSTE" <?= $filtroTipo === 'AJUSTE' ? 'selected' : '' ?>>Ajuste</option>
                </select>
            </div>
            <div class="col-6 col-md-6">
                <label class="form-label">Produto</label>
                <select class="form-select form-select-sm" name="produto">
                    <option value="0">Todos os produtos</option>
                    <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int)$filtroProduto ?>" selected><?= e($p['descricao']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">De</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= e($filtroDataIni ?? '') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($filtroDataFim ?? '') ?>">
            </div>
            <div class="col-12 col-md-auto mt-2 mt-md-0">
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
                        <th>Tipo</th>
                        <th>Produto</th>
                        <th class="text-end">Qtd.</th>
                        <th class="text-end">Ant.</th>
                        <th class="text-end">Depois</th>
                        <th>Custo</th>
                        <th>Motivo</th>
                        <th>Documento</th>
                        <th>Usuário</th>
                        <th>Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimentos as $m): ?>
                    <tr>
                        <td><?= formatar_datahora($m['data']) ?></td>
                        <td>
                            <?= $m['tipo'] === 'ENTRADA' ? '<span class="badge bg-success">Entrada</span>'
                                : ($m['tipo'] === 'SAIDA' ? '<span class="badge bg-danger">Saída</span>'
                                : '<span class="badge bg-warning text-dark">Ajuste</span>') ?>
                        </td>
                        <td>
                            <a href="<?= url('produtos/form.php?id=' . (int)$m['produto_id']) ?>" class="fw-semibold"><?= e($m['produto_nome']) ?></a>
                            <small class="text-muted d-block"><?= e($m['produto_codigo'] ?? '') ?></small>
                        </td>
                        <td class="text-end fw-semibold"><?= formatar_qtde($m['quantidade']) ?></td>
                        <td class="text-end"><?= formatar_qtde($m['estoque_anterior']) ?></td>
                        <td class="text-end"><?= formatar_qtde($m['estoque_posterior']) ?></td>
                        <td><?= $m['custo'] !== null ? formatar_moeda($m['custo']) : '-' ?></td>
                        <td><?= e($m['motivo'] ?? '-') ?></td>
                        <td><?= e($m['documento'] ?? '-') ?></td>
                        <td><?= e($m['usuario_nome'] ?? '-') ?></td>
                        <td class="text-muted" style="max-width:180px"><?= e($m['observacao'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include INC . 'footer.php'; ?>