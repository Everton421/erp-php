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
if (in_array($conta['status'], ['PAGO', 'CANCELADO'], true)) {
    flash('warning', 'Esta conta já está quitada ou cancelada.');
    redirecionar('contas_receber/index.php');
}

$restante = max($conta['valor'] + $conta['juros'] + $conta['multa'] - $conta['desconto'] - $conta['valor_pago'], 0);
if ($restante <= 0) {
    flash('warning', 'Não há valor em aberto nesta conta.');
    redirecionar('contas_receber/index.php');
}

$jurosPct = max(parse_decimal($_POST['juros_pct'] ?? '0'), 0);
$multa = max(parse_decimal($_POST['multa'] ?? '0'), 0);
$desconto = max(parse_decimal($_POST['desconto'] ?? '0'), 0);
$valorRecebido = parse_decimal($_POST['valor_recebido'] ?? '0');
$formaId = (int)($_POST['forma_pagamento_id'] ?? 0);
$dataPg = parse_data($_POST['data_pagamento'] ?? '') ?: hoje();
$observacao = sanear($_POST['observacao'] ?? '');

if ($valorRecebido <= 0) {
    flash('danger', 'Informe um valor de recebimento maior que zero.');
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
    $novoPago = round((float)$conta['valor_pago'] + $valorRecebido, 2);
    $totalDevido = round((float)$conta['valor'] + $novoJuros + $novaMulta - $novoDesconto, 2);

    $status = $novoPago >= $totalDevido - 0.005 ? 'PAGO' : 'PARCIAL';

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE contas_receber SET juros = ?, multa = ?, desconto = ?, valor_pago = ?, status = ?,
                data_pagamento = ?, forma_pagamento_id = ?, observacao = ?
          WHERE id = ?'
    );
    $stmt->execute([$novoJuros, $novaMulta, $novoDesconto, $novoPago, $status, $status === 'PAGO' ? $dataPg . ' ' . date('H:i:s') : null, $formaId, $observacao ?: null, $id]);

    registrar_fluxo('ENTRADA', 'RECEBIMENTO', 'Recebimento conta #' . $id, $valorRecebido, 'RECEBER#' . $id);

    // Atualiza a parcela da venda correspondente
    if ($conta['venda_parcela_id']) {
        $pdo->prepare('UPDATE venda_parcelas SET status = ? WHERE id = ?')->execute([$status, (int)$conta['venda_parcela_id']]);
    }

    // Marca fluxo anterior como estornado (caso tenha havido estorno/reescrita) — nunca aqui, apenas segurança
    $pdo->commit();

    registrar_log('contas_receber', 'Baixa em conta a receber #' . $id, $id, ['valor_pago' => $conta['valor_pago']], ['valor_pago' => $novoPago, 'status' => $status]);
    flash('success', 'Recebimento registrado (' . formatar_moeda($valorRecebido) . '). Status: ' . $status . '.');
    redirecionar('contas_receber/index.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'baixar_receber');
    flash('danger', 'Não foi possível registrar o recebimento.');
    redirecionar('contas_receber/index.php');
}