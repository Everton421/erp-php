<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('clientes_ver');

$id = (int)($_GET['id'] ?? 0);
$cliente = buscar_linha('clientes', $id);

if (!$cliente) {
    flash('danger', 'Cliente não encontrado.');
    redirecionar('clientes/index.php');
}

$pdo = db();

// Estatísticas do cliente
$stats = $pdo->prepare(
    "SELECT 
        COUNT(CASE WHEN status = 'FINALIZADA' THEN 1 END) AS total_compras,
        COALESCE(SUM(CASE WHEN status = 'FINALIZADA' THEN total END), 0) AS valor_compras,
        COUNT(CASE WHEN status = 'ORCAMENTO' THEN 1 END) AS total_orcamentos
     FROM vendas
     WHERE cliente_id = ?"
);
$stats->execute([$id]);
$resumo = $stats->fetch();

$totalCompras = (int)($resumo['total_compras'] ?? 0);
$valorTotal = (float)($resumo['valor_compras'] ?? 0);
$ticketMedio = $totalCompras > 0 ? $valorTotal / $totalCompras : 0.0;
$totalOrcamentos = (int)($resumo['total_orcamentos'] ?? 0);

// Saldo em aberto no Contas a Receber
$crStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(valor - valor_pago), 0)
       FROM contas_receber
      WHERE cliente_id = ? AND status IN ('PENDENTE', 'PARCIAL')"
);
$crStmt->execute([$id]);
$saldoDevedor = (float)$crStmt->fetchColumn();

// Histórico de Vendas
$vendasStmt = $pdo->prepare(
    "SELECT id, numero, data_venda, total, status, observacao
       FROM vendas
      WHERE cliente_id = ?
      ORDER BY data_venda DESC LIMIT 50"
);
$vendasStmt->execute([$id]);
$vendas = $vendasStmt->fetchAll();

// Histórico de Contas a Receber
$contasStmt = $pdo->prepare(
    "SELECT cr.*, fp.nome AS forma_nome
       FROM contas_receber cr
       LEFT JOIN formas_pagamento fp ON fp.id = cr.forma_pagamento_id
      WHERE cr.cliente_id = ?
      ORDER BY cr.vencimento DESC LIMIT 50"
);
$contasStmt->execute([$id]);
$contas = $contasStmt->fetchAll();

// WhatsApp
$telNum = preg_replace('/\D/', '', $cliente['celular'] ?: $cliente['telefone'] ?: '');
$linkWhats = strlen($telNum) >= 10 ? 'https://wa.me/55' . $telNum : null;

