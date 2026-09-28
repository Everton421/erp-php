<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('usuarios_ver');

$tituloPagina = 'Usuários';

$stmt = db()->prepare(
    'SELECT u.*, p.nome AS perfil_nome
       FROM usuarios u
       LEFT JOIN perfis p ON p.id = u.perfil_id
      ORDER BY u.nome'
);
$stmt->execute();
$usuarios = $stmt->fetchAll();

$meuId = (int)usuario_atual()['id'];
$usuariosAdmin = array_filter($usuarios, fn($u) => (int)$u['is_admin'] === 1);
$ultimoAdminId = count($usuariosAdmin) === 1 ? (int)reset($usuariosAdmin)['id'] : null;

include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-people-fill me-2"></i>Usuários</h1>
        <span class="subtitulo">Cadastro e controle de acesso</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('usuarios/perfis.php') ?>" class="btn btn-soft"><i class="bi bi-person-gear me-1"></i>Perfis e permissões</a>
        <?php if (tem_permissao('usuarios_criar')): ?>
        <a href="<?= url('usuarios/form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo usuário</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Último acesso</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= e($u['nome']) ?>
                            <?php if ((int)$u['is_admin'] === 1): ?><span class="badge bg-primary-subtle text-primary ms-1">Admin</span><?php endif; ?>
                        </td>
                        <td><?= e($u['usuario']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['perfil_nome'] ?? '-') ?></td>
                        <td>
                            <?php if ((int)$u['status'] === 1): ?>
                            <span class="badge bg-success">Ativo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatar_datahora($u['ultimo_acesso']) ?: '-' ?></td>
                        <td>
                            <?php if ($u['id'] == $meuId || tem_permissao('usuarios_editar')): ?>
                            <a href="<?= url('usuarios/form.php?id=' . (int)$u['id']) ?>" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if (tem_permissao('usuarios_excluir') && (int)$u['id'] !== $meuId && (int)$u['id'] !== $ultimoAdminId): ?>
                            <form id="excluirUser<?= (int)$u['id'] ?>" action="<?= url('usuarios/salvar.php') ?>" method="post" class="d-inline ajax-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir_user">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-user" data-bs-toggle="tooltip" title="Excluir"><i class="bi bi-trash"></i></button>
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

<script>
    APP.confirmarExcluir('.excluir-user', 'Deseja realmente excluir este usuário? Esta ação não poderá ser desfeita.');
</script>
<?php include INC . 'footer.php'; ?>