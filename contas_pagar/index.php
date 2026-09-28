<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('contas_pagar_ver');

$filtroStatus = $_GET['status'] ?? '';
$filtroDe = parse_data($_GET['de'] ?? '');
$filtroAte = parse_data($_GET['ate'] ?? '');
$hoje = hoje();

$sql = "SELECT cp.id, cp.categoria_id, cp.documento, cp.descricao, cp.parcela_numero, cp.valor,
               cp.vencimento, cp.data_pagamento, cp.valor_pago, cp.juros, cp.multa, cp.desconto, cp.status,
               cp.fornecedor_id, cp.forma_pagamento_id, cp.observacao, f.razao_social AS fornecedor_nome, cf.nome AS categoria_nome,
               fp.nome AS forma_nome
          FROM contas_pagar cp
          LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id
          LEFT JOIN categorias_financeiras cf ON cf.id = cp.categoria_id
          LEFT JOIN formas_pagamento fp ON fp.id = cp.forma_pagamento_id
         WHERE 1=1";
$params = [];
if ($filtroStatus !== '') {
    $sql .= ' AND cp.status = ?';
    $params[] = $filtroStatus;
}
if ($filtroDe) {
    $sql .= ' AND cp.vencimento >= ?';
    $params[] = $filtroDe;
}
if ($filtroAte) {
    $sql .= ' AND cp.vencimento <= ?';
    $params[] = $filtroAte;
}
$sql .= ' ORDER BY cp.vencimento, cp.id DESC LIMIT 3000';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

$aberto = $vencido = $pagoMes = 0.0;
foreach ($contas as $cp) {
    $pendente = !in_array($cp['status'], ['PAGO', 'CANCELADO'], true);
    $restante = max($cp['valor'] + $cp['juros'] + $cp['multa'] - $cp['desconto'] - $cp['valor_pago'], 0);
    if ($pendente) {
        $aberto += $restante;
        if ($cp['vencimento'] < $hoje) {
            $vencido += $restante;
        }
    }
    if ($cp['data_pagamento'] && date('m', strtotime($cp['data_pagamento'])) === date('m')) {
        $pagoMes += $cp['valor_pago'];
    }
}

$formas = db()->query('SELECT id, nome FROM formas_pagamento WHERE ativo = 1 ORDER BY nome')->fetchAll();
$fornecedores = db()->query('SELECT id, razao_social FROM fornecedores ORDER BY razao_social')->fetchAll();
$categorias = db()->query("SELECT id, nome FROM categorias_financeiras WHERE tipo = 'DESPESA' ORDER BY nome")->fetchAll();

