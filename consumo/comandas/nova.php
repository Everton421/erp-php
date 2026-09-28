<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('comandas_criar');

$pdo = db();
$mesaPre = (int)($_GET['mesa_id'] ?? 0);

$stmt = $pdo->query(
    "SELECT m.*,
            (SELECT COUNT(*) FROM comandas c WHERE c.mesa_id = m.id AND c.status = 'ABERTA') AS comanda_aberta
       FROM mesas m
      WHERE m.ativo = 1
      ORDER BY m.numero"
);
$mesas = $stmt->fetchAll();

$qtdLivres = 0;
$qtdReservadas = 0;
foreach ($mesas as $m) {
    if ((int)$m['comanda_aberta'] > 0) {
        continue;
    }
    if ($m['status'] === 'RESERVADA') {
        $qtdReservadas++;
    } else {
        $qtdLivres++;
    }
}

$tituloPagina = 'Abrir comanda';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt-cutoff me-2"></i>Abrir comanda</h1>
        <span class="subtitulo">
            <?= $qtdLivres ?> mesa(s) livre(s) e <?= $qtdReservadas ?> reservada(s) disponível(is)
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-soft">
            <i class="bi bi-grid-3x3-gap me-1"></i>Ver mesas
        </a>
        <?php if (tem_permissao('comandas_ver')): ?>
        <a href="<?= url('consumo/comandas/index.php') ?>" class="btn btn-light">
            <i class="bi bi-list-ul me-1"></i>Minhas comandas
        </a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= url('consumo/comandas/salvar.php') ?>" id="formAbrir">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="abrir">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header-custom"><i class="bi bi-grid-3x3-gap"></i>Escolha a mesa</div>
                <div class="card-body-custom">
                    <?php if (!$mesas): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox d-block fs-1 mb-2"></i>
                        Nenhuma mesa ativa cadastrada.
                    </div>
                    <?php endif; ?>

                    <div class="mesa-grid">
                        <?php foreach ($mesas as $m): ?>
                        <?php $ocupada = (int)$m['comanda_aberta'] > 0; ?>
                        <label class="mesa-card <?= $ocupada ? 'OCUPADA' : e($m['status']) ?>"
                               style="cursor:<?= $ocupada ? 'not-allowed' : 'pointer' ?>;
                                      opacity:<?= $ocupada ? '.55' : '1' ?>">
                            <input type="radio" class="d-none" name="mesa_id" value="<?= (int)$m['id'] ?>"
                                   <?= $ocupada ? 'disabled' : '' ?>
                                   <?= $mesaPre === (int)$m['id'] ? 'checked' : '' ?>>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="mesa-numero"><?= (int)$m['numero'] ?></div>
                                    <?php if ($m['nome']): ?>
                                    <div class="mesa-nome"><?= e($m['nome']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($ocupada): ?>
                                <span class="badge bg-secondary text-uppercase">Ocupada</span>
                                <?php else: ?>
                                <?= badge_status((string)$m['status']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="mesa-meta">
                                <span><i class="bi bi-people me-1"></i><?= (int)$m['capacidade'] ?> lugares</span>
                            </div>
                            <?php if (!$ocupada): ?>
                            <div class="mt-2">
                                <span class="badge text-bg-primary-subtle text-primary-emphasis">
                                    <i class="bi bi-cursor-fill me-1"></i>Selecionar
                                </span>
                            </div>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-card-text"></i>Detalhes da comanda</div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label" for="observacao">Observação da comanda</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="3" maxlength="255"
                                  placeholder="Ex.: aniversário, pedido compartilhado,talheres para 4 pessoas"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="btnAbrir">
                        <i class="bi bi-check-lg me-1"></i>Abrir comanda
                    </button>
                    <small class="text-muted d-block mt-2">
                        A mesa é ocupada imediatamente. Adicione os itens logo após a abertura.
                    </small>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    $(function () {
        // Destaque da mesa selecionada
        function atualizarDestaque() {
            $('.mesa-grid label').removeClass('border-primary').css('outline', '');
            const $sel = $('input[name="mesa_id"]:checked');
            if ($sel.length) {
                $sel.closest('label').css('outline', '2px solid #4f6ef7').css('outline-offset', '1px');
            }
        }
        $('input[name="mesa_id"]').on('change', atualizarDestaque);
        atualizarDestaque();

        $('#formAbrir').on('submit', function () {
            if (!$('input[name="mesa_id"]:checked').length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Escolha uma mesa',
                    text: 'Selecione a mesa que receberá a comanda.',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            $('#btnAbrir').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Abrindo...');
        });
    });
</script>
<?php include INC . 'footer.php'; ?>
