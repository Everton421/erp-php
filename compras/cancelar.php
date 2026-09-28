<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('compras_cancelar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('compras/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM compras WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$compra = $stmt->fetch();
if (!$compra) {
    flash('danger', 'Compra não encontrada.');
    redirecionar('compras/index.php');
}
if ($compra['status'] !== 'FINALIZADA') {
    flash('warning', 'Esta compra não pode mais ser cancelada.');
    redirecionar('compras/ver.php?id=' . $id);
}

$tipoPedido = $compra['tipo_pedido_id'] ? buscar_linha('tipos_pedido', (int)$compra['tipo_pedido_id']) : null;
$movEstoque = $tipoPedido ? (int)$tipoPedido['movimenta_estoque'] === 1 : true;
$geraFinanceiro = $tipoPedido ? (int)$tipoPedido['gera_financeiro'] === 1 : true;
$estoqueEntrada = $tipoPedido ? (int)$tipoPedido['estoque_entrada'] === 1 : true;

try {

    if ($geraFinanceiro) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM contas_pagar WHERE compra_id = ? AND valor_pago > 0');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash('danger', 'Esta compra possui pagamentos realizados. Estorne a baixa antes de cancelar.');
            redirecionar('compras/ver.php?id=' . $id);
        }
    }

    $pdo->beginTransaction();

    if ($movEstoque) {
        $stmtItens = $pdo->prepare(
            'SELECT ci.*, p.estoque_atual AS estoque_produto
               FROM compra_itens ci JOIN produtos p ON p.id = ci.produto_id WHERE ci.compra_id = ?'
        );
        $stmtItens->execute([$id]);
        $stmtEstoque = $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?');
        $stmtMov = $pdo->prepare(
            'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
             VALUES (?, ?, ?, ?, ?, NULL, \'ESTORNO COMPRA\', ?, ?, NOW(), ?)'
        );

        $sinalEstorno = $estoqueEntrada ? -1 : 1;
        $movTipo = $estoqueEntrada ? 'SAIDA' : 'ENTRADA';

        foreach ($stmtItens->fetchAll() as $i) {
            $anterior = (float)$i['estoque_produto'];
            $posterior = $anterior + $sinalEstorno * (float)$i['quantidade'];
            $stmtEstoque->execute([$posterior, (int)$i['produto_id']]);
            $stmtMov->execute([
                (int)$i['produto_id'],
                $movTipo,
                (float)$i['quantidade'],
                $anterior,
                $posterior,
                $compra['numero'],
                (int)$compra['criado_por'],
                'Estorno compra ' . $compra['numero'],
            ]);
        }
    }

    if ($geraFinanceiro) {
        $stmtFluxo = $pdo->prepare('SELECT * FROM fluxo_caixa WHERE referencia = ? AND estorno = 0');
        $stmtFluxo->execute(['COMPRA#' . $id]);
        foreach ($stmtFluxo->fetchAll() as $fl) {
            $tipoEstorno = $fl['tipo'] === 'ENTRADA' ? 'SAIDA' : 'ENTRADA';
            registrar_fluxo($tipoEstorno, $fl['categoria'] ?: 'OUTRO', 'Estorno: ' . $fl['descricao'], $fl['valor'], 'COMPRA#' . $id . '#ESTORNO');
            $pdo->prepare('UPDATE fluxo_caixa SET estorno = 1 WHERE id = ?')->execute([(int)$fl['id']]);
        }

        $pdo->prepare("UPDATE compra_parcelas SET status = 'CANCELADO' WHERE compra_id = ? AND status IN ('PENDENTE','VENCIDO')")
            ->execute([$id]);
        $pdo->prepare("UPDATE contas_pagar SET status = 'CANCELADO' WHERE compra_id = ? AND status IN ('PENDENTE','VENCIDO')")
            ->execute([$id]);
    }

    $motivo = sanear($_POST['motivo'] ?? 'Cancelamento realizado pelo usuário');
    $pdo->prepare(
        "UPDATE compras SET status = 'CANCELADA', cancelado_por = ?, cancelado_em = NOW(), motivo_cancelamento = ? WHERE id = ?"
    )->execute([(int)$_SESSION['user_id'], $motivo, $id]);

    $pdo->commit();

    registrar_log('compras', 'Compra cancelada ' . $compra['numero'], $id, ['status' => 'FINALIZADA'], ['status' => 'CANCELADA', 'motivo' => $motivo]);
    flash('success', 'Compra ' . $compra['numero'] . ' cancelada.');
    redirecionar('compras/ver.php?id=' . $id);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'cancelar_compra');
    flash('danger', 'Não foi possível cancelar a compra.');
    redirecionar('compras/ver.php?id=' . $id);
}