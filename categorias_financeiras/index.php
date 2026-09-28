<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('contas_pagar_ver');

$dados = db()->query('SELECT * FROM categorias_financeiras ORDER BY tipo, nome')->fetchAll();

$tituloPagina = 'Categorias financeiras';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-tags me-2"></i>Categorias financeiras</h1>
        <span class="subtitulo">Classificação de receitas e despesas</span>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCatFin"><i class="bi bi-plus-lg me-1"></i>Nova categoria</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Cadastro</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td class="fw-semibold"><?= e($r['nome']) ?></td>
                        <td>
                            <?= $r['tipo'] === 'RECEITA'
                                ? '<span class="badge bg-success">Receita</span>'
                                : '<span class="badge bg-danger">Despesa</span>' ?>
                        </td>
                        <td><?= formatar_data($r['criado_em']) ?></td>
                        <td class="text-nowrap">
                            <button class="acao-btn btn-soft editar-cf" data-id="<?= (int)$r['id'] ?>" data-nome="<?= e($r['nome']) ?>" data-tipo="<?= e($r['tipo']) ?>" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></button>
                            <form action="<?= url('categorias_financeiras/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-cf" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCatFin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= url('categorias_financeiras/salvar.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="cfId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCfTitulo">Nova categoria financeira</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="cfNome">Nome</label>
                        <input type="text" class="form-control" id="cfNome" name="nome" required maxlength="80" placeholder="Ex.: Aluguel">
                    </div>
                    <div>
                        <label class="form-label obrigatorio" for="cfTipo">Tipo</label>
                        <select class="form-select" id="cfTipo" name="tipo">
                            <option value="RECEITA">Receita</option>
                            <option value="DESPESA">Despesa</option>
                        </select>
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
        $('.editar-cf').on('click', function () {
            $('#cfId').val($(this).data('id'));
            $('#cfNome').val($(this).data('nome'));
            $('#cfTipo').val($(this).data('tipo'));
            $('#modalCfTitulo').text('Editar categoria');
            $('#modalCatFin').modal('show');
        });
        $('[data-bs-target="#modalCatFin"]').on('click', function () {
            $('#cfId').val(0); $('#cfNome').val(''); $('#cfTipo').val('RECEITA');
            $('#modalCfTitulo').text('Nova categoria financeira');
        });
    });
    APP.confirmarExcluir('.excluir-cf', 'Deseja excluir esta categoria financeira?');
</script>
<?php include INC . 'footer.php'; ?>