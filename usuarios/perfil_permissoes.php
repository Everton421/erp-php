<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('usuarios_editar');

$id = (int)($_GET['id'] ?? 0);
$perfil = buscar_linha('perfis', $id);
if (!$perfil) {
    flash('danger', 'Perfil não encontrado.');
    redirecionar('usuarios/perfis.php');
}

$perfilPerms = [];
$stmt = db()->prepare('SELECT chave FROM permissoes p JOIN perfil_permissoes pp ON pp.permissao_id = p.id WHERE pp.perfil_id = ? AND pp.permitido = 1');
$stmt->execute([$id]);
$perfilPerms = array_flip(array_column($stmt->fetchAll(), 'chave'));

$gerente = (int)$perfil['id'] === 2;

$tituloPagina = 'Permissões - ' . $perfil['nome'];
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-sliders me-2"></i>Permissões do perfil</h1>
        <span class="subtitulo"><?= e($perfil['nome']) ?></span>
    </div>
    <a href="<?= url('usuarios/perfis.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('usuarios/salvar.php') ?>" id="formPerm">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_perfil">
    <input type="hidden" name="id" value="<?= (int)$perfil['id'] ?>">

    <?php if ($gerente): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>Algumas áreas (usuários, configurações e logs) ficam fora da matriz do Gerente por padrão de segurança.
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header-custom">
            <span>Matriz de permissões</span>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-success btn-sm" id="marcarTudo">Marcar tudo</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="desmarcarTudo">Desmarcar tudo</button>
            </div>
        </div>
        <div class="card-body-custom">
            <?php foreach (lista_permissoes() as $modulo => $perms): ?>
            <?php if ($gerente && in_array($modulo, ['usuarios', 'config', 'logs'], true)) { continue; } ?>
            <div class="perm-modulo">
                <div class="perm-titulo">
                    <span><?= e(rotulo_modulo($modulo)) ?></span>
                    <button type="button" class="btn btn-link btn-sm p-0 marcar-modulo" data-modulo="true">Marcar módulo</button>
                </div>
                <div class="perm-itens">
                    <?php foreach ($perms as $chave => $descricao): ?>
                    <div class="form-check">
                        <input class="form-check-input perm-check" type="checkbox" name="perm[]" value="<?= e($chave) ?>" id="p_<?= e($chave) ?>" <?= isset($perfilPerms[$chave]) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="p_<?= e($chave) ?>"><?= e($descricao) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Salvar permissões</button>
        <a href="<?= url('usuarios/perfis.php') ?>" class="btn btn-light">Cancelar</a>
    </div>
</form>

<script>
    $(function () {
        $('#marcarTudo').on('click', () => $('.perm-check').prop('checked', true));
        $('#desmarcarTudo').on('click', () => $('.perm-check').prop('checked', false));
        $('.marcar-modulo').on('click', function () {
            $(this).closest('.perm-modulo').find('.perm-check').prop('checked', true);
        });
    });
</script>
<?php include INC . 'footer.php'; ?>