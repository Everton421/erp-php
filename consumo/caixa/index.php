<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('caixa_consumo_ver');

$pdo = db();
$statusFiltro = (string)($_GET['status'] ?? 'ABERTO');
$periodo = (string)($_GET['periodo'] ?? 'hoje');

$where = ["c.status = 'FECHADA'"];
$params = [];

if ($statusFiltro === 'ABERTO') {
    $where[] = "c.status_pagamento <> 'PAGO'";
} elseif ($statusFiltro === 'PAGO') {
    $where[] = "c.status_pagamento = 'PAGO'";
}
if ($periodo === 'hoje') {
    $where[] = 'DATE(c.data_fechamento) = CURDATE()';
} elseif ($periodo === 'semana') {
    $where[] = 'c.data_fechamento >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
} elseif ($periodo === 'mes') {
    $where[] = 'c.data_fechamento >= DATE_FORMAT(CURDATE(), "%Y-%m-01 00:00:00")';
}

$sql = "SELECT c.*, m.numero AS mesa_numero, m.nome AS mesa_nome,
               u.nome AS garcom_nome, fp.nome AS forma_nome
          FROM comandas c
          JOIN mesas m ON m.id = c.mesa_id
          JOIN usuarios u ON u.id = c.garcom_id
          LEFT JOIN formas_pagamento fp ON fp.id = c.forma_pagamento_id
         WHERE " . implode(' AND ', $where) . '
         ORDER BY c.data_fechamento DESC, c.id DESC
         LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$comandas = $stmt->fetchAll();

$aReceber = 0.0;
$aPagarHoje = 0.0;
$qtdAReceber = 0;
foreach ($comandas as $c) {
    $saldo = max(0, (float)$c['total'] - (float)$c['valor_pago']);
    if ($saldo > 0) {
        $aReceber += $saldo;
        $qtdAReceber++;
    } else {
        $aPagarHoje += (float)$c['valor_pago'];
    }
}

$formas = $pdo->query('SELECT id, nome FROM formas_pagamento WHERE ativo = 1 ORDER BY nome')->fetchAll();

$selecionadaId = (int)($_GET['comanda'] ?? 0);
if ($selecionadaId <= 0 && $comandas) {
    foreach ($comandas as $c) {
        if ((string)$c['status_pagamento'] !== 'PAGO') {
            $selecionadaId = (int)$c['id'];
            break;
        }
    }
    if ($selecionadaId <= 0) {
        $selecionadaId = (int)$comandas[0]['id'];
    }
}
$selecionada = comanda_buscar($selecionadaId);
$pagamentosSel = [];
if ($selecionada) {
    $stmt = $pdo->prepare(
        'SELECT pg.*, fp.nome AS forma_nome
           FROM comanda_pagamentos pg
           JOIN formas_pagamento fp ON fp.id = pg.forma_pagamento_id
          WHERE pg.comanda_id = ?
          ORDER BY pg.id'
    );
    $stmt->execute([$selecionadaId]);
    $pagamentosSel = $stmt->fetchAll();
}
$saldoSelecionada = $selecionada
    ? max(0, (float)$selecionada['total'] - (float)$selecionada['valor_pago'])
    : 0.0;

