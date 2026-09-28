<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('contas_pagar_baixar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('contas_pagar/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM contas_pagar WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$conta = $stmt->fetch();
if (!$conta) {
    flash('danger', 'Conta não encontrada.');
    redirecionar('contas_pagar/index.php');
}
if ($conta['valor_pago'] <= 0 || $conta['status'] === 'CANCELADO') {
    flash('warning', 'Esta conta não possui pagamentos para estornar.');
    redirecionar('contas_pagar/index.php');
}

try {

    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE contas_pagar SET valor_pago = 0, juros = 0, multa = 0, desconto = 0,
                data_pagamento = NULL, forma_pagamento_id = NULL, status = ? WHERE id = ?'
    )->execute([in_array($conta['status'], ['PAGO', 'PARCIAL'], true) ? 'PENDENTE' : $conta['status'], $id]);

    $stmtFluxo = $pdo->prepare('SELECT * FROM fluxo_caixa WHERE referencia = ? AND estorno = 0');
    $stmtFluxo->execute(['PAGAR#' . $id]);
    foreach ($stmtFluxo->fetchAll() as $fl) {
        registrar_fluxo('ENTRADA', 'CONTA', 'Estorno pagamento: ' . $fl['descricao'], $fl['valor'], 'PAGAR#' . $id . '#ESTORNO');
        $pdo->prepare('UPDATE fluxo_caixa SET estorno = 1 WHERE id = ?')->execute([(int)$fl['id']]);
    }

    if ($conta['compra_parcela_id']) {
        $pdo->prepare("UPDATE compra_parcelas SET status = 'PENDENTE' WHERE id = ?")->execute([(int)$conta['compra_parcela_id']]);
    }

    $pdo->commit();

    registrar_log('contas_pagar', 'Estorno de pagamento #' . $id, $id, ['valor_pago' => $conta['valor_pago']], ['valor_pago' => 0, 'status' => 'PENDENTE']);
    flash('success', 'Pagamento estornado com sucesso.');
    redirecionar('contas_pagar/index.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'estornar_pagar');
    flash('danger', 'Não foi possível estornar o pagamento.');
    redirecionar('contas_pagar/index.php');
}