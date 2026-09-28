<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('mesas_ver');

$pdo = db();
$statusFiltro = (string)($_GET['status'] ?? 'todos');

$sql = "SELECT m.*,
               c.id AS comanda_id, c.numero AS comanda_numero, c.total AS comanda_total,
               c.data_abertura AS comanda_abertura, c.status AS comanda_status,
               c.status_pagamento, g.nome AS garcom_nome
          FROM mesas m
          LEFT JOIN comandas c ON c.mesa_id = m.id AND c.status = 'ABERTA'
          LEFT JOIN usuarios g ON g.id = c.garcom_id
         WHERE 1=1";
$params = [];
if ($statusFiltro !== 'todos') {
    $sql .= ' AND m.status = ?';
    $params[] = $statusFiltro;
}
$sql .= ' ORDER BY m.numero';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mesas = $stmt->fetchAll();

$resumo = $pdo->query(
    "SELECT m.status, COUNT(*) AS total
       FROM mesas m WHERE m.ativo = 1 GROUP BY m.status"
)->fetchAll();

$contagem = ['LIVRE' => 0, 'OCUPADA' => 0, 'RESERVADA' => 0];
foreach ($resumo as $linha) {
    if (isset($contagem[$linha['status']])) {
        $contagem[$linha['status']] = (int)$linha['total'];
    }
}
$totalAtivas = array_sum($contagem);
$inativas = (int)$pdo->query('SELECT COUNT(*) FROM mesas WHERE ativo = 0')->fetchColumn();

$tituloPagina = 'Mesas';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-grid-3x3-gap me-2"></i>Mesas</h1>
        <span class="subtitulo">
            <?= $totalAtivas ?> mesa(s) ativa(s) &middot;
            <?= $contagem['LIVRE'] ?> livre(s) &middot;
            <?= $contagem['OCUPADA'] ?> ocupada(s) &middot;
            <?= $contagem['RESERVADA'] ?> reservada(s)
            <?= $inativas > 0 ? ' &middot; ' . $inativas . ' inativa(s)' : '' ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('consumo/mesas/index.php') ?>" class="btn btn-soft" data-atualizar>
            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
        </a>
        <?php if (tem_permissao('mesas_editar')): ?>
        <a href="<?= url('consumo/mesas/form.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nova mesa
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <label class="small text-muted mb-0">Filtrar:</label>
        <div class="btn-group btn-group-sm">
            <a href="<?= url('consumo/mesas/index.php?status=todos') ?>"
               class="btn <?= $statusFiltro === 'todos' ? 'btn-primary' : 'btn-light' ?>">Todas</a>
            <a href="<?= url('consumo/mesas/index.php?status=LIVRE') ?>"
               class="btn <?= $statusFiltro === 'LIVRE' ? 'btn-primary' : 'btn-light' ?>">Livres</a>
            <a href="<?= url('consumo/mesas/index.php?status=OCUPADA') ?>"
               class="btn <?= $statusFiltro === 'OCUPADA' ? 'btn-primary' : 'btn-light' ?>">Ocupadas</a>
            <a href="<?= url('consumo/mesas/index.php?status=RESERVADA') ?>"
               class="btn <?= $statusFiltro === 'RESERVADA' ? 'btn-primary' : 'btn-light' ?>">Reservadas</a>
        </div>
        <div class="ms-auto small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            A grade é atualizada automaticamente a cada 15 segundos.
        </div>
    </div>
</div>

