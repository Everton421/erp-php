<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('contas_receber_baixar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('contas_receber/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM contas_receber WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$conta = $stmt->fetch();
if (!$conta) {
    flash('danger', 'Conta não encontrada.');
    redirecionar('contas_receber/index.php');
}
if ($conta['valor_pago'] <= 0 || $conta['status'] === 'CANCELADO') {
    flash('warning', 'Esta conta não possui recebimentos para estornar.');
    redirecionar('contas_receber/index.php');
}

try {

    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE contas_receber SET valor_pago = 0, juros = 0, multa = 0, desconto = 0,
                data_pagamento = NULL, forma_pagamento_id = NULL, status = ? WHERE id = ?'
    )->execute([in_array($conta['status'], ['PAGO', 'PARCIAL'], true) ? 'PENDENTE' : $conta['status'], $id]);

    // Reverte o fluxo de caixa do recebimento
    $stmtFluxo = $pdo->prepare('SELECT * FROM fluxo_caixa WHERE referencia = ? AND estorno = 0');
    $stmtFluxo->execute(['RECEBER#' . $id]);
    foreach ($stmtFluxo->fetchAll() as $fl) {
        registrar_fluxo('SAIDA', 'RECEBIMENTO', 'Estorno recebimento: ' . $fl['descricao'], $fl['valor'], 'RECEBER#' . $id . '#ESTORNO');
        $pdo->prepare('UPDATE fluxo_caixa SET estorno = 1 WHERE id = ?')->execute([(int)$fl['id']]);
    }

    if ($conta['venda_parcela_id']) {
        $pdo->prepare("UPDATE venda_parcelas SET status = 'PENDENTE' WHERE id = ?")->execute([(int)$conta['venda_parcela_id']]);
    }

    $pdo->commit();

    registrar_log('contas_receber', 'Estorno de recebimento #' . $id, $id, ['valor_pago' => $conta['valor_pago']], ['valor_pago' => 0, 'status' => 'PENDENTE']);
    flash('success', 'Recebimento estornado com sucesso.');
    redirecionar('contas_receber/index.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'estornar_receber');
    flash('danger', 'Não foi possível estornar o recebimento.');
    redirecionar('contas_receber/index.php');
}