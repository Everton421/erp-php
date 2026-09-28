<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('cardapio_ver');

$pdo = db();

$categorias = cardapio_categorias(true);
$statusFiltro = (string)($_GET['status'] ?? 'ativos');
$catFiltro = (int)($_GET['categoria'] ?? 0);
$busca = trim((string)($_GET['busca'] ?? ''));

$sql = 'SELECT i.*, c.nome AS categoria_nome, c.cor AS categoria_cor
          FROM cardapio_itens i
          LEFT JOIN cardapio_categorias c ON c.id = i.categoria_id
         WHERE 1=1';
$params = [];

if ($statusFiltro === 'ativos') {
    $sql .= ' AND i.ativo = 1';
} elseif ($statusFiltro === 'inativos') {
    $sql .= ' AND i.ativo = 0';
}
if ($catFiltro > 0) {
    $sql .= ' AND i.categoria_id = ?';
    $params[] = $catFiltro;
}
if ($busca !== '') {
    $sql .= ' AND (i.descricao LIKE ? OR i.codigo LIKE ?)';
    $params[] = '%' . $busca . '%';
    $params[] = '%' . $busca . '%';
}
$sql .= ' ORDER BY c.ordem, c.nome, i.descricao';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$itens = $stmt->fetchAll();

$qtdAtivos = (int)$pdo->query('SELECT COUNT(*) FROM cardapio_itens WHERE ativo = 1')->fetchColumn();
$qtdInativos = (int)$pdo->query('SELECT COUNT(*) FROM cardapio_itens WHERE ativo = 0')->fetchColumn();
$valorMedio = (float)$pdo->query('SELECT COALESCE(AVG(preco), 0) FROM cardapio_itens WHERE ativo = 1')->fetchColumn();

$tituloPagina = 'Cardápio';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-text me-2"></i>Cardápio</h1>
        <span class="subtitulo">
            <?= count($itens) ?> item(ns) na listagem &middot;
            <?= $qtdAtivos ?> ativo(s) &middot;
            <?= $qtdInativos ?> inativo(s) &middot;
            ticket médio <?= formatar_moeda($valorMedio) ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('consumo/cardapio/categorias.php') ?>" class="btn btn-soft">
            <i class="bi bi-tags me-1"></i>Categorias
        </a>
        <?php if (tem_permissao('cardapio_editar')): ?>
        <a href="<?= url('consumo/cardapio/form.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Novo item
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($statusFiltro) ?>">
            <div class="col-12 col-md-5">
                <label class="form-label" for="busca">Buscar item</label>
                <input type="text" class="form-control" id="busca" name="busca" value="<?= e($busca) ?>"
                       placeholder="Descrição ou código" maxlength="60">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="categoria">Categoria</label>
                <select class="form-select" id="categoria" name="categoria">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $catFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="<?= url('consumo/cardapio/index.php') ?>" class="btn btn-light">Limpar</a>
            </div>
        </form>
        <div class="btn-group btn-group-sm mt-3">
            <a href="<?= url('consumo/cardapio/index.php?status=ativos') ?>"
               class="btn <?= $statusFiltro === 'ativos' ? 'btn-primary' : 'btn-light' ?>">Ativos</a>
            <a href="<?= url('consumo/cardapio/index.php?status=inativos') ?>"
               class="btn <?= $statusFiltro === 'inativos' ? 'btn-primary' : 'btn-light' ?>">Inativos</a>
            <a href="<?= url('consumo/cardapio/index.php?status=todos') ?>"
               class="btn <?= $statusFiltro === 'todos' ? 'btn-primary' : 'btn-light' ?>">Todos</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-end">Preço</th>
                        <th class="text-center">Preparo</th>
                        <th>Observações</th>
                        <th>Status</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$itens): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                            Nenhum item encontrado com os filtros aplicados.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($itens as $i): ?>
                    <tr>
                        <td>
                            <?php if ($i['foto'] && file_exists(BASE_PATH . '/' . $i['foto'])): ?>
                            <img src="<?= url($i['foto']) ?>" width="38" height="38" class="rounded object-fit-cover" alt="">
                            <?php else: ?>
                            <span class="d-inline-grid" style="width:38px;height:38px;place-items:center;background:#eef1fb;border-radius:8px;color:#8ea0d4">
                                <i class="bi bi-cup-hot"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= e($i['codigo'] ?: '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($i['descricao']) ?></div>
                            <?php if ($i['descricao_complementar']): ?>
                            <small class="text-muted"><?= e($i['descricao_complementar']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($i['categoria_nome']): ?>
                            <span class="categoria-tag" style="background:<?= e($i['categoria_cor'] ?: '#4f6ef7') ?>">
                                <?= e($i['categoria_nome']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$i['preco'] ?></td>
                        <td class="text-center">
                            <?= $i['tempo_preparo'] ? (int)$i['tempo_preparo'] . ' min' : '-' ?>
                        </td>
                        <td class="small text-muted"><?= e($i['observacoes'] ?: '-') ?></td>
                        <td>
                            <?= (int)$i['ativo'] === 1
                                ? '<span class="badge bg-success">Ativo</span>'
                                : '<span class="badge bg-secondary">Inativo</span>' ?>
                        </td>
                        <td class="text-nowrap no-print">
                            <?php if (tem_permissao('cardapio_editar')): ?>
                            <a href="<?= url('consumo/cardapio/form.php?id=' . (int)$i['id']) ?>"
                               class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (tem_permissao('cardapio_editar')): ?>
                            <form action="<?= url('consumo/cardapio/salvar.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="alternar_item">
                                <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft" data-bs-toggle="tooltip"
                                        title="<?= (int)$i['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                    <i class="bi bi-<?= (int)$i['ativo'] === 1 ? 'toggle-on' : 'toggle-off' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (tem_permissao('cardapio_editar')): ?>
                            <form action="<?= url('consumo/cardapio/salvar.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir_item">
                                <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-item" data-bs-toggle="tooltip"
                                        title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>APP.confirmarExcluir('.excluir-item', 'Deseja excluir este item do cardápio? Itens já lançados em comandas serão preservados.');</script>
<?php include INC . 'footer.php'; ?>
