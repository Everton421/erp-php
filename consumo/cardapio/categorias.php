<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('cardapio_ver');

$pdo = db();

$editando = (int)($_GET['categoria'] ?? 0);
$categoriaEdit = $editando > 0 ? buscar_linha('cardapio_categorias', $editando) : null;

$stmt = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM cardapio_itens i WHERE i.categoria_id = c.id) AS qtd_itens
       FROM cardapio_categorias c
      ORDER BY c.ordem, c.nome'
);
$categorias = $stmt->fetchAll();

$tituloPagina = 'Categorias do cardápio';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-tags me-2"></i>Categorias do cardápio</h1>
        <span class="subtitulo"><?= count($categorias) ?> categoria(s) cadastrada(s)</span>
    </div>
    <a href="<?= url('consumo/cardapio/index.php') ?>" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i>Voltar ao cardápio
    </a>
</div>

<div class="row g-3">
    <?php if (tem_permissao('cardapio_editar')): ?>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header-custom">
                <i class="bi bi-<?= $categoriaEdit ? 'pencil' : 'plus-lg' ?>"></i>
                <?= $categoriaEdit ? 'Editar categoria' : 'Nova categoria' ?>
            </div>
            <div class="card-body-custom">
                <form method="post" action="<?= url('consumo/cardapio/salvar.php') ?>" id="formCategoria">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="salvar_categoria">
                    <input type="hidden" name="id" value="<?= (int)($categoriaEdit['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="nome">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" required maxlength="60"
                               value="<?= e((string)($categoriaEdit['nome'] ?? '')) ?>"
                               placeholder="Ex.: Entradas" autofocus>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="cor">Cor</label>
                            <input type="color" class="form-control form-control-color w-100" id="cor" name="cor"
                                   value="<?= e((string)($categoriaEdit['cor'] ?? '#4f6ef7')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ordem">Ordem</label>
                            <input type="text" class="form-control" id="ordem" name="ordem" data-mask="int"
                                   maxlength="3" value="<?= (int)($categoriaEdit['ordem'] ?? 0) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ativo">Status</label>
                            <select class="form-select" id="ativo" name="ativo">
                                <option value="1" <?= (int)($categoriaEdit['ativo'] ?? 1) === 1 ? 'selected' : '' ?>>Ativa</option>
                                <option value="0" <?= (int)($categoriaEdit['ativo'] ?? 1) === 0 ? 'selected' : '' ?>>Inativa</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Salvar
                        </button>
                        <?php if ($categoriaEdit): ?>
                        <a href="<?= url('consumo/cardapio/categorias.php') ?>" class="btn btn-light">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-12 <?= tem_permissao('cardapio_editar') ? 'col-lg-8' : 'col-lg-12' ?>">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-geral responsive nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Ordem</th>
                                <th>Categoria</th>
                                <th class="text-center">Itens</th>
                                <th>Status</th>
                                <?php if (tem_permissao('cardapio_editar')): ?>
                                <th class="no-print">Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$categorias): ?>
                            <tr>
                                <td colspan="<?= tem_permissao('cardapio_editar') ? 6 : 5 ?>" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                    Nenhuma categoria cadastrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($categorias as $c): ?>
                        <tr>
                            <td><?= (int)$c['ordem'] ?></td>
                            <td>
                                <span class="categoria-tag" style="background:<?= e($c['cor']) ?>">
                                    <?= e($c['nome']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="<?= url('consumo/cardapio/index.php?categoria=' . (int)$c['id']) ?>">
                                    <?= (int)$c['qtd_itens'] ?>
                                </a>
                            </td>
                            <td>
                                <?= (int)$c['ativo'] === 1
                                    ? '<span class="badge bg-success">Ativa</span>'
                                    : '<span class="badge bg-secondary">Inativa</span>' ?>
                            </td>
                            <?php if (tem_permissao('cardapio_editar')): ?>
                            <td class="text-nowrap no-print">
                                <a href="<?= url('consumo/cardapio/categorias.php?categoria=' . (int)$c['id']) ?>"
                                   class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="<?= url('consumo/cardapio/salvar.php') ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir_categoria">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="acao-btn btn-soft-danger excluir-categoria"
                                            data-bs-toggle="tooltip" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
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
    APP.confirmarExcluir('.excluir-categoria',
        'Deseja excluir esta categoria? Os itens vinculados ficarão sem categoria.');
</script>
<?php include INC . 'footer.php'; ?>
