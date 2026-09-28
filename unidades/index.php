<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('unidades_ver');

$dados = db()->query(
    'SELECT u.*, (SELECT COUNT(*) FROM produtos p WHERE p.unidade_id = u.id) AS qtd
       FROM unidades u ORDER BY u.nome'
)->fetchAll();

$tituloPagina = 'Unidades de medida';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-rulers me-2"></i>Unidades</h1>
        <span class="subtitulo">Unidades de medida de produtos</span>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUn"><i class="bi bi-plus-lg me-1"></i>Nova unidade</button>
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
            <table class="table table-hover table-geral" data-ordem="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descrição</th>
                        <th>Sigla</th>
                        <th>Produtos</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td class="fw-semibold"><?= e($r['nome']) ?></td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= e($r['sigla']) ?></span></td>
                        <td><?= (int)$r['qtd'] ?></td>
                        <td class="text-nowrap">
                            <?php if (tem_permissao('unidades_editar')): ?>
                            <button class="acao-btn btn-soft editar-un" data-id="<?= (int)$r['id'] ?>" data-nome="<?= e($r['nome']) ?>" data-sigla="<?= e($r['sigla']) ?>" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('unidades_excluir')): ?>
                            <form action="<?= url('unidades/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-un" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<div class="modal fade" id="modalUn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('unidades/salvar.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="unId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUnTitulo">Nova unidade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="unNome">Descrição</label>
                        <input type="text" class="form-control" id="unNome" name="nome" required maxlength="60" placeholder="Ex.: Unidade">
                    </div>
                    <div>
                        <label class="form-label obrigatorio" for="unSigla">Sigla</label>
                        <input type="text" class="form-control" id="unSigla" name="sigla" required maxlength="10" style="text-transform:uppercase" placeholder="Ex.: UN">
                    </div>
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
        $('.editar-un').on('click', function () {
            $('#unId').val($(this).data('id'));
            $('#unNome').val($(this).data('nome'));
            $('#unSigla').val($(this).data('sigla'));
            $('#modalUnTitulo').text('Editar unidade');
            $('#modalUn').modal('show');
        });
        $('[data-bs-target="#modalUn"]').on('click', function () {
            $('#unId').val(0); $('#unNome').val(''); $('#unSigla').val('');
            $('#modalUnTitulo').text('Nova unidade');
        });
    });
    APP.confirmarExcluir('.excluir-un', 'Deseja excluir esta unidade?');
</script>
<?php include INC . 'footer.php'; ?>