$tituloPagina = 'Caixa do ' . rotulo_consumo();
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-coin me-2"></i>Caixa do <?= e(rotulo_consumo()) ?></h1>
        <span class="subtitulo">
            <?= $qtdAReceber ?> comanda(s) com saldo em aberto &middot;
            a receber <?= formatar_moeda($aReceber) ?> &middot;
            recebido <?= formatar_moeda($aPagarHoje) ?>
        </span>
    </div>
    <a href="<?= url('consumo/comandas/index.php?status=FECHADA') ?>" class="btn btn-light">
        <i class="bi bi-receipt-cutoff me-1"></i>Comandas fechadas
    </a>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-6 col-md-4">
                        <label class="form-label" for="status">Situação</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ABERTO" <?= $statusFiltro === 'ABERTO' ? 'selected' : '' ?>>Em aberto</option>
                            <option value="PAGO" <?= $statusFiltro === 'PAGO' ? 'selected' : '' ?>>Pagas</option>
                            <option value="TODAS" <?= $statusFiltro === 'TODAS' ? 'selected' : '' ?>>Todas</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label" for="periodo">Período</label>
                        <select class="form-select" id="periodo" name="periodo">
                            <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                            <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Últimos 7 dias</option>
                            <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Mês atual</option>
                            <option value="todos" <?= $periodo === 'todos' ? 'selected' : '' ?>>Todo o período</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                        <a href="<?= url('consumo/caixa/index.php') ?>" class="btn btn-light">Limpar</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-geral responsive nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Comanda</th>
                                <th>Mesa</th>
                                <th>Fechamento</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Pago</th>
                                <th class="text-end">Saldo</th>
                                <th class="no-print"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$comandas): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                    Nenhuma comanda fechada no período selecionado.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($comandas as $c): ?>
                            <?php
                            $saldo = max(0, (float)$c['total'] - (float)$c['valor_pago']);
                            $ativa = (int)$c['id'] === $selecionadaId;
                            ?>
                            <tr class="<?= $ativa ? 'table-active' : '' ?>">
                                <td class="fw-semibold">
                                    <?= e($c['numero']) ?>
                                    <?= badge_status((string)$c['status_pagamento']) ?>
                                </td>
                                <td>
                                    <?= (int)$c['mesa_numero'] ?>
                                    <small class="text-muted d-block"><?= e($c['garcom_nome']) ?></small>
                                </td>
                                <td><?= formatar_datahora((string)$c['data_fechamento']) ?></td>
                                <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$c['total'] ?></td>
                                <td class="text-end text-success" data-moeda-exibir><?= (float)$c['valor_pago'] ?></td>
                                <td class="text-end <?= $saldo > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"
                                    data-moeda-exibir><?= $saldo ?></td>
                                <td class="text-end no-print">
                                    <a href="<?= url('consumo/caixa/index.php?status=' . e($statusFiltro)
                                        . '&periodo=' . e($periodo) . '&comanda=' . (int)$c['id']) ?>"
                                       class="btn btn-sm <?= $saldo > 0 && tem_permissao('caixa_consumo_pagar') ? 'btn-primary' : 'btn-soft' ?>">
                                        <?= $saldo > 0 ? 'Cobrar' : 'Ver' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header-custom"><i class="bi bi-cash-stack"></i>Recebimento</div>
            <div class="card-body-custom">
                <?php if (!$selecionada): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-cash-coin d-block fs-1 mb-2"></i>
                    Selecione uma comanda para registrar o pagamento.
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="h5 mb-0">Comanda <?= e($selecionada['numero']) ?></div>
                        <small class="text-muted">
                            Mesa <?= (int)$selecionada['mesa_numero'] ?>
                            <?= $selecionada['mesa_nome'] ? ' · ' . e($selecionada['mesa_nome']) : '' ?>
                            &middot; <?= e($selecionada['garcom_nome']) ?>
                        </small>
                    </div>
                    <?= badge_status((string)$selecionada['status_pagamento']) ?>
                </div>

                <input type="hidden" name="comanda_id" value="<?= (int)$selecionada['id'] ?>">
                <input type="hidden" name="comanda_total" value="<?= (float)$selecionada['total'] ?>">
                <input type="hidden" name="comanda_saldo" value="<?= $saldoSelecionada ?>">

                <div class="pg-total mb-3">
                    <div class="linha">
                        <span class="rot">Total da comanda</span>
                        <span class="val"><?= formatar_moeda($selecionada['total']) ?></span>
                    </div>
                    <div class="linha">
                        <span class="rot">Já pago</span>
                        <span class="val text-success"><?= formatar_moeda($selecionada['valor_pago']) ?></span>
                    </div>
                    <?php if ((float)$selecionada['troco'] > 0): ?>
                    <div class="linha">
                        <span class="rot">Troco anterior</span>
                        <span class="val"><?= formatar_moeda($selecionada['troco']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="linha destaque">
                        <span class="rot">Saldo a receber</span>
                        <span class="val" data-pg-resumo><?= formatar_moeda($saldoSelecionada) ?></span>
                    </div>
                </div>

                <?php if ($pagamentosSel): ?>
                <div class="mb-3">
                    <h3 class="h6 text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.5px">
                        Pagamentos registrados
                    </h3>
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php foreach ($pagamentosSel as $pg): ?>
                        <tr>
                            <td>
                                <?= e($pg['forma_nome']) ?>
                                <?php if ((int)$pg['qtde_parcelas'] > 1): ?>
                                <span class="badge bg-info"><?= (int)$pg['qtde_parcelas'] ?>x</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= formatar_moeda($pg['valor']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($saldoSelecionada <= 0): ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check2-circle me-1"></i>
                    Esta comanda está quitada.
                </div>
                <?php elseif (!tem_permissao('caixa_consumo_pagar')): ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-lock me-1"></i>
                    Você não tem permissão para registrar pagamentos.
                </div>
                <?php else: ?>
                <form id="formPagamento" onsubmit="return false;">
                    <h3 class="h6 text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.5px">
                        Formas de pagamento
                    </h3>

                    <div class="pg-linha">
                        <div>
                            <label class="form-label small">Forma</label>
                            <select class="form-select" name="pg_forma">
                                <?php foreach ($formas as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= $f['nome'] === 'Dinheiro' ? 'selected' : '' ?>>
                                    <?= e($f['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small">Valor (R$)</label>
                            <input type="text" class="form-control" name="pg_valor" data-mask="moeda"
                                   value="<?= formatar_numero($saldoSelecionada, 2) ?>" autocomplete="off">
                        </div>
                        <div>
                            <label class="form-label small">Parcelas</label>
                            <select class="form-select" name="pg_parcelas">
                                <option value="1">À vista</option>
                                <option value="2">2x</option>
                                <option value="3">3x</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-soft-danger js-remove-pg" title="Remover linha">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-soft btn-sm js-add-pg">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar forma
                        </button>
                        <button type="button" class="btn btn-soft btn-sm js-pg-troco-exato">
                            <i class="bi bi-calculator me-1"></i>Completar saldo
                        </button>
                    </div>

                    <div class="pg-total mb-3">
                        <div class="linha">
                            <span class="rot">Total informado</span>
                            <span class="val" data-pg-pago><?= formatar_moeda(0) ?></span>
                        </div>
                        <div class="linha">
                            <span class="rot">Troco</span>
                            <span class="val troco" data-pg-troco><?= formatar_moeda(0) ?></span>
                        </div>
                        <div class="linha">
                            <span class="rot">Falta</span>
                            <span class="val" data-pg-falta><?= formatar_moeda($saldoSelecionada) ?></span>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="button" class="btn btn-primary js-pg-confirmar">
                            <i class="bi bi-check-lg me-1"></i>Confirmar pagamento
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Pagamento parcial gera conta a receber com o saldo restante.
                        Excesso de pagamento é registrado como troco.
                    </small>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // O seletor de masked input do app converte ao enviar; aqui só normalizamos
    // o campo de valor para que o cálculo do troco use o número real.
    $(function () {
        $('.pg-linha [name="pg_valor"]').each(function () {
            $(this).val(APP.fmtNumero(APP.paraNumero($(this).val()), 2));
        });
    });
</script>
<?php include INC . 'footer.php'; ?>
