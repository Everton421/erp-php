<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('compras_criar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('compras/index.php');
}
exigir_csrf();

$pdo = db();
$usuario = usuario_atual();
$atualizarCusto = (int)obter_config('atualizar_custo_compra', '1') === 1;
$diasVenc = max((int)obter_config('dias_vencimento', '30'), 1);

$_SESSION['compra_rascunho'] = [
    'fornecedor_id' => (int)($_POST['fornecedor_id'] ?? 0),
    'data_compra' => (string)($_POST['data_compra'] ?? hoje()),
    'tipo_pedido_id' => (int)($_POST['tipo_pedido_id'] ?? 0),
    'desconto' => (string)($_POST['desconto'] ?? '0'),
    'acrescimo' => (string)($_POST['acrescimo'] ?? '0'),
    'observacao' => (string)($_POST['observacao'] ?? ''),
    'rascunho_json' => (string)($_POST['rascunho_json'] ?? ''),
];

$itens = json_decode((string)($_POST['itens_json'] ?? ''), true);
$pagamentos = json_decode((string)($_POST['pagamentos_json'] ?? ''), true);

if (!is_array($itens) || !count($itens)) {
    flash('danger', 'Adicione ao menos um item à compra.');
    voltar();
}

$fornecedorId = (int)($_POST['fornecedor_id'] ?? 0);
if ($fornecedorId > 0 && !buscar_linha('fornecedores', $fornecedorId)) {
    flash('danger', 'Fornecedor não encontrado.');
    voltar();
}

$desconto = max(parse_decimal($_POST['desconto'] ?? '0'), 0);
$acrescimo = max(parse_decimal($_POST['acrescimo'] ?? '0'), 0);
$observacao = sanear($_POST['observacao'] ?? '');
$dataCompra = parse_data($_POST['data_compra'] ?? '') ?: hoje();

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

