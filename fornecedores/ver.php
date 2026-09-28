<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('fornecedores_ver');

$id = (int)($_GET['id'] ?? 0);
$fornecedor = buscar_linha('fornecedores', $id);

if (!$fornecedor) {
    flash('danger', 'Fornecedor não encontrado.');
    redirecionar('fornecedores/index.php');
}

$pdo = db();

// Estatísticas de compras
$stats = $pdo->prepare(
    "SELECT 
        COUNT(CASE WHEN status = 'FINALIZADA' THEN 1 END) AS total_compras,
        COALESCE(SUM(CASE WHEN status = 'FINALIZADA' THEN total END), 0) AS valor_compras
     FROM compras
     WHERE fornecedor_id = ?"
);
$stats->execute([$id]);
$resumo = $stats->fetch();

$totalCompras = (int)($resumo['total_compras'] ?? 0);
$valorTotal = (float)($resumo['valor_compras'] ?? 0);

// Saldo em aberto no Contas a Pagar
$cpStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(valor - valor_pago), 0)
       FROM contas_pagar
      WHERE fornecedor_id = ? AND status IN ('PENDENTE', 'PARCIAL')"
);
$cpStmt->execute([$id]);
$saldoPagar = (float)$cpStmt->fetchColumn();

// Total de produtos fornecidos
$prodCountStmt = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE fornecedor_id = ? AND status = 1');
$prodCountStmt->execute([$id]);
$totalProds = (int)$prodCountStmt->fetchColumn();

// Histórico de Compras
$comprasStmt = $pdo->prepare(
    "SELECT id, numero, data_compra, total, status, observacao
       FROM compras
      WHERE fornecedor_id = ?
      ORDER BY data_compra DESC LIMIT 50"
);
$comprasStmt->execute([$id]);
$compras = $comprasStmt->fetchAll();

// Histórico de Contas a Pagar
$contasStmt = $pdo->prepare(
    "SELECT cp.*, fp.nome AS forma_nome
       FROM contas_pagar cp
       LEFT JOIN formas_pagamento fp ON fp.id = cp.forma_pagamento_id
      WHERE cp.fornecedor_id = ?
      ORDER BY cp.vencimento DESC LIMIT 50"
);
$contasStmt->execute([$id]);
$contas = $contasStmt->fetchAll();

// WhatsApp
$telNum = preg_replace('/\D/', '', $fornecedor['celular'] ?: $fornecedor['telefone'] ?: '');
$linkWhats = strlen($telNum) >= 10 ? 'https://wa.me/55' . $telNum : null;

