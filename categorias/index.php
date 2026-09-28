<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('categorias_ver');

$dados = db()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM produtos p WHERE p.categoria_id = c.id) AS qtd_produtos,
            (SELECT COUNT(*) FROM subcategorias s WHERE s.categoria_id = c.id) AS qtd_sub
       FROM categorias c ORDER BY c.nome'
)->fetchAll();

$tituloPagina = 'Categorias de produtos';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-tags me-2"></i>Categorias</h1>
        <span class="subtitulo">Classificação de produtos</span>
    </div>
    <button class="btn btn-light d-md-none" data-bs-toggle="modal" data-bs-target="#modalCat"><i class="bi bi-plus-lg me-1"></i>Nova</button>
    <button class="btn btn-primary d-none d-md-inline-flex" data-bs-toggle="modal" data-bs-target="#modalCat"><i class="bi bi-plus-lg me-1"></i>Nova categoria</button>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    <?php if (tem_permissao('categorias_ver')): ?>
    <li class="nav-item"><a class="nav-link<?= $moduloAtual === 'categorias' ? ' active' : '' ?>" href="<?= url('categorias/index.php') ?>"><i class="bi bi-tags me-1"></i>Categorias</a></li>
    <?php endif; ?>
    <?php if (tem_permissao('marcas_ver')): ?>
    <li class="nav-item"><a class="nav-link<?= $moduloAtual === 'marcas' ? ' active' : '' ?>" href="<?= url('marcas/index.php') ?>"><i class="bi bi-award me-1"></i>Marcas</a></li>
    <?php endif; ?>
    <?php if (tem_permissao('unidades_ver')): ?>
    <li class="nav-item"><a class="nav-link<?= $moduloAtual === 'unidades' ? ' active' : '' ?>" href="<?= url('unidades/index.php') ?>"><i class="bi bi-rulers me-1"></i>Unidades</a></li>
    <?php endif; ?>
</ul>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Produtos</th>
                        <th>Subcategorias</th>
                        <th>Cadastro</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['nome']) ?></td>
                        <td><?= (int)$r['qtd_produtos'] ?></td>
                        <td><?= (int)$r['qtd_sub'] ?></td>
                        <td><?= formatar_data($r['criado_em']) ?></td>
                        <td class="text-nowrap">
                            <?php if (tem_permissao('categorias_editar')): ?>
                            <button class="acao-btn btn-soft editar-cat" data-id="<?= (int)$r['id'] ?>" data-nome="<?= e($r['nome']) ?>" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('categorias_excluir')): ?>
                            <form action="<?= url('categorias/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-cat" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<div class="modal fade" id="modalCat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('categorias/salvar.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="salvar_categoria">
                <input type="hidden" name="id" id="catId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCatTitulo">Nova categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label obrigatorio" for="catNome">Nome da categoria</label>
                    <input type="text" class="form-control" id="catNome" name="nome" required maxlength="80" autofocus>
                </div>
                <div class="modal-body pt-0">
                    <label class="form-label" for="catPai">Categoria pai (subcategoria)</label>
                    <select class="form-select" id="catPai" name="categoria_pai_id">
                        <option value="0">Nenhuma</option>
                        <?php foreach ($dados as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= e($r['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Se preenchida, esta categoria vira uma <b>subcategoria</b> da selecionada.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(function () {
        $('.editar-cat').on('click', function () {
            $('#catId').val($(this).data('id'));
            $('#catNome').val($(this).data('nome'));
            $('#modalCatTitulo').text('Editar categoria');
            $('#catPai').val(0).prop('disabled', true);
            $('#modalCat').modal('show');
        });
        $('[data-bs-target="#modalCat"]').on('click', function () {
            $('#catId').val(0);
            $('#catNome').val('');
            $('#modalCatTitulo').text('Nova categoria');
            $('#catPai').prop('disabled', false).val(0);
        });
    });
    APP.confirmarExcluir('.excluir-cat', 'Deseja excluir esta categoria? Produtos ficarão sem categoria.');
</script>
<?php include INC . 'footer.php'; ?>