if ($geraFinanceiro && (!is_array($pagamentos) || !count($pagamentos))) {
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
        $custo = (float)($item['custo_unitario'] ?? 0);
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
        if ($qtd <= 0 || $custo < 0) {
            flash('danger', 'Quantidade/custo inválido para "' . $produto['descricao'] . '".');
            voltar();
        }
        $descItem = min($descItem, $qtd * $custo);
        $sub = $qtd * $custo;
        $subtotal += $sub;
        $descontoItens += $descItem;
        $detalhes[] = [
            'produto' => $produto,
            'quantidade' => $qtd,
            'custo' => $custo,
            'desconto' => $descItem,
            'subtotal' => $sub,
            'total' => $sub - $descItem,
        ];
    }

    $total = ($subtotal - $descontoItens) - $desconto + $acrescimo;
    if ($total < 0) {
        flash('danger', 'Total da compra não pode ser negativo.');
        voltar();
    }

    $pagamentosPuros = [];
    $somaPgtos = 0.0;
    if ($geraFinanceiro) {
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
                flash('danger', 'Forma de pagamento não encontrada ou inativa.');
                voltar();
            }
            $somaPgtos += $valor;
            $pagamentosPuros[] = [
                'forma' => $forma,
                'valor' => $valor,
                'qtde_parcelas' => $qtdeParc,
                'situacao' => ($p['situacao'] ?? 'pago') === 'pago' ? 'PAGO' : 'PENDENTE',
            ];
        }
        if (abs($somaPgtos - $total) > 0.005) {
            flash('danger', 'O total dos pagamentos (' . formatar_moeda($somaPgtos) . ') deve ser igual ao total da compra (' . formatar_moeda($total) . ').');
            voltar();
        }
    }

    $pdo->beginTransaction();

    $numero = gerar_numero('compras', 'numero');
    $dataHora = $dataCompra . ' ' . date('H:i:s');
    $hoje = date('Y-m-d');

    $stmt = $pdo->prepare(
        'INSERT INTO compras (numero, fornecedor_id, tipo_pedido_id, data_compra, subtotal, desconto, acrescimo, total, status, observacao, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'FINALIZADA\', ?, ?)'
    );
    $stmt->execute([
        $numero,
        $fornecedorId ?: null,
        $tipoPedidoId,
        $dataHora,
        round($subtotal, 2),
        round($descontoItens + $desconto, 2),
        round($acrescimo, 2),
        round($total, 2),
        $observacao ?: null,
        (int)$usuario['id'],
    ]);
    $compraId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
        'INSERT INTO compra_itens (compra_id, produto_id, quantidade, custo_unitario, desconto, subtotal, total)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtEstoque = $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?');
    $stmtMov = $pdo->prepare(
        'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtCusto = $pdo->prepare('UPDATE produtos SET preco_custo = ? WHERE id = ?');

    $movTipo = $estoqueEntrada ? 'ENTRADA' : 'SAIDA';
    $motivo = $tipoPedido['nome'];

    foreach ($detalhes as $d) {
        $prod = $d['produto'];
        $anterior = (float)$prod['estoque_atual'];
        $sinal = $estoqueEntrada ? 1 : -1;
        $posterior = $anterior + $sinal * $d['quantidade'];

        $stmtItem->execute([
            $compraId,
            (int)$prod['id'],
            round($d['quantidade'], 3),
            round($d['custo'], 2),
            round($d['desconto'], 2),
            round($d['subtotal'], 2),
            round($d['total'], 2),
        ]);
        if ($movEstoque) {
            $stmtEstoque->execute([$posterior, (int)$prod['id']]);
            $stmtMov->execute([
                (int)$prod['id'],
                $movTipo,
                round($d['quantidade'], 3),
                $anterior,
                $posterior,
                round($d['custo'], 2),
                $motivo,
                $numero,
                (int)$usuario['id'],
                $dataHora,
                'Compra ' . $numero,
            ]);
        }
        if ($movEstoque && $estoqueEntrada && $atualizarCusto) {
            $stmtCusto->execute([round($d['custo'], 4), (int)$prod['id']]);
        }
    }

    if ($geraFinanceiro) {
        $stmtPg = $pdo->prepare(
            'INSERT INTO compra_pagamentos (compra_id, forma_pagamento_id, valor, qtde_parcelas, data_pagamento)
             VALUES (?, ?, ?, ?, NULL)'
        );
        $stmtParcela = $pdo->prepare(
            'INSERT INTO compra_parcelas (compra_id, compra_pagamento_id, numero, vencimento, valor, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmtCp = $pdo->prepare(
            'INSERT INTO contas_pagar (fornecedor_id, compra_id, compra_parcela_id, categoria_id, documento, descricao, parcela_numero, valor, vencimento, data_pagamento, valor_pago, juros, multa, desconto, status, forma_pagamento_id, observacao)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)'
        );

        foreach ($pagamentosPuros as $pg) {
            $stmtPg->execute([
                $compraId,
                (int)$pg['forma']['id'],
                round($pg['valor'], 2),
                $pg['qtde_parcelas'],
            ]);
            $pagamentoId = (int)$pdo->lastInsertId();

            if ($pg['qtde_parcelas'] > 1) {
                $base = floor(($pg['valor'] * 100) / $pg['qtde_parcelas']) / 100;
                $soma = 0.0;
                for ($n = 1; $n <= $pg['qtde_parcelas']; $n++) {
                    $valorParc = ($n === $pg['qtde_parcelas']) ? round($pg['valor'] - $soma, 2) : $base;
                    $soma += $valorParc;
                    $vencimento = date('Y-m-d', strtotime('+' . ($n * $diasVenc) . ' days', strtotime($hoje)));
                    $stmtParcela->execute([$compraId, $pagamentoId, $n, $vencimento, $valorParc, 'PENDENTE']);
                    $parcelaId = (int)$pdo->lastInsertId();
                    $stmtCp->execute([
                        $fornecedorId ?: null,
                        $compraId,
                        $parcelaId,
                        $numero,
                        'Compra ' . $numero . ' (parcela ' . $n . ')',
                        $n . '/' . $pg['qtde_parcelas'],
                        $valorParc,
                        $vencimento,
                        null,
                        0,
                        'PENDENTE',
                        (int)$pg['forma']['id'],
                        'Parcela ' . $n . ' da compra ' . $numero,
                    ]);
                }
            } else {
                if ($pg['situacao'] === 'PAGO') {
                    $stmtParcela->execute([$compraId, $pagamentoId, 1, $hoje, round($pg['valor'], 2), 'PAGO']);
                    $parcelaId = (int)$pdo->lastInsertId();
                    $stmtCp->execute([
                        $fornecedorId ?: null,
                        $compraId,
                        $parcelaId,
                        $numero,
                        'Pagamento à vista - Compra ' . $numero,
                        'À VISTA',
                        round($pg['valor'], 2),
                        $hoje,
                        $dataHora,
                        round($pg['valor'], 2),
                        'PAGO',
                        (int)$pg['forma']['id'],
                        'Pagamento à vista da compra ' . $numero,
                    ]);
                    registrar_fluxo('SAIDA', 'COMPRA', 'Compra ' . $numero . ' (' . $pg['forma']['nome'] . ')', $pg['valor'], 'COMPRA#' . $compraId);
                } else {
                    $vencimento = date('Y-m-d', strtotime('+' . $diasVenc . ' days', strtotime($hoje)));
                    $stmtParcela->execute([$compraId, $pagamentoId, 1, $vencimento, round($pg['valor'], 2), 'PENDENTE']);
                    $parcelaId = (int)$pdo->lastInsertId();
                    $stmtCp->execute([
                        $fornecedorId ?: null,
                        $compraId,
                        $parcelaId,
                        $numero,
                        'Compra ' . $numero . ' (a pagar)',
                        'À PRAZO',
                        round($pg['valor'], 2),
                        $vencimento,
                        null,
                        0,
                        'PENDENTE',
                        (int)$pg['forma']['id'],
                        'Compra ' . $numero . ' a pagar',
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    unset($_SESSION['compra_rascunho']);

    registrar_log('compras', 'Compra finalizada ' . $numero, $compraId, null, [
        'fornecedor_id' => $fornecedorId, 'total' => $total, 'itens' => count($detalhes), 'tipo_pedido' => $motivo,
    ]);
    flash('success', 'Compra ' . $numero . ' registrada com sucesso!');
    redirecionar('compras/ver.php?id=' . $compraId);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'salvar_compra');
    flash('danger', 'Não foi possível registrar a compra. A operação foi cancelada.');
    redirecionar('compras/nova.php');
}