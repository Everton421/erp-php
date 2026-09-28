<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('usuarios_ver');

$id = (int)($_GET['id'] ?? 0);
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('usuarios_editar');
}

$usuario = ['id' => 0, 'nome' => '', 'usuario' => '', 'email' => '', 'telefone' => '',
    'perfil_id' => '', 'is_admin' => 0, 'status' => 1];
$meuId = (int)usuario_atual()['id'];

if ($edicao) {
    $linha = buscar_linha('usuarios', $id);
    if (!$linha) {
        flash('danger', 'Usuário não encontrado.');
        redirecionar('usuarios/index.php');
    }
    $usuario = $linha;
}

// Perfis
$perfis = db()->query('SELECT * FROM perfis ORDER BY nome')->fetchAll();

// Permissões do perfil (linha de base) e overrides individuais
$perfilPerms = [];
$userOverrides = [];
if (!empty($usuario['perfil_id'])) {
    $stmt = db()->prepare('SELECT chave FROM permissoes p JOIN perfil_permissoes pp ON pp.permissao_id = p.id WHERE pp.perfil_id = ? AND pp.permitido = 1');
    $stmt->execute([(int)$usuario['perfil_id']]);
    $perfilPerms = array_flip(array_column($stmt->fetchAll(), 'chave'));
}
if ($edicao) {
    $stmt = db()->prepare('SELECT p.chave, up.permitido FROM usuario_permissoes up JOIN permissoes p ON p.id = up.permissao_id WHERE up.usuario_id = ?');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $l) {
        $userOverrides[$l['chave']] = (int)$l['permitido'];
    }
}
$listaPerms = lista_permissoes();
$podeEditarPerms = tem_permissao('usuarios_editar');