$tituloPagina = 'Contas a pagar';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-receipt me-2"></i>Contas a pagar</h1>
        <span class="subtitulo">Compromissos financeiros e despesas</span>
    </div>
    <?php if (tem_permissao('contas_pagar_criar')): ?>
    <button class="btn btn-primary js-nova"><i class="bi bi-plus-lg me-1"></i>Nova conta</button>
    <?php endif; ?>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Em aberto</div><div class="stat-val text-warning"><?= formatar_moeda($aberto) ?></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Vencidas</div><div class="stat-val text-danger"><?= formatar_moeda($vencido) ?></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="stat-top text-muted">Pago no mês</div><div class="stat-val text-success"><?= formatar_moeda($pagoMes) ?></div></div>
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
            <table class="table table-hover table-geral" data-ordem="4">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Descrição</th>
                        <th>Fornecedor</th>
                        <th>Categoria</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Pago</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contas as $cp):
                        $restante = max($cp['valor'] + $cp['juros'] + $cp['multa'] - $cp['desconto'] - $cp['valor_pago'], 0);
                        $status = $cp['status'];
                        if (($status === 'PENDENTE' || $status === 'PARCIAL') && $cp['vencimento'] < $hoje) {
                            $status = 'VENCIDO';
                        }
                        $encargos = $cp['juros'] + $cp['multa'] - $cp['desconto'];
                    ?>
                    <tr>
                        <td class="text-muted"><?= (int)$cp['id'] ?></td>
                        <td>
                            <b><?= e($cp['descricao']) ?></b>
                            <?php if ($cp['documento']): ?><small class="text-muted d-block"><?= e($cp['documento']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($cp['fornecedor_nome'] ?? '-') ?></td>
                        <td><?= e($cp['categoria_nome'] ?? '-') ?></td>
                        <td><?= formatar_data($cp['vencimento']) ?><?= $cp['parcela_numero'] ? ' <span class="badge bg-light text-muted">' . e($cp['parcela_numero']) . '</span>' : '' ?></td>
                        <td class="text-end"><?= formatar_moeda($cp['valor']) ?></td>
                        <td class="text-end fw-semibold"><?= formatar_moeda($cp['valor'] + $encargos) ?></td>
                        <td class="text-end"><?= $cp['valor_pago'] > 0 ? formatar_moeda($cp['valor_pago']) : '-' ?></td>
                        <td><?= badge_status($status) ?></td>
                        <td class="text-end">
                            <?php if (tem_permissao('contas_pagar_baixar') && $restante > 0): ?>
                            <button class="btn btn-sm btn-success btn-icone js-pagar"
                                    title="Pagar"
                                    data-id="<?= (int)$cp['id'] ?>"
                                    data-desc="<?= e($cp['descricao']) ?>"
                                    data-fornecedor="<?= e($cp['fornecedor_nome'] ?? '-') ?>"
                                    data-restante="<?= round($restante, 2) ?>"
                                    data-juros="<?= e(obter_config('juros_padrao', '1')) ?>"
                                    data-multa="<?= e(obter_config('multa_padrao', '2')) ?>"><i class="bi bi-check-lg"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('contas_pagar_baixar') && $cp['valor_pago'] > 0 && $cp['status'] !== 'CANCELADO'): ?>
                            <button class="btn btn-sm btn-soft-danger btn-icone js-confirmar"
                                    title="Estornar pagamento"
                                    data-titulo="Estornar pagamento?"
                                    data-texto="O pagamento será revertido e a conta retornará a pendente."
                                    data-url="<?= url('contas_pagar/estornar.php') ?>"
                                    data-post='<?= e(json_encode(['id' => (int)$cp['id']])) ?>'><i class="bi bi-arrow-counterclockwise"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('contas_pagar_editar') && $restante > 0): ?>
                            <button class="btn btn-sm btn-soft btn-icone js-editar" title="Editar"
                                    data-id="<?= (int)$cp['id'] ?>"
                                    data-fornecedor="<?= (int)$cp['fornecedor_id'] ?>"
                                    data-categoria="<?= (int)$cp['categoria_id'] ?>"
                                    data-desc="<?= e($cp['descricao']) ?>"
                                    data-documento="<?= e($cp['documento'] ?? '') ?>"
                                    data-valor="<?= round($cp['valor'], 2) ?>"
                                    data-venc="<?= e($cp['vencimento']) ?>"
                                    data-forma="<?= (int)$cp['forma_pagamento_id'] ?>"
                                    data-obs="<?= e($cp['observacao'] ?? '') ?>"><i class="bi bi-pencil"></i></button>
                            <?php endif; ?>
                            <?php if (tem_permissao('contas_pagar_excluir') && $restante >= 0): ?>
                            <button class="btn btn-sm btn-soft-danger btn-icone js-confirmar"
                                    title="Excluir"
                                    data-titulo="Excluir conta?"
                                    data-texto="Esta ação não pode ser desfeita."
                                    data-url="<?= url('contas_pagar/salvar.php') ?>"
                                    data-post='<?= e(json_encode(['acao' => 'excluir', 'id' => (int)$cp['id']])) ?>'><i class="bi bi-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nova/Editar conta -->