$tituloPagina = 'Fornecedor: ' . $fornecedor['razao_social'];
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-truck me-2"></i><?= e($fornecedor['razao_social']) ?></h1>
        <span class="subtitulo">
            <?= e($fornecedor['nome_fantasia'] ?: 'Fornecedor') ?>
            <?php if ($fornecedor['codigo']): ?> • Código: <b><?= e($fornecedor['codigo']) ?></b><?php endif; ?>
        </span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (tem_permissao('compras_criar')): ?>
        <a href="<?= url('compras/nova.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-basket me-1"></i>Nova compra
        </a>
        <?php endif; ?>
        <?php if (tem_permissao('fornecedores_editar')): ?>
        <a href="<?= url('fornecedores/form.php?id=' . (int)$fornecedor['id']) ?>" class="btn btn-soft btn-sm">
            <i class="bi bi-pencil me-1"></i>Editar cadastro
        </a>
        <?php endif; ?>
        <a href="<?= url('fornecedores/index.php') ?>" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<!-- Cards de Resumo -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-teal h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-basket me-1"></i>Total comprado</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($valorTotal) ?></div>
                <div class="stat-extra"><?= $totalCompras ?> pedido(s) de compra</div>
                <i class="bi bi-basket stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-red h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-receipt me-1"></i>Saldo a pagar</div>
                <div class="stat-valor mt-1"><?= formatar_moeda($saldoPagar) ?></div>
                <div class="stat-extra"><?= $saldoPagar > 0 ? 'contas em aberto' : 'liquidado' ?></div>
                <i class="bi bi-receipt stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-indigo h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-box-seam me-1"></i>Produtos</div>
                <div class="stat-valor mt-1"><?= $totalProds ?></div>
                <div class="stat-extra">itens vinculados</div>
                <i class="bi bi-box-seam stat-ico"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card bg-grad-slate h-100">
            <div class="card-body">
                <div class="stat-label"><i class="bi bi-shield-check me-1"></i>Status</div>
                <div class="stat-valor mt-1 fs-4"><?= (int)$fornecedor['status'] === 1 ? 'Ativo' : 'Inativo' ?></div>
                <div class="stat-extra"><?= formatar_data($fornecedor['criado_em']) ?></div>
                <i class="bi bi-shield-check stat-ico"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Informações de Contato -->
    <div class="col-12 col-lg-4">
        <div class="card mb-3 h-100">
            <div class="card-header-custom"><i class="bi bi-info-circle me-1"></i>Dados Cadastrais</div>
            <div class="card-body-custom">
                <div class="mb-2">
                    <small class="text-muted d-block">CNPJ / CPF</small>
                    <b><?= e($fornecedor['documento'] ?: 'Não informado') ?></b>
                </div>
                <?php if ($fornecedor['inscricao_estadual']): ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Inscrição Estadual</small>
                    <b><?= e($fornecedor['inscricao_estadual']) ?></b>
                </div>
                <?php endif; ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Telefone / Contato</small>
                    <b><?= e($fornecedor['celular'] ?: $fornecedor['telefone'] ?: 'Não informado') ?></b>
                    <?php if ($linkWhats): ?>
                    <a href="<?= e($linkWhats) ?>" target="_blank" class="btn btn-sm btn-outline-success ms-2 py-0 px-2" title="Chamar no WhatsApp">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">E-mail</small>
                    <b><?= e($fornecedor['email'] ?: 'Não informado') ?></b>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Endereço</small>
                    <b>
                        <?= e($fornecedor['endereco'] ?: '-') ?>
                        <?= $fornecedor['numero'] ? ', ' . e($fornecedor['numero']) : '' ?>
                        <?= $fornecedor['bairro'] ? ' - ' . e($fornecedor['bairro']) : '' ?>
                        <br>
                        <?= e($fornecedor['cidade'] ?: '-') ?><?= $fornecedor['estado'] ? '/' . e($fornecedor['estado']) : '' ?>
                        <?= $fornecedor['cep'] ? ' • CEP: ' . e($fornecedor['cep']) : '' ?>
                    </b>
                </div>
                <?php if ($fornecedor['observacoes']): ?>
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted d-block">Observações</small>
                    <div class="small"><?= nl2br(e($fornecedor['observacoes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Histórico de Compras e Financeiro -->
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header-custom p-0">
                <ul class="nav nav-tabs border-bottom-0" id="tabsFornec" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="tab-compras" data-bs-toggle="tab" data-bs-target="#conteudo-compras" type="button">
                            <i class="bi bi-basket me-1"></i>Compras (<?= count($compras) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="tab-contas-pagar" data-bs-toggle="tab" data-bs-target="#conteudo-contas-pagar" type="button">
                            <i class="bi bi-receipt me-1"></i>Contas a Pagar (<?= count($contas) ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body-custom p-0">
                <div class="tab-content">
                    <!-- Tab Compras -->
                    <div class="tab-pane fade show active" id="conteudo-compras">
                        <?php if (!count($compras)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-1"></i>Nenhuma compra registrada para este fornecedor.
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
                                    <?php foreach ($compras as $c): ?>
                                    <tr>
                                        <td><b><?= e($c['numero']) ?></b></td>
                                        <td><?= formatar_datahora($c['data_compra']) ?></td>
                                        <td><?= badge_status($c['status']) ?></td>
                                        <td class="text-end fw-semibold"><?= formatar_moeda($c['total']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= url('compras/ver.php?id=' . (int)$c['id']) ?>" class="btn btn-sm btn-soft">
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

                    <!-- Tab Contas a Pagar -->
                    <div class="tab-pane fade" id="conteudo-contas-pagar">
                        <?php if (!count($contas)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-check2-circle fs-2 text-success d-block mb-1"></i>Nenhuma conta a pagar para este fornecedor.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Descrição / Doc</th>
                                        <th>Vencimento</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Pago</th>
                                        <th>Status</th>
                                        <th>Forma</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contas as $cp): ?>
                                    <tr>
                                        <td><?= e($cp['descricao'] ?: ($cp['documento'] ?: '-')) ?></td>
                                        <td><?= formatar_data($cp['vencimento']) ?></td>
                                        <td class="text-end"><?= formatar_moeda($cp['valor']) ?></td>
                                        <td class="text-end text-success"><?= formatar_moeda($cp['valor_pago']) ?></td>
                                        <td><?= badge_status($cp['status']) ?></td>
                                        <td><?= e($cp['forma_nome'] ?: '-') ?></td>
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
