<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('vendas_criar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('vendas/index.php');
}
exigir_csrf();

$pdo = db();
$usuario = usuario_atual();
$permiteNegativo = (int)obter_config('estoque_negativo', '0') === 1;
$diasVenc = max((int)obter_config('dias_vencimento', '30'), 1);

$tipoOperacao = ($_POST['tipo_operacao'] ?? 'VENDA') === 'ORCAMENTO' ? 'ORCAMENTO' : 'FINALIZADA';
$ehOrcamento = $tipoOperacao === 'ORCAMENTO';

$_SESSION['venda_rascunho'] = [
    'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
    'tipo_pedido_id' => (int)($_POST['tipo_pedido_id'] ?? 0),
    'desconto' => (string)($_POST['desconto'] ?? '0'),
    'acrescimo' => (string)($_POST['acrescimo'] ?? '0'),
    'observacao' => (string)($_POST['observacao'] ?? ''),
    'tipo_operacao' => (string)($_POST['tipo_operacao'] ?? 'VENDA'),
    'rascunho_json' => (string)($_POST['rascunho_json'] ?? ''),
];

$itens = json_decode((string)($_POST['itens_json'] ?? ''), true);
$pagamentos = json_decode((string)($_POST['pagamentos_json'] ?? ''), true);

if (!is_array($itens) || !count($itens)) {
    flash('danger', 'Adicione ao menos um item ' . ($ehOrcamento ? 'ao orçamento.' : 'à venda.'));
    voltar();
}

$clienteId = (int)($_POST['cliente_id'] ?? 0);
if ($clienteId > 0 && !buscar_linha('clientes', $clienteId)) {
    flash('danger', 'Cliente não encontrado.');
    voltar();
}
$desconto = max(parse_decimal($_POST['desconto'] ?? '0'), 0);
$acrescimo = max(parse_decimal($_POST['acrescimo'] ?? '0'), 0);
$observacao = sanear($_POST['observacao'] ?? '');

// ---- Tipo de pedido ----
$tipoPedidoId = (int)($_POST['tipo_pedido_id'] ?? 0);
$tipoPedido = buscar_linha('tipos_pedido', $tipoPedidoId);
if (!$tipoPedido) {
    flash('danger', 'Tipo de pedido inválido.');
    voltar();
}
$movEstoque = (int)$tipoPedido['movimenta_estoque'] === 1;
$geraFinanceiro = (int)$tipoPedido['gera_financeiro'] === 1;
$estoqueEntrada = (int)$tipoPedido['estoque_entrada'] === 1;

if (!$ehOrcamento && $geraFinanceiro && (!is_array($pagamentos) || !count($pagamentos))) {
    flash('danger', 'Informe ao menos uma forma de pagamento.');
    voltar();
}

