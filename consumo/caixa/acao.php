<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resposta(false, 'Método não permitido.', null, 405);
}
exigir_csrf();
exigir_permissao('caixa_consumo_pagar');

$acao = (string)($_POST['acao'] ?? 'pagar');
$comandaId = (int)($_POST['comanda_id'] ?? 0);
$pdo = db();

$comanda = comanda_buscar($comandaId);
if (!$comanda) {
    json_resposta(false, 'Comanda não encontrada.', null, 404);
}
if ((string)$comanda['status'] === 'CANCELADA') {
    json_resposta(false, 'Comanda cancelada não pode receber pagamento.', null, 409);
}
if ((string)$comanda['status'] !== 'FECHADA') {
    json_resposta(false, 'A comanda precisa estar fechada para receber pagamento.', null, 409);
}

if ($acao !== 'pagar') {
    json_resposta(false, 'Ação inválida.', null, 400);
}

$pagamentos = json_decode((string)($_POST['pagamentos'] ?? '[]'), true);
if (!is_array($pagamentos) || !$pagamentos) {
    json_resposta(false, 'Informe ao menos uma forma de pagamento.', null, 422);
}

$totalComanda = round((float)$comanda['total'], 2);
$saldo = round($totalComanda - (float)$comanda['valor_pago'], 2);
if ($saldo <= 0) {
    json_resposta(false, 'Esta comanda já está quitada.', null, 409);
}

$formasValidas = [];
foreach ($pdo->query('SELECT id, nome FROM formas_pagamento WHERE ativo = 1') as $f) {
    $formasValidas[(int)$f['id']] = (string)$f['nome'];
}

$lista = [];
$somaInformada = 0.0;
foreach ($pagamentos as $pg) {
    $formaId = (int)($pg['forma'] ?? 0);
    $valor = round((float)($pg['valor'] ?? 0), 2);
    $parcelas = max(1, min(12, (int)($pg['parcelas'] ?? 1)));

    if (!isset($formasValidas[$formaId])) {
        json_resposta(false, 'Forma de pagamento inválida.', null, 422);
    }
    if ($valor <= 0) {
        continue;
    }
    $lista[] = ['forma_id' => $formaId, 'forma_nome' => $formasValidas[$formaId], 'valor' => $valor, 'parcelas' => $parcelas];
    $somaInformada += $valor;
}

if (!$lista) {
    json_resposta(false, 'Informe ao menos uma forma de pagamento com valor maior que zero.', null, 422);
}
$somaInformada = round($somaInformada, 2);

$documento = comanda_documento($comanda);
$referencia = comanda_referencia($comanda);
$antes = $comanda;
$hoje = date('Y-m-d');
$diasVenc = (int)obter_config('dias_recebimento', '30');

