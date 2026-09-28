<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('contas_receber_ver');

$filtroStatus = $_GET['status'] ?? '';
$filtroDe = parse_data($_GET['de'] ?? '');
$filtroAte = parse_data($_GET['ate'] ?? '');
$hoje = hoje();

$sql = "SELECT cr.id, cr.documento, cr.parcela_numero, cr.valor, cr.vencimento, cr.data_pagamento,
               cr.valor_pago, cr.juros, cr.multa, cr.desconto, cr.status, cr.cliente_id,
               c.nome AS cliente_nome,
               fp.nome AS forma_nome
          FROM contas_receber cr
          LEFT JOIN clientes c ON c.id = cr.cliente_id
          LEFT JOIN formas_pagamento fp ON fp.id = cr.forma_pagamento_id
         WHERE 1=1";
$params = [];
if ($filtroStatus !== '') {
    $sql .= ' AND cr.status = ?';
    $params[] = $filtroStatus;
}
if ($filtroDe) {
    $sql .= ' AND cr.vencimento >= ?';
    $params[] = $filtroDe;
}
if ($filtroAte) {
    $sql .= ' AND cr.vencimento <= ?';
    $params[] = $filtroAte;
}
$sql .= ' ORDER BY cr.vencimento, cr.id DESC LIMIT 3000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

// Métricas
$aberto = $vencido = $recebidoMes = 0.0;
foreach ($contas as $cr) {
    $pendente = !in_array($cr['status'], ['PAGO', 'CANCELADO'], true);
    $restante = max($cr['valor'] + $cr['juros'] + $cr['multa'] - $cr['desconto'] - $cr['valor_pago'], 0);
    if ($pendente) {
        $aberto += $restante;
        if ($cr['vencimento'] < $hoje) {
            $vencido += $restante;
        }
    }
    if ($cr['data_pagamento'] && date('m', strtotime($cr['data_pagamento'])) === date('m')) {
        $recebidoMes += $cr['valor_pago'];
    }
}

$formas = db()->query('SELECT id, nome FROM formas_pagamento WHERE ativo = 1 ORDER BY nome')->fetchAll();

$tituloPagina = 'Contas a receber';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-coin me-2"></i>Contas a receber</h1>
        <span class="subtitulo">Duplicatas e parcelas de vendas</span>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Em aberto</div><div class="stat-val text-warning"><?= formatar_moeda($aberto) ?></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Vencidas</div><div class="stat-val text-danger"><?= formatar_moeda($vencido) ?></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Recebido no mês</div><div class="stat-val text-success"><?= formatar_moeda($recebidoMes) ?></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">Todos</option>
                    <option value="PENDENTE" <?= $filtroStatus === 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                    <option value="VENCIDO" <?= $filtroStatus === 'VENCIDO' ? 'selected' : '' ?>>Vencido</option>
                    <option value="PARCIAL" <?= $filtroStatus === 'PARCIAL' ? 'selected' : '' ?>>Parcial</option>
                    <option value="PAGO" <?= $filtroStatus === 'PAGO' ? 'selected' : '' ?>>Pago</option>
                    <option value="CANCELADO" <?= $filtroStatus === 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Vencimento de</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= e($filtroDe ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($filtroAte ?? '') ?>">
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-sm btn-soft w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral" data-ordem="3">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Doc.</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Juros/Multa</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Recebido</th>
                        <th>Forma</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contas as $cr):
                        $restante = max($cr['valor'] + $cr['juros'] + $cr['multa'] - $cr['desconto'] - $cr['valor_pago'], 0);
                        $status = $cr['status'];
                        if (($status === 'PENDENTE' || $status === 'PARCIAL') && $cr['vencimento'] < $hoje) {
                            $status = 'VENCIDO';
                        }
                        $encargos = $cr['juros'] + $cr['multa'] - $cr['desconto'];
                    ?>
                    <tr>
                        <td class="text-muted"><?= (int)$cr['id'] ?></td>
                        <td><?= e($cr['cliente_nome'] ?? '-') ?></td>
                        <td><?= e($cr['documento'] ?? '-') ?><?= $cr['parcela_numero'] ? ' <span class="badge bg-light text-muted">' . e($cr['parcela_numero']) . '</span>' : '' ?></td>
                        <td><?= formatar_data($cr['vencimento']) ?></td>
                        <td class="text-end"><?= formatar_moeda($cr['valor']) ?></td>
                        <td class="text-end"><?= $encargos != 0 ? formatar_moeda($encargos) : '-' ?></td>
                        <td class="text-end fw-semibold" data-total="<?= round($cr['valor'] + $encargos, 2) ?>"><?= formatar_moeda($cr['valor'] + $encargos) ?></td>
                        <td class="text-end"><?= $cr['valor_pago'] > 0 ? formatar_moeda($cr['valor_pago']) : '-' ?></td>
                        <td><?= e($cr['forma_nome'] ?? '-') ?></td>
                        <td><?= badge_status($status) ?></td>
                        <td class="text-end">
                            <?php if (tem_permissao('contas_receber_baixar') && $restante > 0): ?>
                            <button class="btn btn-sm btn-success btn-icone js-baixar"
                                    title="Receber"
                                    data-id="<?= (int)$cr['id'] ?>"
                                    data-cliente="<?= e($cr['cliente_nome'] ?? 'Consumidor final') ?>"
                                    data-doc="<?= e($cr['documento'] ?? '') ?>"
                                    data-restante="<?= round($restante, 2) ?>"
                                    data-juros="<?= e(obter_config('juros_padrao', '1')) ?>"
                                    data-multa="<?= e(obter_config('multa_padrao', '2')) ?>"><i class="bi bi-check-lg"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('contas_receber_baixar') && $cr['valor_pago'] > 0 && $cr['status'] !== 'CANCELADO'): ?>
                            <button class="btn btn-sm btn-soft-danger btn-icone js-confirmar"
                                    title="Estornar recebimento"
                                    data-titulo="Estornar recebimento?"
                                    data-texto="O recebimento será revertido e a conta retornará a pendente."
                                    data-url="<?= url('contas_receber/estornar.php') ?>"
                                    data-post='<?= e(json_encode(['id' => (int)$cr['id']])) ?>'><i class="bi bi-arrow-counterclockwise"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de baixa -->
