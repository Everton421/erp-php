<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('clientes_ver');

$stmt = db()->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM vendas v WHERE v.cliente_id = c.id AND v.status = "FINALIZADA") AS total_vendas
       FROM clientes c ORDER BY c.nome'
);
$clientes = $stmt->fetchAll();

$tituloPagina = 'Clientes';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-people me-2"></i>Clientes</h1>
        <span class="subtitulo"><?= count($clientes) ?> cliente(s) cadastrado(s)</span>
    </div>
    <?php if (tem_permissao('clientes_criar')): ?>
    <a href="<?= url('clientes/form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo cliente</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="1">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>CPF/CNPJ</th>
                        <th>Contato</th>
                        <th>Cidade/UF</th>
                        <th>Status</th>
                        <th>Vendas</th>
                        <th>Cadastro</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><?= e($c['codigo'] ?: '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($c['nome']) ?></div>
                            <small class="text-muted"><?= e($c['nome_fantasia'] ?: ($c['tipo'] === 'JURIDICA' ? 'PJ' : 'PF')) ?></small>
                        </td>
                        <td><?= e($c['documento'] ?: '-') ?></td>
                        <td>
                            <?= e($c['celular'] ?: $c['telefone'] ?: '-') ?>
                            <?php if ($c['email']): ?><br><small class="text-muted"><?= e($c['email']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?= e($c['cidade'] ?: '-') ?><?= $c['estado'] ? '/'.e($c['estado']) : '' ?>
                        </td>
                        <td><?= (int)$c['status'] === 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                        <td><?= (int)$c['total_vendas'] ?></td>
                        <td><?= formatar_data($c['criado_em']) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= url('clientes/ver.php?id=' . (int)$c['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Ver ficha e histórico"><i class="bi bi-eye"></i></a>
                            <?php if (tem_permissao('clientes_editar')): ?>
                            <a href="<?= url('clientes/form.php?id=' . (int)$c['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if (tem_permissao('clientes_excluir')): ?>
                            <form id="excluirCliente<?= (int)$c['id'] ?>" action="<?= url('clientes/excluir.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-cliente" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<script>APP.confirmarExcluir('.excluir-cliente', 'Deseja excluir este cliente? As vendas vinculadas serão preservadas (sem dados do cliente).');</script>
<?php include INC . 'footer.php'; ?>