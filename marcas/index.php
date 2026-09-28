<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('marcas_ver');

$dados = db()->query(
    'SELECT m.*, (SELECT COUNT(*) FROM produtos p WHERE p.marca_id = m.id) AS qtd
       FROM marcas m ORDER BY m.nome'
)->fetchAll();

$tituloPagina = 'Marcas';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-award me-2"></i>Marcas</h1>
        <span class="subtitulo">Fabricantes e marcas de produtos</span>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMarca"><i class="bi bi-plus-lg me-1"></i>Nova marca</button>
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
                        <th>Cadastro</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['nome']) ?></td>
                        <td><?= (int)$r['qtd'] ?></td>
                        <td><?= formatar_data($r['criado_em']) ?></td>
                        <td class="text-nowrap">
                            <?php if (tem_permissao('marcas_editar')): ?>
                            <button class="acao-btn btn-soft editar-marca" data-id="<?= (int)$r['id'] ?>" data-nome="<?= e($r['nome']) ?>" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('marcas_excluir')): ?>
                            <form action="<?= url('marcas/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-marca" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<div class="modal fade" id="modalMarca" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('marcas/salvar.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="marcaId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMarcaTitulo">Nova marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label obrigatorio" for="marcaNome">Nome da marca</label>
                    <input type="text" class="form-control" id="marcaNome" name="nome" required maxlength="80">
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
        $('.editar-marca').on('click', function () {
            $('#marcaId').val($(this).data('id'));
            $('#marcaNome').val($(this).data('nome'));
            $('#modalMarcaTitulo').text('Editar marca');
            $('#modalMarca').modal('show');
        });
        $('[data-bs-target="#modalMarca"]').on('click', function () {
            $('#marcaId').val(0);
            $('#marcaNome').val('');
            $('#modalMarcaTitulo').text('Nova marca');
        });
    });
    APP.confirmarExcluir('.excluir-marca', 'Deseja excluir esta marca?');
</script>
<?php include INC . 'footer.php'; ?>