try {
    $pdo->beginTransaction();

    // Trava a comanda: impede que dois caixas registrem pagamento ao mesmo tempo.
    $stmt = $pdo->prepare('SELECT valor_pago, status_pagamento FROM comandas WHERE id = ? FOR UPDATE');
    $stmt->execute([$comandaId]);
    if (!$bloqueio = $stmt->fetch()) {
        $pdo->rollBack();
        json_resposta(false, 'Comanda não encontrada.', null, 404);
    }
    $pagoAntes = round((float)$bloqueio['valor_pago'], 2);
    $saldoAtual = round($totalComanda - $pagoAntes, 2);
    if ($saldoAtual <= 0) {
        $pdo->rollBack();
        json_resposta(false, 'Esta comanda acabou de ser quitada por outro usuário.', null, 409);
    }

    // Cada forma é aplicada até o saldo acabar: o excedente é troco e não entra como receita.
    $aplicado = 0.0;
    $primeiraForma = $lista[0]['forma_id'];
    foreach ($lista as $idx => $pg) {
        $restante = round($saldoAtual - $aplicado, 2);
        if ($restante <= 0) {
            break;
        }
        $valorAplicado = min($pg['valor'], $restante);
        $lista[$idx]['informado'] = $pg['valor'];
        $lista[$idx]['valor'] = round($valorAplicado, 2);
        $lista[$idx]['troco'] = round($pg['valor'] - $valorAplicado, 2);
        $aplicado = round($aplicado + $valorAplicado, 2);

        $stmt = $pdo->prepare(
            'INSERT INTO comanda_pagamentos (comanda_id, forma_pagamento_id, valor, qtde_parcelas, valor_recebido, data_pagamento)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$comandaId, $pg['forma_id'], $lista[$idx]['valor'], $pg['parcelas'], $lista[$idx]['valor']]);

        registrar_fluxo(
            'ENTRADA',
            'RECEBIMENTO',
            'Comanda ' . $comanda['numero'] . ' (' . $pg['forma_nome'] . ')',
            $lista[$idx]['valor'],
            $referencia
        );
    }

    $lista = array_values(array_filter($lista, static function ($pg) {
        return (float)$pg['valor'] > 0;
    }));

    $pagoTotal = round($pagoAntes + $aplicado, 2);
    $troco = round(max(0, $somaInformada - $aplicado), 2);
    $novoSaldo = round($totalComanda - $pagoTotal, 2);
    $quitado = $novoSaldo <= 0;

    $stmt = $pdo->prepare(
        "UPDATE comandas
            SET valor_pago = ?, troco = ?, status_pagamento = ?, data_pagamento = NOW(),
                forma_pagamento_id = ?, atualizado_em = NOW()
          WHERE id = ?"
    );
    $stmt->execute([
        $pagoTotal,
        $troco,
        $quitado ? 'PAGO' : 'PARCIAL',
        $primeiraForma,
        $comandaId,
    ]);

    // O recebível é reescrito para refletir apenas o saldo atual do documento.
    $pdo->prepare("DELETE FROM contas_receber WHERE documento = ? AND status IN ('PENDENTE', 'PARCIAL', 'VENCIDO')")
        ->execute([$documento]);

    if ($quitado) {
        $pdo->prepare(
            "INSERT INTO contas_receber (documento, parcela_numero, valor, vencimento, data_pagamento, valor_pago,
                                          juros, multa, desconto, status, forma_pagamento_id, observacao)
             VALUES (?, 'À VISTA', ?, ?, NOW(), ?, 0, 0, 0, 'PAGO', ?, ?)"
        )->execute([
            $documento, $totalComanda, $hoje, $totalComanda, $primeiraForma,
            'Pagamento integral da comanda ' . $comanda['numero'],
        ]);
    } else {
        $pdo->prepare(
            "INSERT INTO contas_receber (documento, parcela_numero, valor, vencimento, data_pagamento, valor_pago,
                                          juros, multa, desconto, status, forma_pagamento_id, observacao)
             VALUES (?, '1/1', ?, ?, NULL, ?, 0, 0, 0, 'PENDENTE', ?, ?)"
        )->execute([
            $documento, $novoSaldo, date('Y-m-d', strtotime('+' . $diasVenc . ' days')), $pagoTotal,
            $primeiraForma,
            'Saldo da comanda ' . $comanda['numero'] . ' (mesa ' . (int)$comanda['mesa_numero'] . ')',
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'pagamento_comanda');
    json_resposta(false, 'Não foi possível registrar o pagamento. Tente novamente.', null, 500);
}

$resumo = [];
foreach ($lista as $pg) {
    $texto = $pg['forma_nome'] . ': ' . formatar_moeda($pg['valor']);
    if ((float)($pg['troco'] ?? 0) > 0) {
        $texto .= ' (informado ' . formatar_moeda($pg['informado']) . ', troco ' . formatar_moeda($pg['troco']) . ')';
    }
    $resumo[] = $texto;
}

registrar_log('consumo', 'Pagamento da comanda ' . $comanda['numero'], $comandaId, [
    'valor_pago' => $antes['valor_pago'],
    'status_pagamento' => $antes['status_pagamento'],
], [
    'pagamentos' => $resumo,
    'valor_pago' => $pagoTotal,
    'troco' => $troco,
    'saldo' => $novoSaldo,
    'status_pagamento' => $quitado ? 'PAGO' : 'PARCIAL',
]);

$mensagem = $quitado
    ? 'Comanda ' . $comanda['numero'] . ' quitada. Saldo final: ' . formatar_moeda($novoSaldo)
    : 'Pagamento parcial registrado. ' . formatar_moeda($novoSaldo) . ' foi para contas a receber.';

if ($troco > 0) {
    $mensagem .= ' Troco: ' . formatar_moeda($troco) . '.';
}

json_resposta(true, $mensagem, [
    'comanda_id' => $comandaId,
    'valor_pago' => $pagoTotal,
    'troco' => $troco,
    'saldo' => $novoSaldo,
    'quitado' => $quitado,
]);