<?php if (tem_permissao('contas_receber_baixar')): ?>
<div class="modal fade" id="modalBaixa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= url('contas_receber/baixar.php') ?>" class="modal-content js-converte">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="baixaId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check2-circle me-2"></i>Receber título</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><b id="baixaCliente"></b><br><small class="text-muted" id="baixaDoc"></small></p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Valor em aberto</label>
                        <input type="text" class="form-control" id="baixaRestante" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Valor a receber</label>
                        <input type="text" class="form-control" id="baixaReceber" name="valor_recebido" data-moeda>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Juros (%)</label>
                        <input type="text" class="form-control" id="baixaJuros" name="juros_pct" data-moeda value="0,00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Multa (R$)</label>
                        <input type="text" class="form-control" id="baixaMulta" name="multa" data-moeda value="0,00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Desconto (R$)</label>
                        <input type="text" class="form-control" id="baixaDesconto" name="desconto" data-moeda value="0,00">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Forma de pagamento</label>
                        <select class="form-select" name="forma_pagamento_id">
                            <?php foreach ($formas as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= e($f['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Data do pagamento</label>
                        <input type="date" class="form-control" name="data_pagamento" value="<?= hoje() ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" name="observacao" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirmar recebimento</button>
            </div>
        </form>
    </div>
</div>
<script>
    $(function () {
        const modal = new bootstrap.Modal(document.getElementById('modalBaixa'));
        $('.js-baixar').on('click', function () {
            const $t = $(this);
            const restante = Number($t.data('restante'));
            const jurosPct = Number(String($t.data('juros') || '0').replace(',', '.'));
            const multaPct = Number(String($t.data('multa') || '0').replace(',', '.'));
            const juros = restante * jurosPct / 100;
            const multa = restante * multaPct / 100;
            $('#baixaId').val($t.data('id'));
            $('#baixaCliente').text($t.data('cliente'));
            $('#baixaDoc').text($t.data('doc'));
            $('#baixaRestante').val(APP.fmtMoeda(restante));
            $('#baixaReceber').val(APP.fmtNumero(restante + juros + multa, 2));
            $('#baixaJuros').val(APP.fmtNumero(jurosPct, 2));
            $('#baixaMulta').val(APP.fmtNumero(multa, 2));
            $('#baixaDesconto').val('0,00');
            modal.show();
        });
    });
</script>
<?php endif; ?>
<?php include INC . 'footer.php'; ?>