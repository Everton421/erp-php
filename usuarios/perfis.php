<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('usuarios_ver');

$perfis = db()->query(
    'SELECT p.*, (SELECT COUNT(*) FROM usuarios u WHERE u.perfil_id = p.id) AS qtd_usuarios
       FROM perfis p ORDER BY p.id'
)->fetchAll();

$tituloPagina = 'Perfis de acesso';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-person-gear me-2"></i>Perfis de acesso</h1>
        <span class="subtitulo">Defina conjuntos de permissões por perfil</span>
    </div>
    <a href="<?= url('usuarios/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header-custom"><i class="bi bi-plus-circle"></i>Novo perfil</div>
            <div class="card-body-custom">
                <form method="post" action="<?= url('usuarios/salvar.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="salvar_perfil">
                    <input type="hidden" name="id" value="0">
                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="nome">Nome do perfil</label>
                        <input type="text" class="form-control" id="nome" name="nome" required maxlength="60" placeholder="Ex.: Supervisor">
                    </div>
                    <?php if (tem_permissao('usuarios_editar')): ?>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Criar perfil</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="alert alert-info small mt-3">
            <i class="bi bi-info-circle me-1"></i>Cada perfil pode ter permissões próprias.
            Usuários podem ainda ganhar <b>exceções individuais</b> na tela de edição de usuário.
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header-custom"><i class="bi bi-shield-shaded"></i>Perfis cadastrados</div>
            <div class="card-body-custom">
                <?php if (!$perfis): ?>
                <p class="text-muted mb-0">Nenhum perfil cadastrado.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($perfis as $p): ?>
                    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                        <i class="bi bi-person-vcard fs-5 text-primary"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($p['nome']) ?></div>
                            <small class="text-muted"><?= (int)$p['qtd_usuarios'] ?> usuário(s)</small>
                        </div>
                        <?php if (tem_permissao('usuarios_editar')): ?>
                        <a href="<?= url('usuarios/perfil_permissoes.php?id=' . (int)$p['id']) ?>" class="btn btn-soft btn-sm"><i class="bi bi-sliders me-1"></i>Permissões</a>
                        <?php endif; ?>
                        <?php if (tem_permissao('usuarios_editar') && $p['id'] > 2 && (int)$p['qtd_usuarios'] === 0): ?>
                        <form action="<?= url('usuarios/salvar.php') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="excluir_perfil">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-soft-danger btn-sm excluir-perfil"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>APP.confirmarExcluir('.excluir-perfil', 'Deseja excluir este perfil?');</script>
<?php include INC . 'footer.php'; ?>