$tituloPagina = $edicao ? 'Editar usuário' : 'Novo usuário';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-person-plus-fill me-2"></i><?= $edicao ? 'Editar usuário' : 'Novo usuário' ?></h1>
        <span class="subtitulo">Dados de acesso e permissões individuais</span>
    </div>
    <a href="<?= url('usuarios/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('usuarios/salvar.php') ?>" class="js-converte" id="formUsuario">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_user">
    <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">
    <input type="hidden" name="overrides" value="">

    <div class="row g-3">
        <div class="col-12 col-md-8">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-person"></i>Dados do usuário</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="nome">Nome completo</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= e($usuario['nome']) ?>" required maxlength="100">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="usuario">Nome de usuário</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" value="<?= e($usuario['usuario']) ?>" required maxlength="50" pattern="[a-zA-Z0-9_.-]+">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="email">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($usuario['email']) ?>" required maxlength="120">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefone">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" data-mask="telefone" value="<?= e($usuario['telefone']) ?>" maxlength="16">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="perfil_id">Perfil de acesso</label>
                            <select class="form-select" id="perfil_id" name="perfil_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($perfis as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (string)$usuario['perfil_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="1" <?= (int)$usuario['status'] === 1 ? 'selected' : '' ?>>Ativo</option>
                                <option value="0" <?= (int)$usuario['status'] === 0 ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="col-12 border-top pt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1" <?= (int)$usuario['is_admin'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_admin"><i class="bi bi-shield-lock me-1"></i>Administrador (acesso total, ignora perfil e permissões individuais)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-key"></i>Senha</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label <?= $edicao ? '' : 'obrigatorio' ?>" for="senha">
                                <?= $edicao ? 'Nova senha (deixe em branco para manter)' : 'Senha' ?>
                            </label>
                            <input type="password" class="form-control" id="senha" name="senha" <?= $edicao ? '' : 'required' ?> minlength="6" autocomplete="new-password">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label <?= $edicao ? '' : 'obrigatorio' ?>" for="senha2">Confirmar senha</label>
                            <input type="password" class="form-control" id="senha2" name="senha2" <?= $edicao ? '' : 'required' ?> minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="small text-muted mt-2">A senha é armazenada com hash seguro (bcrypt). Não é possível recuperá-la.</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-shield-check"></i>Permissões individuais</div>
                <div class="card-body-custom">
                    <p class="small text-muted mb-0">
                        Por padrão o usuário utiliza as permissões do perfil. Marque/desmarque permissões abaixo
                        para criar exceções individuais (<?= e('herdam o perfil') ?> quando não alteradas).
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-info-circle"></i>Informações</div>
                <div class="card-body-custom small text-muted">
                    <?php if ($edicao): ?>
                    <div>Cadastrado em: <b><?= formatar_datahora($usuario['criado_em']) ?></b></div>
                    <div class="mt-1">Último acesso: <b><?= formatar_datahora($usuario['ultimo_acesso']) ?: 'nunca' ?></b></div>
                    <?php else: ?>
                    <div>Cadastro com usuário ativo e perfil de acesso. O usuário poderá personalizar a senha após o primeiro acesso.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$edicao || (int)$usuario['is_admin'] === 0): ?>
    <div class="card mb-4">
        <div class="card-header-custom">
            <i class="bi bi-diagram-3"></i>Matriz de permissões (exceções individuais)
            <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="btnMarcarTodas">Marcar todas</button>
        </div>
        <div class="card-body-custom">
            <?php if ($edicao && empty($perfilPerms) && empty($listaPerms)): ?>
            <p class="text-muted">Sem permissões definidas.</p>
            <?php endif; ?>
            <?php if ((int)$usuario['is_admin'] === 0): ?>
            <?php foreach ($listaPerms as $modulo => $perms): ?>
            <div class="perm-modulo">
                <div class="perm-titulo">
                    <span><?= e(rotulo_modulo($modulo)) ?></span>
                    <small class="text-muted fw-normal"><?= count($perms) ?> permissões</small>
                </div>
                <div class="perm-itens">
                    <?php foreach ($perms as $chave => $descricao): ?>
                    <?php
                        $perfilGrant = isset($perfilPerms[$chave]);
                        $override = array_key_exists($chave, $userOverrides) ? (int)$userOverrides[$chave] : null;
                        $checked = $override !== null ? $override === 1 : $perfilGrant;
                    ?>
                    <div class="form-check">
                        <input class="form-check-input perm-check" type="checkbox"
                               id="perm_<?= e($chave) ?>" name="permissoes[<?= e($chave) ?>]" value="1"
                               <?= $checked ? 'checked' : '' ?>
                               data-perfil="<?= $perfilGrant ? '1' : '0' ?>"
                               <?= $podeEditarPerms ? '' : 'disabled' ?>>
                        <label class="form-check-label" for="perm_<?= e($chave) ?>"><?= e($descricao) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="alert alert-info mb-0">Este usuário é administrador e possui acesso total.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Salvar</button>
        <a href="<?= url('usuarios/index.php') ?>" class="btn btn-light">Cancelar</a>
    </div>
</form>

<?php if ((int)$usuario['id'] === (int)$meuId && isset($usuario['is_admin'])): ?>
<script>
    // Previne rebaixar o próprio admin acidentalmente
    $('#formUsuario').on('submit', function (e) {
        if (!$('#is_admin').is(':checked') && confirm('Você está removendo seu próprio acesso de administrador. Continuar?')) {
            return;
        }
        if (!$('#is_admin').is(':checked')) {
            e.preventDefault();
            Swal.fire({
                title: 'Atenção',
                text: 'Você não pode remover seu próprio acesso de administrador.',
                icon: 'warning'
            });
        }
    });
</script>
<?php endif; ?>

<script>
    $(function () {
        $('#btnMarcarTodas').on('click', function () {
            $('.perm-check').each(function () {
                $(this).prop('checked', true);
            });
        });

        $('#formUsuario').on('submit', function () {
            const overrides = [];
            $('.perm-check').each(function () {
                const base = $(this).data('perfil') === '1';
                const atual = $(this).is(':checked');
                if (base !== atual) {
                    overrides.push({ chave: this.name.replace(/^permissoes\[|\]$/g, ''), permitido: atual ? 1 : 0 });
                }
            });
            $('input[name=overrides]').val(JSON.stringify(overrides));
        });
    });
</script>
<?php include INC . 'footer.php'; ?>