<div class="mesa-grid" data-mesa-grid data-intervalo="15" data-status="<?= e($statusFiltro) ?>">
    <?php foreach ($mesas as $m): ?>
    <?php
    $destino = $m['comanda_id']
        ? url('consumo/comandas/ver.php?id=' . (int)$m['comanda_id'])
        : url('consumo/mesas/form.php?id=' . (int)$m['id']);
    ?>
    <div class="mesa-card <?= e($m['status']) ?><?= (int)$m['ativo'] === 1 ? '' : ' INATIVA' ?>" data-mesa-id="<?= (int)$m['id'] ?>">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="mesa-numero"><?= (int)$m['numero'] ?></div>
                <?php if ($m['nome']): ?>
                <div class="mesa-nome"><?= e($m['nome']) ?></div>
                <?php endif; ?>
            </div>
            <?= badge_status((string)$m['status']) ?>
        </div>

        <div class="mesa-meta">
            <span><i class="bi bi-people me-1"></i><?= (int)$m['capacidade'] ?> lugares</span>
            <?php if ((int)$m['ativo'] === 0): ?>
            <span class="badge bg-secondary">Inativa</span>
            <?php endif; ?>
        </div>

        <?php if ($m['comanda_id']): ?>
        <div class="mesa-meta">
            <span><i class="bi bi-receipt me-1"></i>Comanda <?= e($m['comanda_numero']) ?></span>
        </div>
        <div class="mesa-meta">
            <span><i class="bi bi-person me-1"></i><?= e($m['garcom_nome']) ?></span>
            <span><i class="bi bi-clock me-1"></i><?= formatar_tempo_decorrido((string)$m['comanda_abertura']) ?></span>
        </div>
        <?php endif; ?>

        <div class="mesa-rodape">
            <span class="valor">
                <?= $m['comanda_id'] ? formatar_moeda($m['comanda_total']) : '&nbsp;' ?>
            </span>
            <a href="<?= $destino ?>" class="btn btn-sm <?= $m['comanda_id'] ? 'btn-warning' : 'btn-primary' ?>">
                <?php if ($m['comanda_id']): ?>
                <i class="bi bi-receipt-cutoff me-1"></i>Abrir
                <?php elseif ($m['status'] === 'RESERVADA'): ?>
                <i class="bi bi-pencil me-1"></i>Editar
                <?php else: ?>
                <i class="bi bi-plus-lg me-1"></i>Comanda
                <?php endif; ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (!$mesas): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-grid-3x3-gap d-block fs-1 mb-2"></i>
                Nenhuma mesa encontrada.
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (tem_permissao('mesas_editar')): ?>
<div class="card mt-3 no-print">
    <div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-list-ul me-2"></i>Todas as mesas cadastradas</h2>
        <div class="table-responsive">
            <table class="table table-hover table-geral responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Nome</th>
                        <th class="text-center">Capacidade</th>
                        <th>Status</th>
                        <th>Comanda aberta</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mesas as $m): ?>
                    <tr>
                        <td class="fw-semibold"><?= (int)$m['numero'] ?></td>
                        <td><?= e($m['nome'] ?: '-') ?></td>
                        <td class="text-center"><?= (int)$m['capacidade'] ?></td>
                        <td>
                            <?= badge_status((string)$m['status']) ?>
                            <?= (int)$m['ativo'] === 0 ? ' <span class="badge bg-secondary">Inativa</span>' : '' ?>
                        </td>
                        <td>
                            <?php if ($m['comanda_id']): ?>
                            <a href="<?= url('consumo/comandas/ver.php?id=' . (int)$m['comanda_id']) ?>">
                                <?= e($m['comanda_numero']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap no-print">
                            <a href="<?= url('consumo/mesas/form.php?id=' . (int)$m['id']) ?>"
                               class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($m['status'] === 'RESERVADA' && !$m['comanda_id']): ?>
                            <form action="<?= url('consumo/mesas/acao.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="mesa_id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Liberar reserva">
                                    <i class="bi bi-unlock"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (tem_permissao('mesas_excluir') && !$m['comanda_id']): ?>
                            <form action="<?= url('consumo/mesas/salvar.php') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="acao-btn btn-soft-danger excluir-mesa"
                                        data-bs-toggle="tooltip" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
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
<?php endif; ?>

<script>
    APP.confirmarExcluir('.excluir-mesa', 'Deseja excluir esta mesa? O histórico de comandas será preservado.');
    $('[data-atualizar]').on('click', function (e) {
        e.preventDefault();
            CONSUMO.mesas.atualizar();
    });
</script>
<?php include INC . 'footer.php'; ?>
