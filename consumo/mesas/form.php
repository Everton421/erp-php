<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('mesas_editar');

$id = (int)($_GET['id'] ?? 0);
$edicao = $id > 0;

$mesa = [
    'id' => 0, 'numero' => '', 'nome' => '', 'capacidade' => 4,
    'status' => 'LIVRE', 'ativo' => 1,
];

if ($edicao) {
    $mesa = mesa_buscar($id);
    if (!$mesa) {
        flash('danger', 'Mesa não encontrada.');
        redirecionar('consumo/mesas/index.php');
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM comandas WHERE mesa_id = ? AND status = 'ABERTA'");
    $stmt->execute([$id]);
    $comandaAberta = (int)$stmt->fetchColumn() > 0;
} else {
    $comandaAberta = false;
}

$proximoNumero = (int)db()->query('SELECT COALESCE(MAX(numero), 0) + 1 FROM mesas')->fetchColumn();

$tituloPagina = $edicao ? 'Editar mesa' : 'Nova mesa';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-grid-3x3-gap me-2"></i><?= $edicao ? 'Editar mesa' : 'Nova mesa' ?></h1>
        <span class="subtitulo"><?= $edicao ? 'Mesa #' . (int)$mesa['id'] : 'Cadastro de mesa' ?></span>
    </div>
    <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<form method="post" action="<?= url('consumo/mesas/salvar.php') ?>" id="formMesa">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= (int)$mesa['id'] ?>">

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-grid-3x3-gap"></i>Dados da mesa</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <label class="form-label obrigatorio" for="numero">Número</label>
                            <input type="text" class="form-control" id="numero" name="numero" data-mask="int"
                                   required maxlength="5" autofocus
                                   value="<?= $edicao ? (int)$mesa['numero'] : $proximoNumero ?>">
                        </div>
                        <div class="col-6 col-md-8">
                            <label class="form-label" for="nome">Nome / identificação</label>
                            <input type="text" class="form-control" id="nome" name="nome"
                                   value="<?= e((string)($mesa['nome'] ?? '')) ?>" maxlength="40"
                                   placeholder="Ex.: Varanda, Cantinho, Family">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label obrigatorio" for="capacidade">Capacidade</label>
                            <input type="text" class="form-control" id="capacidade" name="capacidade"
                                   data-mask="int" required maxlength="3" value="<?= (int)$mesa['capacidade'] ?>">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status"
                                    <?= $comandaAberta ? 'disabled' : '' ?>>
                                <option value="LIVRE" <?= $mesa['status'] === 'LIVRE' ? 'selected' : '' ?>>Livre</option>
                                <option value="RESERVADA" <?= $mesa['status'] === 'RESERVADA' ? 'selected' : '' ?>>Reservada</option>
                                <?php if (!$edicao): ?>
                                <option value="OCUPADA" <?= $mesa['status'] === 'OCUPADA' ? 'selected' : '' ?>>Ocupada</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($comandaAberta): ?>
                            <input type="hidden" name="status" value="<?= e((string)$mesa['status']) ?>">
                            <small class="text-muted">
                                <i class="bi bi-lock me-1"></i>
                                O status é controlado pela comanda aberta.
                            </small>
                            <?php else: ?>
                            <small class="text-muted">
                                Mesa ocupada não permite abrir nova comanda; use o botão de comanda na grade.
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="ativo">Ativa</label>
                            <select class="form-select" id="ativo" name="ativo">
                                <option value="1" <?= (int)$mesa['ativo'] === 1 ? 'selected' : '' ?>>Sim</option>
                                <option value="0" <?= (int)$mesa['ativo'] === 0 ? 'selected' : '' ?>>Não</option>
                            </select>
                            <small class="text-muted">Mesas inativas somem da grade do atendente.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-card-text"></i>Comanda atual</div>
                <div class="card-body-custom">
                    <?php if ($edicao): ?>
                    <?php
                    $stmt = db()->prepare(
                        "SELECT c.*, u.nome AS garcom_nome
                           FROM comandas c
                           JOIN usuarios u ON u.id = c.garcom_id
                          WHERE c.mesa_id = ? AND c.status = 'ABERTA'
                          ORDER BY c.id DESC LIMIT 1"
                    );
                    $stmt->execute([$id]);
                    $comanda = $stmt->fetch();
                    ?>
                    <?php if ($comanda): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Comanda <?= e($comanda['numero']) ?></div>
                            <small class="text-muted">
                                <?= e($comanda['garcom_nome']) ?> &middot;
                                aberta <?= formatar_tempo_decorrido((string)$comanda['data_abertura']) ?> atrás
                            </small>
                        </div>
                        <a href="<?= url('consumo/comandas/ver.php?id=' . (int)$comanda['id']) ?>"
                           class="btn btn-sm btn-warning">Abrir</a>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">
                        <i class="bi bi-check2-circle me-1 text-success"></i>
                        Nenhuma comanda aberta nesta mesa.
                    </p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted mb-0">Salve a mesa para gerenciar as comandas.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i><?= $edicao ? 'Salvar alterações' : 'Cadastrar mesa' ?>
        </button>
        <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-light">Cancelar</a>
    </div>
</form>
<?php include INC . 'footer.php'; ?>
