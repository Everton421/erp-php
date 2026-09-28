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
if (in_array($conta['status'], ['PAGO', 'CANCELADO'], true)) {
    flash('warning', 'Esta conta já está paga ou cancelada.');
    redirecionar('contas_pagar/index.php');
}

$restante = max($conta['valor'] + $conta['juros'] + $conta['multa'] - $conta['desconto'] - $conta['valor_pago'], 0);
if ($restante <= 0) {
    flash('warning', 'Não há valor em aberto nesta conta.');
    redirecionar('contas_pagar/index.php');
}

$jurosPct = max(parse_decimal($_POST['juros_pct'] ?? '0'), 0);
$multa = max(parse_decimal($_POST['multa'] ?? '0'), 0);
$desconto = max(parse_decimal($_POST['desconto'] ?? '0'), 0);
$valorPago = parse_decimal($_POST['valor_pago'] ?? '0');
$formaId = (int)($_POST['forma_pagamento_id'] ?? 0);
$dataPg = parse_data($_POST['data_pagamento'] ?? '') ?: hoje();

if ($valorPago <= 0) {
    flash('danger', 'Informe um valor de pagamento maior que zero.');
    voltar();
}
if ($formaId <= 0 || !buscar_linha('formas_pagamento', $formaId)) {
    flash('danger', 'Selecione uma forma de pagamento válida.');
    voltar();
}

try {

    $jurosValor = round($restante * $jurosPct / 100, 2);
    $novoJuros = round((float)$conta['juros'] + $jurosValor, 2);
    $novaMulta = round((float)$conta['multa'] + $multa, 2);
    $novoDesconto = round((float)$conta['desconto'] + $desconto, 2);
    $novoPago = round((float)$conta['valor_pago'] + $valorPago, 2);
    $totalDevido = round((float)$conta['valor'] + $novoJuros + $novaMulta - $novoDesconto, 2);

    $status = $novoPago >= $totalDevido - 0.005 ? 'PAGO' : 'PARCIAL';

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE contas_pagar SET juros = ?, multa = ?, desconto = ?, valor_pago = ?, status = ?,
                data_pagamento = ?, forma_pagamento_id = ?
          WHERE id = ?'
    );
    $stmt->execute([
        $novoJuros, $novaMulta, $novoDesconto, $novoPago, $status,
        $status === 'PAGO' ? $dataPg . ' ' . date('H:i:s') : null,
        $formaId,
        $id,
    ]);

    registrar_fluxo('SAIDA', 'CONTA', 'Pagamento conta #' . $id . ' - ' . $conta['descricao'], $valorPago, 'PAGAR#' . $id);

    // Atualiza a parcela da compra correspondente
    if ($conta['compra_parcela_id']) {
        $pdo->prepare('UPDATE compra_parcelas SET status = ? WHERE id = ?')->execute([$status, (int)$conta['compra_parcela_id']]);
    }

    $pdo->commit();

    registrar_log('contas_pagar', 'Baixa em conta a pagar #' . $id, $id, ['valor_pago' => $conta['valor_pago']], ['valor_pago' => $novoPago, 'status' => $status]);
    flash('success', 'Pagamento registrado (' . formatar_moeda($valorPago) . '). Status: ' . $status . '.');
    redirecionar('contas_pagar/index.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'baixar_pagar');
    flash('danger', 'Não foi possível registrar o pagamento.');
    redirecionar('contas_pagar/index.php');
}