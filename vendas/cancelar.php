<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('vendas_cancelar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('vendas/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM vendas WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$venda = $stmt->fetch();
if (!$venda) {
    flash('danger', 'Venda não encontrada.');
    redirecionar('vendas/index.php');
}
if ($venda['status'] !== 'FINALIZADA') {
    flash('warning', 'Esta venda não pode mais ser cancelada.');
    redirecionar('vendas/ver.php?id=' . $id);
}

$tipoPedido = $venda['tipo_pedido_id'] ? buscar_linha('tipos_pedido', (int)$venda['tipo_pedido_id']) : null;
$movEstoque = $tipoPedido ? (int)$tipoPedido['movimenta_estoque'] === 1 : true;
$geraFinanceiro = $tipoPedido ? (int)$tipoPedido['gera_financeiro'] === 1 : true;
$estoqueEntrada = $tipoPedido ? (int)$tipoPedido['estoque_entrada'] === 1 : false;

try {

    if ($geraFinanceiro) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM contas_receber WHERE venda_id = ? AND valor_pago > 0');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash('danger', 'Esta venda possui recebimentos registrados. Estorne a baixa antes de cancelar.');
            redirecionar('vendas/ver.php?id=' . $id);
        }
    }

    $pdo->beginTransaction();

    if ($movEstoque) {
        $stmtItens = $pdo->prepare(
            'SELECT vi.*, p.estoque_atual AS estoque_produto
               FROM venda_itens vi JOIN produtos p ON p.id = vi.produto_id WHERE vi.venda_id = ?'
        );
        $stmtItens->execute([$id]);
        $stmtEstoque = $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?');
        $stmtMov = $pdo->prepare(
            'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
             VALUES (?, ?, ?, ?, ?, NULL, \'ESTORNO VENDA\', ?, ?, NOW(), ?)'
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
                $venda['numero'],
                (int)$venda['criado_por'],
                'Estorno venda ' . $venda['numero'],
            ]);
        }
    }

    if ($geraFinanceiro) {
        $stmtFluxo = $pdo->prepare("SELECT * FROM fluxo_caixa WHERE referencia = ? AND estorno = 0");
        $stmtFluxo->execute(['VENDA#' . $id]);
        foreach ($stmtFluxo->fetchAll() as $fl) {
            $tipoEstorno = $fl['tipo'] === 'ENTRADA' ? 'SAIDA' : 'ENTRADA';
            registrar_fluxo($tipoEstorno, $fl['categoria'] ?: 'OUTRO', 'Estorno: ' . $fl['descricao'], $fl['valor'], 'VENDA#' . $id . '#ESTORNO');
            $pdo->prepare('UPDATE fluxo_caixa SET estorno = 1 WHERE id = ?')->execute([(int)$fl['id']]);
        }

        $pdo->prepare("UPDATE venda_parcelas SET status = 'CANCELADO' WHERE venda_id = ? AND status IN ('PENDENTE','VENCIDO')")
            ->execute([$id]);
        $pdo->prepare("UPDATE contas_receber SET status = 'CANCELADO' WHERE venda_id = ? AND status IN ('PENDENTE','VENCIDO')")
            ->execute([$id]);
    }

    $motivo = sanear($_POST['motivo'] ?? 'Cancelamento realizado pelo usuário');
    $pdo->prepare(
        "UPDATE vendas SET status = 'CANCELADA', cancelado_por = ?, cancelado_em = NOW(), motivo_cancelamento = ? WHERE id = ?"
    )->execute([(int)$_SESSION['user_id'], $motivo, $id]);

    $pdo->commit();

    registrar_log('vendas', 'Venda cancelada ' . $venda['numero'], $id, ['status' => 'FINALIZADA'], ['status' => 'CANCELADA', 'motivo' => $motivo]);
    flash('success', 'Venda ' . $venda['numero'] . ' cancelada com sucesso.');
    redirecionar('vendas/ver.php?id=' . $id);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'cancelar_venda');
    flash('danger', 'Não foi possível cancelar a venda.');
    redirecionar('vendas/index.php');
}