<div class="modal fade" id="modalConta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= url('contas_pagar/salvar.php') ?>" class="modal-content js-converte">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" id="contaAcao" value="criar">
            <input type="hidden" name="id" id="contaId" value="0">
            <div class="modal-header">
                <h5 class="modal-title" id="contaTitulo"><i class="bi bi-receipt-cutoff me-2"></i>Nova conta a pagar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label">Descrição *</label>
                        <input type="text" class="form-control" name="descricao" id="cDescricao" required maxlength="255">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fornecedor</label>
                        <select class="form-select" name="fornecedor_id" id="cFornecedor">
                            <option value="">Selecione...</option>
                            <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= e($f['razao_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Categoria (despesa)</label>
                        <select class="form-select" name="categoria_id" id="cCategoria">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Valor *</label>
                        <input type="text" class="form-control" name="valor" id="cValor" data-moeda required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Vencimento *</label>
                        <input type="date" class="form-control" name="vencimento" id="cVenc" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Documento</label>
                        <input type="text" class="form-control" name="documento" id="cDocumento" maxlength="40">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Forma de pagamento</label>
                        <select class="form-select" name="forma_pagamento_id" id="cForma">
                            <option value="">Selecione...</option>
                            <?php foreach ($formas as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= e($f['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" name="observacao" id="cObs" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de pagamento -->
<div class="modal fade" id="modalPagar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= url('contas_pagar/baixar.php') ?>" class="modal-content js-converte">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="pgId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check2-circle me-2"></i>Pagar título</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><b id="pgDesc"></b><br><small class="text-muted" id="pgFornecedor"></small></p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Valor em aberto</label>
                        <input type="text" class="form-control" id="pgRestante" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Valor a pagar</label>
                        <input type="text" class="form-control" id="pgValor" name="valor_pago" data-moeda>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Juros (%)</label>
                        <input type="text" class="form-control" id="pgJuros" name="juros_pct" data-moeda value="0,00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Multa (R$)</label>
                        <input type="text" class="form-control" id="pgMulta" name="multa" data-moeda value="0,00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Desconto (R$)</label>
                        <input type="text" class="form-control" id="pgDesconto" name="desconto" data-moeda value="0,00">
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
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirmar pagamento</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(function () {
        const modalConta = new bootstrap.Modal(document.getElementById('modalConta'));
        const modalPagar = new bootstrap.Modal(document.getElementById('modalPagar'));

        $('.js-nova').on('click', function () {
            $('#contaAcao').val('criar'); $('#contaId').val('0');
            $('#contaTitulo').html('<i class="bi bi-receipt-cutoff me-2"></i>Nova conta a pagar');
            $('#cDescricao').val(''); $('#cFornecedor').val(''); $('#cCategoria').val(''); $('#cValor').val('');
            $('#cVenc').val(''); $('#cDocumento').val(''); $('#cForma').val(''); $('#cObs').val('');
            modalConta.show();
        });

        $('.js-editar').on('click', function () {
            const $t = $(this);
            $('#contaAcao').val('editar'); $('#contaId').val($t.data('id'));
            $('#contaTitulo').html('<i class="bi bi-receipt-cutoff me-2"></i>Editar conta #' + $t.data('id'));
            $('#cDescricao').val($t.data('desc'));
            $('#cFornecedor').val($t.data('fornecedor'));
            $('#cCategoria').val($t.data('categoria'));
            $('#cValor').val(APP.fmtNumero($t.data('valor'), 2));
            $('#cVenc').val($t.data('venc'));
            $('#cDocumento').val($t.data('documento'));
            $('#cForma').val($t.data('forma'));
            $('#cObs').val($t.data('obs'));
            modalConta.show();
        });

        $('.js-pagar').on('click', function () {
            const $t = $(this);
            const restante = Number($t.data('restante'));
            const jurosPct = Number(String($t.data('juros') || '0').replace(',', '.'));
            const multaPct = Number(String($t.data('multa') || '0').replace(',', '.'));
            $('#pgId').val($t.data('id'));
            $('#pgDesc').text($t.data('desc'));
            $('#pgFornecedor').text($t.data('fornecedor'));
            $('#pgRestante').val(APP.fmtMoeda(restante));
            $('#pgValor').val(APP.fmtNumero(restante + restante * jurosPct / 100 + restante * multaPct / 100, 2));
            $('#pgJuros').val(APP.fmtNumero(jurosPct, 2));
            $('#pgMulta').val(APP.fmtNumero(restante * multaPct / 100, 2));
            $('#pgDesconto').val('0,00');
            modalPagar.show();
        });
    });
</script>
<?php include INC . 'footer.php'; ?>