<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

exigir_login();
exigir_permissao('vendas_criar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('vendas/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$venda = buscar_linha('vendas', $id);

if (!$venda) {
    flash('danger', 'Venda/orçamento não encontrado.');
    redirecionar('vendas/index.php');
}

if ($venda['status'] !== 'ORCAMENTO') {
    flash('warning', 'Este registro não é um orçamento em aberto.');
    redirecionar('vendas/ver.php?id=' . $id);
}

$pdo = db();
$usuario = usuario_atual();
$permiteNegativo = (int)obter_config('estoque_negativo', '0') === 1;
$diasVenc = max((int)obter_config('dias_vencimento', '30'), 1);

// Carregar itens
$stmt = $pdo->prepare(
    'SELECT vi.*, p.descricao, p.estoque_atual, p.status AS prod_status
       FROM venda_itens vi
       JOIN produtos p ON p.id = vi.produto_id
      WHERE vi.venda_id = ?'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

if (!count($itens)) {
    flash('danger', 'Este orçamento não possui itens cadastrados.');
    redirecionar('vendas/ver.php?id=' . $id);
}

// Validar estoque
foreach ($itens as $item) {
    if ((int)$item['prod_status'] !== 1) {
        flash('danger', 'O produto "' . $item['descricao'] . '" está inativo no catálogo.');
        redirecionar('vendas/ver.php?id=' . $id);
    }
    $qtd = (float)$item['quantidade'];
    $estoqueAtual = (float)$item['estoque_atual'];
    if (!$permiteNegativo && ($estoqueAtual - $qtd) < 0) {
        flash('danger', 'Estoque insuficiente para "' . $item['descricao'] . '" (saldo atual: ' . formatar_qtde($estoqueAtual) . ', necessário: ' . formatar_qtde($qtd) . ').');
        redirecionar('vendas/ver.php?id=' . $id);
    }
}

// Carregar pagamentos já registrados como previsão
$stmt = $pdo->prepare('SELECT * FROM venda_pagamentos WHERE venda_id = ?');
$stmt->execute([$id]);
$pagamentos = $stmt->fetchAll();

// Se não houver pagamentos registrados, buscar forma padrão (Dinheiro)
if (!count($pagamentos)) {
    $formaPadrao = $pdo->query('SELECT id FROM formas_pagamento WHERE ativo = 1 ORDER BY id LIMIT 1')->fetch();
    if ($formaPadrao) {
        $stmtPg = $pdo->prepare('INSERT INTO venda_pagamentos (venda_id, forma_pagamento_id, valor, qtde_parcelas) VALUES (?, ?, ?, 1)');
        $stmtPg->execute([$id, (int)$formaPadrao['id'], (float)$venda['total']]);
        $pagamentos = [['id' => (int)$pdo->lastInsertId(), 'forma_pagamento_id' => (int)$formaPadrao['id'], 'valor' => (float)$venda['total'], 'qtde_parcelas' => 1]];
    }
}

try {
    $pdo->beginTransaction();

    $agora = date('Y-m-d H:i:s');
    $hoje = date('Y-m-d');

    // 1. Atualizar status da venda para FINALIZADA
    $stmt = $pdo->prepare('UPDATE vendas SET status = \'FINALIZADA\', data_venda = ? WHERE id = ?');
    $stmt->execute([$agora, $id]);

    // 2. Decrementar estoque dos produtos
    $stmtEstoque = $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?');
    $stmtMov = $pdo->prepare(
        'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
         VALUES (?, \'SAIDA\', ?, ?, ?, NULL, \'VENDA\', ?, ?, ?, ?)'
    );

    foreach ($itens as $item) {
        $pid = (int)$item['produto_id'];
        $qtd = (float)$item['quantidade'];
        $anterior = (float)$item['estoque_atual'];
        $posterior = $anterior - $qtd;

        $stmtEstoque->execute([$posterior, $pid]);
        $stmtMov->execute([
            $pid,
            round($qtd, 3),
            $anterior,
            $posterior,
            $venda['numero'],
            (int)$usuario['id'],
            $agora,
            'Conversão orçamento ' . $venda['numero'],
        ]);
    }

    // 3. Gerar parcelas e contas a receber
    $stmtParcela = $pdo->prepare(
        'INSERT INTO venda_parcelas (venda_id, venda_pagamento_id, numero, vencimento, valor, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmtCr = $pdo->prepare(
        'INSERT INTO contas_receber (cliente_id, venda_id, venda_parcela_id, documento, parcela_numero, valor, vencimento, data_pagamento, valor_pago, juros, multa, desconto, status, forma_pagamento_id, observacao)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)'
    );

    foreach ($pagamentos as $pg) {
        $pagamentoId = (int)$pg['id'];
        $valor = (float)$pg['valor'];
        $qtdeParc = max((int)$pg['qtde_parcelas'], 1);
        $formaId = (int)$pg['forma_pagamento_id'];
        $forma = buscar_linha('formas_pagamento', $formaId);
        $formaNome = $forma['nome'] ?? 'Forma #' . $formaId;

        if ($qtdeParc > 1) {
            $base = floor(($valor * 100) / $qtdeParc) / 100;
            $soma = 0.0;
            for ($n = 1; $n <= $qtdeParc; $n++) {
                $valorParc = ($n === $qtdeParc) ? round($valor - $soma, 2) : $base;
                $soma += $valorParc;
                $vencimento = date('Y-m-d', strtotime('+' . ($n * $diasVenc) . ' days', strtotime($hoje)));
                $stmtParcela->execute([$id, $pagamentoId, $n, $vencimento, $valorParc, 'PENDENTE']);
                $parcelaId = (int)$pdo->lastInsertId();
                $stmtCr->execute([
                    $venda['cliente_id'] ?: null,
                    $id,
                    $parcelaId,
                    $venda['numero'],
                    $n . '/' . $qtdeParc,
                    $valorParc,
                    $vencimento,
                    null,
                    0,
                    'PENDENTE',
                    $formaId,
                    'Parcela ' . $n . ' da venda ' . $venda['numero'],
                ]);
            }
        } else {
            $stmtParcela->execute([$id, $pagamentoId, 1, $hoje, round($valor, 2), 'PAGO']);
            $parcelaId = (int)$pdo->lastInsertId();
            $stmtCr->execute([
                $venda['cliente_id'] ?: null,
                $id,
                $parcelaId,
                $venda['numero'],
                'À VISTA',
                round($valor, 2),
                $hoje,
                $agora,
                round($valor, 2),
                'PAGO',
                $formaId,
                'Pagamento à vista da venda ' . $venda['numero'],
            ]);
            registrar_fluxo('ENTRADA', 'VENDA', 'Venda ' . $venda['numero'] . ' (' . $formaNome . ')', $valor, 'VENDA#' . $id);
        }
    }

    $pdo->commit();

    registrar_log('vendas', 'Orçamento convertido em venda ' . $venda['numero'], $id);
    flash('success', 'Orçamento ' . $venda['numero'] . ' convertido em venda com sucesso! Estoque e pagamentos registrados.');
    redirecionar('vendas/ver.php?id=' . $id);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'converter_orcamento');
    flash('danger', 'Erro ao converter o orçamento em venda.');
    redirecionar('vendas/ver.php?id=' . $id);
}