$tituloPagina = 'Cliente: ' . $cliente['nome'];
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-person-badge me-2"></i><?= e($cliente['nome']) ?></h1>
        <span class="subtitulo">
            <?= e($cliente['nome_fantasia'] ?: ($cliente['tipo'] === 'JURIDICA' ? 'Pessoa Jurídica' : 'Pessoa Física')) ?>
            <?php if ($cliente['codigo']): ?> • Código: <b><?= e($cliente['codigo']) ?></b><?php endif; ?>
        </span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (tem_permissao('vendas_criar')): ?>
        <a href="<?= url('vendas/nova.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-cart-plus me-1"></i>Nova venda
        </a>
        <?php endif; ?>
        <?php if (tem_permissao('clientes_editar')): ?>
        <a href="<?= url('clientes/form.php?id=' . (int)$cliente['id']) ?>" class="btn btn-soft btn-sm">
            <i class="bi bi-pencil me-1"></i>Editar cadastro
        </a>
        <?php endif; ?>
        <a href="<?= url('clientes/index.php') ?>" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<!-- Cards de Resumo -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-indigo h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-cash-stack me-1"></i>Total comprado</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($valorTotal) ?></div>
                <div class="stat-extra"><?= $totalCompras ?> compra(s) realizada(s)</div>
                <i class="bi bi-cash-stack stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-teal h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-graph-up me-1"></i>Ticket médio</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($ticketMedio) ?></div>
                <div class="stat-extra">por pedido finalizado</div>
                <i class="bi bi-graph-up stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-red h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-clock-history me-1"></i>Saldo em aberto</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($saldoDevedor) ?></div>
                <div class="stat-extra"><?= $saldoDevedor > 0 ? 'possui parcelas a receber' : 'em dia' ?></div>
                <i class="bi bi-clock-history stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-blue h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-file-earmark-text me-1"></i>Orçamentos</div>
                <div class="stat-valor mt-1"><?= $totalOrcamentos ?></div>
                <div class="stat-extra">cotações geradas</div>
                <i class="bi bi-file-earmark-text stat-ico"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Dados Cadastrais -->
    <div class="col-12 col-lg-4">
        <div class="card mb-3 h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-1"></i>Informações de Contato</span>
                <?= (int)$cliente['status'] === 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?>
            </div>
            <div class="card-body-custom">
                <div class="mb-2">
                    <small class="text-muted d-block">Documento (CPF/CNPJ)</small>
                    <b><?= e($cliente['documento'] ?: 'Não informado') ?></b>
                </div>
                <?php if ($cliente['inscricao_estadual']): ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Inscrição Estadual</small>
                    <b><?= e($cliente['inscricao_estadual']) ?></b>
                </div>
                <?php endif; ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Telefone / Celular</small>
                    <b><?= e($cliente['celular'] ?: $cliente['telefone'] ?: 'Não informado') ?></b>
                    <?php if ($linkWhats): ?>
                    <a href="<?= e($linkWhats) ?>" target="_blank" class="btn btn-sm btn-outline-success ms-2 py-0 px-2" title="Chamar no WhatsApp">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">E-mail</small>
                    <b><?= e($cliente['email'] ?: 'Não informado') ?></b>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Endereço</small>
                    <b>
                        <?= e($cliente['endereco'] ?: '-') ?>
                        <?= $cliente['numero'] ? ', ' . e($cliente['numero']) : '' ?>
                        <?= $cliente['bairro'] ? ' - ' . e($cliente['bairro']) : '' ?>
                        <br>
                        <?= e($cliente['cidade'] ?: '-') ?><?= $cliente['estado'] ? '/' . e($cliente['estado']) : '' ?>
                        <?= $cliente['cep'] ? ' • CEP: ' . e($cliente['cep']) : '' ?>
                    </b>
                </div>
                <?php if ($cliente['observacoes']): ?>
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted d-block">Observações</small>
                    <div class="small"><?= nl2br(e($cliente['observacoes'])) ?></div>
                </div>
                <?php endif; ?>
                <div class="mt-3 pt-2 border-top text-muted small">
                    Cadastrado em <?= formatar_data($cliente['criado_em']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico de Vendas e Financeiro -->
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header-custom p-0">
                <ul class="nav nav-tabs border-bottom-0" id="tabsCliente" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="tab-vendas" data-bs-toggle="tab" data-bs-target="#conteudo-vendas" type="button">
                            <i class="bi bi-cart-check me-1"></i>Histórico de Vendas (<?= count($vendas) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="tab-contas" data-bs-toggle="tab" data-bs-target="#conteudo-contas" type="button">
                            <i class="bi bi-cash-coin me-1"></i>Contas a Receber (<?= count($contas) ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body-custom p-0">
                <div class="tab-content">
                    <!-- Tab Vendas -->
                    <div class="tab-pane fade show active" id="conteudo-vendas">
                        <?php if (!count($vendas)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-1"></i>Nenhuma venda ou orçamento registrado para este cliente.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Número</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vendas as $v): ?>
                                    <tr>
                                        <td><b><?= e($v['numero']) ?></b></td>
                                        <td><?= formatar_datahora($v['data_venda']) ?></td>
                                        <td><?= badge_status($v['status']) ?></td>
                                        <td class="text-end fw-semibold"><?= formatar_moeda($v['total']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= url('vendas/ver.php?id=' . (int)$v['id']) ?>" class="btn btn-sm btn-soft">
                                                <i class="bi bi-eye me-1"></i>Ver
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab Contas a Receber -->
                    <div class="tab-pane fade" id="conteudo-contas">
                        <?php if (!count($contas)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-check2-circle fs-2 text-success d-block mb-1"></i>Sem títulos financeiros para este cliente.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Documento</th>
                                        <th>Vencimento</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Pago</th>
                                        <th>Status</th>
                                        <th>Forma</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contas as $c): ?>
                                    <tr>
                                        <td><?= e($c['documento'] ?: '-') ?> <small class="text-muted">(<?= e($c['parcela_numero'] ?: '1/1') ?>)</small></td>
                                        <td><?= formatar_data($c['vencimento']) ?></td>
                                        <td class="text-end"><?= formatar_moeda($c['valor']) ?></td>
                                        <td class="text-end text-success"><?= formatar_moeda($c['valor_pago']) ?></td>
                                        <td><?= badge_status($c['status']) ?></td>
                                        <td><?= e($c['forma_nome'] ?: '-') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include INC . 'footer.php'; ?>
