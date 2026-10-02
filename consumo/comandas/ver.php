<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('comandas_ver');

$id = (int)($_GET['id'] ?? 0);
$comanda = comanda_buscar($id);
if (!$comanda) {
    flash('danger', 'Comanda não encontrada.');
    redirecionar('consumo/comandas/index.php');
}

$podeManipular = (string)$comanda['status'] === 'ABERTA' && comanda_pode_manipular($comanda);
$itens = comanda_itens($id);
$itensAtivos = array_values(array_filter($itens, fn($i) => (string)$i['status'] !== 'CANCELADO'));
$producaoAtiva = consumo_producao_ativa();
$pendentes = $producaoAtiva ? comanda_itens_pendentes($id) : [];
$prontos = $producaoAtiva
    ? array_values(array_filter($itens, fn($i) => (string)$i['status'] === 'PRONTO'))
    : [];
$emPreparo = $producaoAtiva
    ? array_values(array_filter($itens, fn($i) => (string)$i['status'] === 'PREPARANDO'))
    : [];

$statusBorda = (string)$comanda['status'] === 'ABERTA' ? '' : ' opacity-75';

$tituloPagina = 'Comanda ' . $comanda['numero'];
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1>
            <i class="bi bi-receipt-cutoff me-2"></i>Comanda <?= e($comanda['numero']) ?>
            <?= badge_status((string)$comanda['status']) ?>
            <?= badge_status((string)$comanda['status_pagamento']) ?>
        </h1>
        <span class="subtitulo">
            <?= e(mesa_rotulo([
                'numero' => $comanda['mesa_numero'],
                'nome' => $comanda['mesa_nome'],
                'capacidade' => $comanda['mesa_capacidade'],
            ])) ?>
            &middot; <?= e($comanda['garcom_nome']) ?>
            &middot; aberta <?= formatar_tempo_decorrido((string)$comanda['data_abertura']) ?> atrás
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('consumo/comandas/index.php') ?>" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i>Comandas
        </a>
        <?php if (tem_permissao('comandas_imprimir')): ?>
        <a href="<?= url('consumo/comandas/cupom.php?id=' . (int)$comanda['id']) ?>" target="_blank"
           class="btn btn-soft" data-bs-toggle="tooltip" title="Imprimir cupom (80mm)">
            <i class="bi bi-printer me-1"></i>Cupom
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($comanda['observacao']): ?>
<div class="alert alert-info d-flex align-items-start">
    <i class="bi bi-sticky me-2"></i>
    <div><b>Observação:</b> <?= e($comanda['observacao']) ?></div>
</div>
<?php endif; ?>

<?php if ($comanda['status'] === 'CANCELADA'): ?>
<div class="alert alert-danger">
    <i class="bi bi-x-octagon me-2"></i>
    <b>Comanda cancelada</b>
    <?php if ($comanda['motivo_cancelamento']): ?> — motivo: <?= e($comanda['motivo_cancelamento']) ?><?php endif; ?>
    <?php if ($comanda['cancelado_em']): ?>
    <small class="d-block"><?= formatar_datahora((string)$comanda['cancelado_em']) ?></small>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($podeManipular && $pendentes): ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-hourglass-split me-1"></i>
    <b><?= count($pendentes) ?> item(ns) ainda em preparo.</b>
    <span class="text-muted small">
        <?= htmlspecialchars(implode(' · ', array_map(
            fn($p) => formatar_qtde($p['quantidade']) . 'x ' . $p['descricao'],
            array_slice($pendentes, 0, 3)
        ))) ?><?= count($pendentes) > 3 ? ' ...' : '' ?></span>
</div>
<?php endif; ?>