try {

    $detalhes = [];
    $sqlProd = $pdo->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
    $subtotal = 0.0;
    $descontoItens = 0.0;

    foreach ($itens as $item) {
        $pid = (int)($item['produto_id'] ?? 0);
        $qtd = (float)($item['quantidade'] ?? 0);
        $preco = (float)($item['preco_unitario'] ?? 0);
        $descItem = max((float)($item['desconto'] ?? 0), 0);

        $sqlProd->execute([$pid]);
        $produto = $sqlProd->fetch();
        if (!$produto) {
            flash('danger', 'Produto #' . $pid . ' não encontrado.');
            voltar();
        }
        if ((int)$produto['status'] !== 1) {
            flash('danger', 'O produto "' . $produto['descricao'] . '" está inativo.');
            voltar();
        }
        if ($qtd <= 0 || $preco < 0) {
            flash('danger', 'Quantidade/preço inválido para o produto "' . $produto['descricao'] . '".');
            voltar();
        }
        if (!$ehOrcamento && $movEstoque && !$estoqueEntrada && !$permiteNegativo && (float)$produto['estoque_atual'] - $qtd < 0) {
            flash('danger', 'Estoque insuficiente para "' . $produto['descricao'] . '" (saldo: ' . formatar_qtde($produto['estoque_atual']) . ').');
            voltar();
        }

        $descItem = min($descItem, $qtd * $preco);
        $sub = $qtd * $preco;
        $subtotal += $sub;
        $descontoItens += $descItem;
        $detalhes[] = [
            'produto' => $produto,
            'quantidade' => $qtd,
            'preco_unitario' => $preco,
            'desconto' => $descItem,
            'subtotal' => $sub,
            'total' => $sub - $descItem,
        ];
    }

    $total = ($subtotal - $descontoItens) - $desconto + $acrescimo;
    if ($total < 0) {
        flash('danger', 'Total não pode ser negativo.');
        voltar();
    }

    $pagamentosPuros = [];
    $somaPgtos = 0.0;
    if ($geraFinanceiro && is_array($pagamentos)) {
        foreach ($pagamentos as $p) {
            $formaId = (int)($p['forma_pagamento_id'] ?? 0);
            $valor = (float)($p['valor'] ?? 0);
            $qtdeParc = max((int)($p['qtde_parcelas'] ?? 1), 1);
            if ($valor < 0) {
                flash('danger', 'Valor de pagamento inválido.');
                voltar();
            }
            $forma = buscar_linha('formas_pagamento', $formaId);
            if (!$forma || (int)$forma['ativo'] !== 1) {
                if (!$ehOrcamento) {
                    flash('danger', 'Forma de pagamento não encontrada ou inativa.');
                    voltar();
                }
                continue;
            }
            $somaPgtos += $valor;
            $pagamentosPuros[] = ['forma' => $forma, 'valor' => $valor, 'qtde_parcelas' => $qtdeParc];
        }
    }

    if (!$ehOrcamento && $geraFinanceiro && abs($somaPgtos - $total) > 0.005) {
        flash('danger', 'O total dos pagamentos (' . formatar_moeda($somaPgtos) . ') deve ser igual ao total da venda (' . formatar_moeda($total) . ').');
        voltar();
    }

    $pdo->beginTransaction();

    $numero = gerar_numero('vendas', 'numero');
    $dataVenda = date('Y-m-d H:i:s');
    $hoje = date('Y-m-d');

    $stmt = $pdo->prepare(
        'INSERT INTO vendas (numero, cliente_id, vendedor_id, tipo_pedido_id, data_venda, subtotal, desconto, acrescimo, total, status, observacao, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $numero,
        $clienteId ?: null,
        (int)$usuario['id'],
        $tipoPedidoId,
        $dataVenda,
        round($subtotal, 2),
        round($descontoItens + $desconto, 2),
        round($acrescimo, 2),
        round($total, 2),
        $tipoOperacao,
        $observacao ?: null,
        (int)$usuario['id'],
    ]);
    $vendaId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
        'INSERT INTO venda_itens (venda_id, produto_id, quantidade, preco_unitario, desconto, subtotal, total)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtEstoque = $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?');
    $stmtMov = $pdo->prepare(
        'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
         VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)'
    );

    $movTipo = $estoqueEntrada ? 'ENTRADA' : 'SAIDA';
    $motivo = $tipoPedido['nome'];

    foreach ($detalhes as $d) {
        $prod = $d['produto'];
        $stmtItem->execute([
            $vendaId,
            (int)$prod['id'],
            round($d['quantidade'], 3),
            round($d['preco_unitario'], 2),
            round($d['desconto'], 2),
            round($d['subtotal'], 2),
            round($d['total'], 2),
        ]);

        if (!$ehOrcamento && $movEstoque) {
            $anterior = (float)$prod['estoque_atual'];
            $sinal = $estoqueEntrada ? 1 : -1;
            $posterior = $anterior + $sinal * $d['quantidade'];
            $stmtEstoque->execute([$posterior, (int)$prod['id']]);
            $stmtMov->execute([
                (int)$prod['id'],
                $movTipo,
                round($d['quantidade'], 3),
                $anterior,
                $posterior,
                $motivo,
                $numero,
                (int)$usuario['id'],
                $dataVenda,
                'Venda ' . $numero,
            ]);
        }
    }

    if ($geraFinanceiro) {
        $stmtPg = $pdo->prepare(
            'INSERT INTO venda_pagamentos (venda_id, forma_pagamento_id, valor, qtde_parcelas, data_pagamento)
             VALUES (?, ?, ?, ?, NULL)'
        );

        if (!$ehOrcamento) {
            $stmtParcela = $pdo->prepare(
                'INSERT INTO venda_parcelas (venda_id, venda_pagamento_id, numero, vencimento, valor, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmtCr = $pdo->prepare(
                'INSERT INTO contas_receber (cliente_id, venda_id, venda_parcela_id, documento, parcela_numero, valor, vencimento, data_pagamento, valor_pago, juros, multa, desconto, status, forma_pagamento_id, observacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)'
            );
        }

        foreach ($pagamentosPuros as $pg) {
            $stmtPg->execute([
                $vendaId,
                (int)$pg['forma']['id'],
                round($pg['valor'], 2),
                $pg['qtde_parcelas'],
            ]);
            $pagamentoId = (int)$pdo->lastInsertId();

            if ($ehOrcamento) {
                continue;
            }

            if ($pg['qtde_parcelas'] > 1) {
                $base = floor(($pg['valor'] * 100) / $pg['qtde_parcelas']) / 100;
                $soma = 0.0;
                for ($n = 1; $n <= $pg['qtde_parcelas']; $n++) {
                    $valorParc = ($n === $pg['qtde_parcelas']) ? round($pg['valor'] - $soma, 2) : $base;
                    $soma += $valorParc;
                    $vencimento = date('Y-m-d', strtotime('+' . ($n * $diasVenc) . ' days', strtotime($hoje)));
                    $stmtParcela->execute([$vendaId, $pagamentoId, $n, $vencimento, $valorParc, 'PENDENTE']);
                    $parcelaId = (int)$pdo->lastInsertId();
                    $stmtCr->execute([
                        $clienteId ?: null,
                        $vendaId,
                        $parcelaId,
                        $numero,
                        $n . '/' . $pg['qtde_parcelas'],
                        $valorParc,
                        $vencimento,
                        null,
                        0,
                        'PENDENTE',
                        (int)$pg['forma']['id'],
                        'Parcela ' . $n . ' da venda ' . $numero,
                    ]);
                }
            } else {
                $stmtParcela->execute([$vendaId, $pagamentoId, 1, $hoje, round($pg['valor'], 2), 'PAGO']);
                $parcelaId = (int)$pdo->lastInsertId();
                $stmtCr->execute([
                    $clienteId ?: null,
                    $vendaId,
                    $parcelaId,
                    $numero,
                    'À VISTA',
                    round($pg['valor'], 2),
                    $hoje,
                    $dataVenda,
                    round($pg['valor'], 2),
                    'PAGO',
                    (int)$pg['forma']['id'],
                    'Pagamento à vista da venda ' . $numero,
                ]);
                registrar_fluxo('ENTRADA', 'VENDA', 'Venda ' . $numero . ' (' . $pg['forma']['nome'] . ')', $pg['valor'], 'VENDA#' . $vendaId);
            }
        }
    }

    $pdo->commit();
    unset($_SESSION['venda_rascunho']);

    $tipoTexto = $ehOrcamento ? 'Orçamento gerado ' : 'Venda finalizada ';
    registrar_log('vendas', $tipoTexto . $numero, $vendaId, null, [
        'cliente_id' => $clienteId, 'total' => $total, 'itens' => count($detalhes), 'status' => $tipoOperacao, 'tipo_pedido' => $motivo,
    ]);
    flash('success', ($ehOrcamento ? 'Orçamento ' : 'Venda ') . $numero . ' salvo com sucesso!');
    redirecionar('vendas/ver.php?id=' . $vendaId);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'salvar_venda');
    flash('danger', 'Não foi possível finalizar a venda. A operação foi cancelada.');
    redirecionar('vendas/nova.php');
}