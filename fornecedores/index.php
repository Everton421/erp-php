<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('fornecedores_ver');

$fornecedores = db()->query('SELECT * FROM fornecedores ORDER BY razao_social')->fetchAll();

$tituloPagina = 'Fornecedores';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-truck me-2"></i>Fornecedores</h1>
        <span class="subtitulo"><?= count($fornecedores) ?> fornecedor(es) cadastrado(s)</span>
    </div>
    <?php if (tem_permissao('fornecedores_criar')): ?>
    <a href="<?= url('fornecedores/form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo fornecedor</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="1">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Razão social</th>
                        <th>CNPJ</th>
                        <th>Contato</th>
                        <th>Cidade/UF</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fornecedores as $f): ?>
                    <tr>
                        <td><?= e($f['codigo'] ?: '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($f['razao_social']) ?></div>
                            <small class="text-muted"><?= e($f['nome_fantasia']) ?></small>
                        </td>
                        <td><?= e($f['documento'] ?: '-') ?></td>
                        <td>
                            <?= e($f['celular'] ?: $f['telefone'] ?: '-') ?>
                            <?php if ($f['email']): ?><br><small class="text-muted"><?= e($f['email']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($f['cidade'] ?: '-') ?><?= $f['estado'] ? '/'.e($f['estado']) : '' ?></td>
                        <td><?= (int)$f['status'] === 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                        <td><?= formatar_data($f['criado_em']) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= url('fornecedores/ver.php?id=' . (int)$f['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Ver ficha e histórico"><i class="bi bi-eye"></i></a>
                            <?php if (tem_permissao('fornecedores_editar')): ?>
                            <a href="<?= url('fornecedores/form.php?id=' . (int)$f['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if (tem_permissao('fornecedores_excluir')): ?>
                            <form action="<?= url('fornecedores/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-fornecedor" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<script>APP.confirmarExcluir('.excluir-fornecedor', 'Deseja excluir este fornecedor?');</script>
<?php include INC . 'footer.php'; ?>