<?php if ($podeManipular && $prontos): ?>
<div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-bag-check me-1"></i>
    <b><?= count($prontos) ?> item(ns) prontos para entrega.</b>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card mb-3">
            <div class="card-header-custom">
                <i class="bi bi-list-check"></i>Itens da comanda
                <span class="badge bg-light text-dark ms-auto"><?= count($itensAtivos) ?> ativo(s)</span>
            </div>
            <div class="card-body-custom p-0">
                <div class="comanda-itens">
                    <?php if (!$itens): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-basket d-block fs-1 mb-2"></i>
                        Nenhum item lançado. Escolha um produto ao lado.
                    </div>
                    <?php endif; ?>

                    <?php foreach ($itens as $i): ?>
                    <div class="comanda-item <?= e($i['status']) ?>" data-item-linha="<?= (int)$i['id'] ?>">
                        <span class="qtd"><?= formatar_qtde($i['quantidade']) ?>x</span>
                        <div class="desc">
                            <div class="fw-semibold"><?= e($i['descricao']) ?></div>
                            <small class="text-muted">
                                <?= formatar_moeda($i['preco_unitario']) ?> cada
                                <?= (float)$i['total'] > 0 ? ' &middot; ' . formatar_moeda($i['total']) : '' ?>
                            </small>
                            <?php if ($i['observacoes']): ?>
                            <span class="obs mt-1">
                                <i class="bi bi-info-circle"></i><?= e($i['observacoes']) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="text-end">
                            <?= badge_status((string)$i['status']) ?>
                        </div>

                        <?php if ($podeManipular): ?>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <?php $editavel = item_editavel($i); ?>
                            <?php if ($editavel): ?>
                            <button type="button" class="btn btn-soft btn-sm js-menos" title="Diminuir">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm text-center js-qtd"
                                   name="qtd" value="<?= formatar_numero((float)$i['quantidade'], 0) ?>"
                                   data-atual="<?= formatar_numero((float)$i['quantidade'], 0) ?>"
                                   style="width:52px" data-mask="int" aria-label="Quantidade">
                            <button type="button" class="btn btn-soft btn-sm js-mais" title="Aumentar">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                            <?php endif; ?>

                            <?php $saidas = item_transicoes()[(string)$i['status']] ?? []; ?>
                            <?php if ($saidas): ?>
                            <select class="form-select form-select-sm js-status-item" data-item="<?= (int)$i['id'] ?>"
                                    style="width:132px" aria-label="Status do item" title="Alterar status">
                                <option value=""><?= e($i['status']) ?>…</option>
                                <?php foreach ($saidas as $saida): ?>
                                <option value="<?= e($saida) ?>"><?= e($saida) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>

                            <button type="button" class="btn btn-soft btn-sm js-obs-item"
                                    data-item="<?= (int)$i['id'] ?>"
                                    data-obs="<?= e($i['observacoes']) ?>" title="Observação">
                                <i class="bi bi-chat-left-text"></i>
                            </button>

                            <?php if ($editavel): ?>
                            <button type="button" class="btn btn-soft-danger btn-sm js-remover-item"
                                    data-item="<?= (int)$i['id'] ?>" title="Remover">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-body-custom comanda-total">
                <div class="linha-total">
                    <span class="rot">Subtotal</span>
                    <span class="val"><?= formatar_moeda($comanda['subtotal']) ?></span>
                </div>
                <?php if ((float)$comanda['desconto'] > 0): ?>
                <div class="linha-total">
                    <span class="rot">Desconto</span>
                    <span class="val text-danger">- <?= formatar_moeda($comanda['desconto']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ((float)$comanda['acrescimo'] > 0): ?>
                <div class="linha-total">
                    <span class="rot">Acréscimo</span>
                    <span class="val text-success">+ <?= formatar_moeda($comanda['acrescimo']) ?></span>
                </div>
                <?php endif; ?>
                <div class="linha-total principal">
                    <span class="rot">Total</span>
                    <span class="val"><?= formatar_moeda($comanda['total']) ?></span>
                </div>
                <?php if ((float)$comanda['valor_pago'] > 0): ?>
                <div class="linha-total">
                    <span class="rot">Pago</span>
                    <span class="val text-success"><?= formatar_moeda($comanda['valor_pago']) ?></span>
                </div>
                <div class="linha-total">
                    <span class="rot">Saldo</span>
                    <span class="val"><?= formatar_moeda(max(0, (float)$comanda['total'] - (float)$comanda['valor_pago'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($podeManipular): ?>
        <div class="card no-print">
            <div class="card-header-custom"><i class="bi bi-tools"></i>Ações da comanda</div>
            <div class="card-body-custom d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-soft js-fechar" data-comanda="<?= (int)$comanda['id'] ?>"
                        <?= $itensAtivos ? '' : 'disabled' ?>>
                    <i class="bi bi-lock me-1"></i>Fechar comanda
                </button>

                <?php if (tem_permissao('comandas_desconto')): ?>
                <button type="button" class="btn btn-soft js-desconto" data-comanda="<?= (int)$comanda['id'] ?>"
                        data-subtotal="<?= (float)$comanda['subtotal'] ?>"
                        data-desconto="<?= (float)$comanda['desconto'] ?>"
                        data-acrescimo="<?= (float)$comanda['acrescimo'] ?>">
                    <i class="bi bi-percent me-1"></i>Desconto / acréscimo
                </button>
                <?php endif; ?>

                <?php if (tem_permissao('comandas_cancelar')): ?>
                <button type="button" class="btn btn-soft-danger js-cancelar-comanda"
                        data-comanda="<?= (int)$comanda['id'] ?>" data-numero="<?= e($comanda['numero']) ?>">
                    <i class="bi bi-x-octagon me-1"></i>Cancelar comanda
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-xl-5">
        <?php if ($podeManipular && tem_permissao('comandas_item')): ?>
        <div class="card mb-3">
            <div class="card-header-custom">
                <i class="bi bi-journal-text"></i>Produtos
                <span class="ms-auto small text-muted fw-normal">Clique para lançar</span>
            </div>
            <div class="card-body-custom">
                <form method="post" action="<?= url('consumo/comandas/salvar.php') ?>" id="formItemComanda">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="add_item">
                    <input type="hidden" name="comanda_id" value="<?= (int)$comanda['id'] ?>">
                    <input type="hidden" name="produto_id" value="">
                    <input type="hidden" name="qtd" value="1">
                </form>
                <div class="produtos-busca">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control js-busca-produto"
                               placeholder="Buscar produto por nome ou código"
                               autocomplete="off" aria-label="Buscar produto">
                        <button type="button" class="btn btn-soft js-limpa-busca d-none" title="Limpar busca">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="produtos-busca-status small text-muted" data-busca-status hidden></div>
                </div>
                <div data-produtos-picker>
                    <div class="text-center text-muted small py-3">
                        <span class="spinner-border spinner-border-sm me-2"></span>Carregando produtos...
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header-custom"><i class="bi bi-clock-history"></i>Andamento</div>
            <div class="card-body-custom">
                <?php if ($producaoAtiva): ?>
                <div class="kds-barras mb-0">
                    <div class="kds-contador">
                        <i class="bi bi-hourglass-split text-warning fs-4"></i>
                        <div>
                            <div class="n"><?= count($pendentes) ?></div>
                            <div class="r">Fila</div>
                        </div>
                    </div>
                    <div class="kds-contador">
                        <i class="bi bi-fire text-info fs-4"></i>
                        <div>
                            <div class="n"><?= count($emPreparo) ?></div>
                            <div class="r">Preparando</div>
                        </div>
                    </div>
                    <div class="kds-contador">
                        <i class="bi bi-check2-circle text-success fs-4"></i>
                        <div>
                            <div class="n"><?= count($prontos) ?></div>
                            <div class="r">Prontos</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="info-lote">
                    <span class="rot"><?= e(rotulo_producao()) ?></span>
                    <span class="val">Desativada</span>
                </div>
                <?php endif; ?>

                <div class="info-lote mt-3">
                    <span class="rot">Abertura</span>
                    <span class="val"><?= formatar_datahora((string)$comanda['data_abertura']) ?></span>
                </div>
                <?php if ($comanda['data_fechamento']): ?>
                <div class="info-lote">
                    <span class="rot">Fechamento</span>
                    <span class="val"><?= formatar_datahora((string)$comanda['data_fechamento']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($comanda['data_pagamento']): ?>
                <div class="info-lote">
                    <span class="rot">Pagamento</span>
                    <span class="val">
                        <?= formatar_datahora((string)$comanda['data_pagamento']) ?>
                        <?= $comanda['forma_nome'] ? '(' . e($comanda['forma_nome']) . ')' : '' ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3 no-print">
                    <?php if (tem_permissao('comandas_imprimir')): ?>
                    <a href="<?= url('consumo/comandas/cupom.php?id=' . (int)$comanda['id']) ?>" target="_blank"
                       class="btn btn-soft btn-sm">
                        <i class="bi bi-printer me-1"></i>Cupom 80mm
                    </a>
                    <?php endif; ?>
                    <?php if ((string)$comanda['status'] === 'FECHADA' && tem_permissao('caixa_consumo_ver')): ?>
                    <a href="<?= url('consumo/caixa/index.php?comanda=' . (int)$comanda['id']) ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-cash-coin me-1"></i>Ir ao caixa
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        const comandaId = <?= (int)$comanda['id'] ?>;
        const recarregar = () => setTimeout(() => window.location.reload(), 700);

        /* Fechar comanda */
        $('.js-fechar').on('click', function () {
            const $btn = $(this);
            APP.swalConfirm('Fechar comanda?',
                'A comanda será enviada para o caixa. Itens ainda em preparo impedem o fechamento.',
                'question', 'Fechar comanda').then(function (r) {
                if (!r.isConfirmed) return;
                $btn.prop('disabled', true);
                CONSUMO.acao('comandas/acao.php', { csrf_token: APP.csrf, acao: 'fechar', comanda_id: comandaId },
                    function (res) {
                        Swal.fire({
                            icon: 'success', title: res.msg,
                            confirmButtonText: 'Abrir comanda'
                        }).then(function () { window.location.reload(); });
                    },
                    function (res) {
                        APP.toast('error', (res && res.msg) || 'Não foi possível fechar a comanda.');
                        $btn.prop('disabled', false);
                    });
            });
        });

        /* Desconto / acréscimo */
        $('.js-desconto').on('click', function () {
            const $btn = $(this);
            const subtotal = APP.paraNumero($btn.data('subtotal'));
            Swal.fire({
                title: 'Desconto e acréscimo',
                html: '<div class="text-start">'
                    + '<label class="form-label small">Desconto (R$)</label>'
                    + '<input id="pgDesc" class="swal2-input" type="text" inputmode="decimal"'
                    + ' value="' + APP.fmtNumero(APP.paraNumero($btn.data('desconto')), 2) + '">'
                    + '<label class="form-label small mt-2">Acréscimo (R$)</label>'
                    + '<input id="pgAcr" class="swal2-input" type="text" inputmode="decimal"'
                    + ' value="' + APP.fmtNumero(APP.paraNumero($btn.data('acrescimo')), 2) + '">'
                    + '<small class="text-muted">Subtotal: ' + APP.fmtMoeda(subtotal) + '</small></div>',
                showCancelButton: true,
                confirmButtonText: 'Aplicar',
                cancelButtonText: 'Cancelar',
                preConfirm: function () {
                    return {
                        desconto: APP.paraNumero($('#pgDesc').val()),
                        acrescimo: APP.paraNumero($('#pgAcr').val())
                    };
                }
            }).then(function (r) {
                if (!r.isConfirmed) return;
                CONSUMO.acao('comandas/acao.php', {
                    csrf_token: APP.csrf, acao: 'desconto', comanda_id: comandaId,
                    desconto: r.value.desconto, acrescimo: r.value.acrescimo
                }, function () { recarregar(); },
                    function (res) { APP.toast('error', (res && res.msg) || 'Não foi possível aplicar.'); });
            });
        });

        /* Cancelar comanda */
        $('.js-cancelar-comanda').on('click', function () {
            const $btn = $(this);
            const numero = $btn.data('numero');
            APP.swalConfirm('Cancelar comanda ' . numero + '?',
                'Todos os itens serão cancelados e a mesa será liberada. Esta ação não pode ser desfeita.',
                'warning', 'Sim, cancelar').then(function (r) {
                if (!r.isConfirmed) return;
                Swal.fire({
                    title: 'Motivo do cancelamento',
                    input: 'text',
                    inputPlaceholder: 'Ex.: cliente desistiu, erro de digitação...',
                    showCancelButton: true,
                    confirmButtonText: 'Cancelar comanda',
                    cancelButtonText: 'Voltar'
                }).then(function (m) {
                    if (!m.isConfirmed) return;
                    CONSUMO.acao('comandas/acao.php', {
                        csrf_token: APP.csrf, acao: 'cancelar_comanda',
                        comanda_id: comandaId, motivo: m.value
                    }, function () { recarregar(); },
                        function (res) { APP.toast('error', (res && res.msg) || 'Não foi possível cancelar.'); });
                });
            });
        });

        /* Queda de quantidade: salvar ao sair do campo */
        function itemIdDaLinha($el) {
            return $el.closest('[data-item-linha]').data('item-linha');
        }

        function salvarQtd($qtd) {
            let valor = APP.paraNumero($qtd.val());
            if (valor <= 0) {
                valor = 1;
                $qtd.val(1);
            }
            $qtd.val(valor);
            if (valor === Number($qtd.data('atual'))) return;

            $qtd.data('atual', valor);
            CONSUMO.acao('comandas/acao.php', {
                csrf_token: APP.csrf, acao: 'qtd', item_id: itemIdDaLinha($qtd), qtd: valor
            }, function () { recarregar(); },
                function (res) {
                    APP.toast('error', (res && res.msg) || 'Não foi possível alterar a quantidade.');
                    window.location.reload();
                });
        }

        $('.js-mais').on('click', function () {
            const $qtd = $(this).closest('.comanda-item').find('.js-qtd');
            $qtd.val(APP.paraNumero($qtd.val()) + 1);
            salvarQtd($qtd);
        });

        $('.js-menos').on('click', function () {
            const $qtd = $(this).closest('.comanda-item').find('.js-qtd');
            const valor = APP.paraNumero($qtd.val()) - 1;
            if (valor < 1) {
                APP.toast('warning', 'A quantidade mínima é 1. Use "remover" para excluir o item.');
                return;
            }
            $qtd.val(valor);
            salvarQtd($qtd);
        });

        $('.js-qtd').on('blur', function () {
            salvarQtd($(this));
        });

        $('.js-status-item').on('change', function () {
            const $sel = $(this);
            const novo = $sel.val();
            if (!novo) return;

            CONSUMO.acao('comandas/acao.php', {
                csrf_token: APP.csrf, acao: 'status', item_id: $sel.data('item'), status: novo
            }, function () { recarregar(); },
                function (res) {
                    APP.toast('error', (res && res.msg) || 'Não foi possível alterar o status.');
                    $sel.val('');
                });
        });

        $('.js-obs-item').on('click', function () {
            const $btn = $(this);
            Swal.fire({
                title: 'Observação do item',
                input: 'text',
                inputValue: $btn.attr('data-obs') || '',
                inputPlaceholder: 'Ex.: sem cebola, ponto da carne...',
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar'
            }).then(function (r) {
                if (!r.isConfirmed) return;
                CONSUMO.acao('comandas/acao.php', {
                    csrf_token: APP.csrf, acao: 'observacao',
                    item_id: $btn.data('item'), observacao: r.value
                }, function () { recarregar(); },
                    function (res) { APP.toast('error', (res && res.msg) || 'Não foi possível salvar.'); });
            });
        });

        $('.js-remover-item').on('click', function () {
            const $btn = $(this);
            APP.swalConfirm('Remover item?',
                'O item será retirado da comanda e o total será recalculado.',
                'warning', 'Remover').then(function (r) {
                if (!r.isConfirmed) return;
                CONSUMO.acao('comandas/acao.php', {
                    csrf_token: APP.csrf, acao: 'remover', item_id: $btn.data('item')
                }, function () { recarregar(); },
                    function (res) { APP.toast('error', (res && res.msg) || 'Não foi possível remover.'); });
            });
        });

        });
</script>
<?php include INC . 'footer.php'; ?>
