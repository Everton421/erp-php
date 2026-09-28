<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('produtos_ver');

$categorias = db()->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();
$statusFiltro = $_GET['status'] ?? 'todos';

$sql = 'SELECT p.*, c.nome AS categoria_nome, m.nome AS marca_nome, u.sigla AS unidade_sigla,
               f.razao_social AS fornecedor_nome
          FROM produtos p
          LEFT JOIN categorias c ON c.id = p.categoria_id
          LEFT JOIN marcas m ON m.id = p.marca_id
          LEFT JOIN unidades u ON u.id = p.unidade_id
          LEFT JOIN fornecedores f ON f.id = p.fornecedor_id';
$params = [];
if ($statusFiltro === 'ativos') {
    $sql .= ' WHERE p.status = 1';
} elseif ($statusFiltro === 'inativos') {
    $sql .= ' WHERE p.status = 0';
} elseif ($statusFiltro === 'baixo') {
    $sql .= ' WHERE p.status = 1 AND p.estoque_minimo > 0 AND p.estoque_atual <= p.estoque_minimo';
}
$sql .= ' ORDER BY p.descricao';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$tituloPagina = 'Produtos';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-box-seam me-2"></i>Produtos</h1>
        <span class="subtitulo"><?= count($produtos) ?> produto(s) na listagem</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('produtos/etiquetas.php') ?>" class="btn btn-soft"><i class="bi bi-upc-scan me-1"></i>Etiquetas</a>
        <?php if (tem_permissao('produtos_criar')): ?>
        <a href="<?= url('produtos/form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo produto</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <label class="small text-muted mb-0">Filtro rápido:</label>
        <div class="btn-group btn-group-sm">
            <a href="<?= url('produtos/index.php') ?>" class="btn <?= $statusFiltro === 'todos' ? 'btn-primary' : 'btn-light' ?>">Todos</a>
            <a href="<?= url('produtos/index.php?status=ativos') ?>" class="btn <?= $statusFiltro === 'ativos' ? 'btn-primary' : 'btn-light' ?>">Ativos</a>
            <a href="<?= url('produtos/index.php?status=baixo') ?>" class="btn <?= $statusFiltro === 'baixo' ? 'btn-primary' : 'btn-light' ?>">Estoque baixo</a>
            <a href="<?= url('produtos/index.php?status=inativos') ?>" class="btn <?= $statusFiltro === 'inativos' ? 'btn-primary' : 'btn-light' ?>">Inativos</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral responsive nowrap sem-export" style="width:100%">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Código</th>
                        <th>Barras</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Marca</th>
                        <th class="text-end">Estoque</th>
                        <th class="text-end">Custo</th>
                        <th class="text-end">Venda</th>
                        <th>Status</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td>
                            <?php if ($p['foto'] && file_exists(BASE_PATH . '/' . $p['foto'])): ?>
                            <img src="<?= url($p['foto']) ?>" width="38" height="38" class="rounded object-fit-cover" alt="">
                            <?php else: ?>
                            <span class="d-inline-grid" style="width:38px;height:38px;place-items:center;background:#eef1fb;border-radius:8px;color:#8ea0d4"><i class="bi bi-box-seam"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= e($p['codigo'] ?: '-') ?></td>
                        <td><?= e($p['codigo_barras'] ?: '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($p['descricao']) ?></div>
                            <?php if ($p['descricao_complementar']): ?><small class="text-muted"><?= e($p['descricao_complementar']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($p['categoria_nome'] ?: '-') ?></td>
                        <td><?= e($p['marca_nome'] ?: '-') ?></td>
                        <td class="text-end">
                            <?= formatar_qtde($p['estoque_atual']) ?>
                            <?php if ($p['estoque_minimo'] > 0 && $p['estoque_atual'] <= $p['estoque_minimo']): ?>
                            <span class="badge bg-danger">Baixo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end" data-moeda-exibir><?= (float)$p['preco_custo'] ?></td>
                        <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$p['preco_venda'] ?></td>
                        <td><?= (int)$p['status'] === 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                        <td class="text-nowrap">
                            <?php if (tem_permissao('produtos_editar')): ?>
                            <a href="<?= url('produtos/form.php?id=' . (int)$p['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if (tem_permissao('estoque_entrada')): ?>
                            <a href="<?= url('estoque/entrada.php?produto=' . (int)$p['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Entrada de estoque"><i class="bi bi-box-arrow-in-down"></i></a>
                            <?php endif; ?>
                            <?php if (tem_permissao('produtos_excluir')): ?>
                            <form action="<?= url('produtos/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-produto" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<script>APP.confirmarExcluir('.excluir-produto', 'Deseja excluir este produto? O histórico de movimentações será preservado.');</script>
<?php include INC . 'footer.php'; ?>