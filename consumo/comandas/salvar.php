<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('consumo/comandas/index.php');
}
exigir_csrf();

$acao = (string)($_POST['acao'] ?? '');
$pdo = db();
$usuario = usuario_atual();
$usuarioId = (int)($usuario['id'] ?? 0);

switch ($acao) {

    case 'abrir':
        exigir_permissao('comandas_criar');

        $mesaId = (int)($_POST['mesa_id'] ?? 0);
        $observacao = trim((string)($_POST['observacao'] ?? ''));

        $mesa = mesa_buscar($mesaId);
        if (!$mesa) {
            flash('danger', 'Mesa não encontrada.');
            redirecionar('consumo/mesas/index.php');
        }
        if ((int)$mesa['ativo'] !== 1) {
            flash('danger', 'A mesa está inativa e não recebe comandas.');
            redirecionar('consumo/mesas/index.php');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comandas WHERE mesa_id = ? AND status = 'ABERTA'");
        $stmt->execute([$mesaId]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash('warning', 'Esta mesa já possui uma comanda aberta.');
            redirecionar('consumo/mesas/index.php');
        }

        try {
            $pdo->beginTransaction();

            // UPDATE condicional: garante a ocupação uma única vez, mesmo com
            // duas requisições simultâneas para a mesma mesa.
            $stmt = $pdo->prepare(
                "UPDATE mesas SET status = 'OCUPADA' WHERE id = ? AND ativo = 1 AND status IN ('LIVRE', 'RESERVADA')"
            );
            $stmt->execute([$mesaId]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                flash('warning', 'A mesa foi ocupada por outro atendente. Escolha outra mesa.');
                redirecionar('consumo/mesas/index.php');
            }

            $numero = gerar_numero('comandas', 'numero');
            $stmt = $pdo->prepare(
                "INSERT INTO comandas (numero, mesa_id, garcom_id, status, data_abertura, observacao, criado_em, atualizado_em)
                 VALUES (?, ?, ?, 'ABERTA', NOW(), ?, NOW(), NOW())"
            );
            $stmt->execute([$numero, $mesaId, $usuarioId, $observacao !== '' ? $observacao : null]);
            $comandaId = (int)$pdo->lastInsertId();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            erro_banco($e, 'abrir_comanda');
            flash('danger', 'Não foi possível abrir a comanda.');
            redirecionar('consumo/mesas/index.php');
        }

        registrar_log('consumo', 'Comanda aberta ' . $numero, $comandaId, null, [
            'mesa_id' => $mesaId, 'garcom_id' => $usuarioId,
        ]);
        flash('success', 'Comanda ' . $numero . ' aberta. Escolha os itens do cardápio.');
        redirecionar('consumo/comandas/ver.php?id=' . $comandaId);
        break;

    case 'add_item':
        exigir_permissao('comandas_item');

        $comandaId = (int)($_POST['comanda_id'] ?? 0);
        $itemCardapioId = (int)($_POST['item_id'] ?? 0);
        $quantidade = parse_decimal($_POST['qtd'] ?? '1');
        $observacao = trim((string)($_POST['observacao_item'] ?? ''));

        $comanda = comanda_buscar($comandaId);
        $voltarPara = 'consumo/comandas/ver.php?id=' . $comandaId;

        if (!$comanda || (string)$comanda['status'] !== 'ABERTA') {
            flash('danger', 'A comanda não está aberta.');
            redirecionar($voltarPara);
        }
        if (!comanda_pode_manipular($comanda)) {
            flash('danger', 'Você não pode alterar comandas de outros atendentes.');
            redirecionar($voltarPara);
        }
        if ($quantidade <= 0) {
            flash('warning', 'Informe uma quantidade maior que zero.');
            redirecionar($voltarPara);
        }
        if ($quantidade > 999) {
            flash('warning', 'Quantidade acima do limite permitido.');
            redirecionar($voltarPara);
        }

        $item = cardapio_item($itemCardapioId);
        if (!$item || (int)$item['ativo'] !== 1) {
            flash('danger', 'Item do cardápio indisponível.');
            redirecionar($voltarPara);
        }

        try {
            $pdo->beginTransaction();

            $preco = (float)$item['preco'];
            $subtotal = round($preco * $quantidade, 2);
            $stmt = $pdo->prepare(
                "INSERT INTO comanda_itens (comanda_id, cardapio_item_id, descricao, quantidade, preco_unitario,
                                           subtotal, total, status, observacoes, data_pedido)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDENTE', ?, NOW())"
            );
            $stmt->execute([$comandaId, $itemCardapioId, $item['descricao'], $quantidade, $preco, $subtotal,
                $subtotal, $observacao !== '' ? $observacao : null]);
            $itemId = (int)$pdo->lastInsertId();

            comanda_recalcular($comandaId, $pdo);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            erro_banco($e, 'add_item_comanda');
            flash('danger', 'Não foi possível adicionar o item.');
            redirecionar($voltarPara);
        }

        registrar_log('consumo', 'Item adicionado à comanda ' . $comanda['numero'] . ' #' . $itemId,
            $comandaId, null, ['item_id' => $itemCardapioId, 'qtd' => $quantidade]);
        flash('success', formatar_qtde($quantidade) . 'x ' . $item['descricao'] . ' adicionado à comanda.');
        redirecionar($voltarPara);
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('consumo/comandas/index.php');
}
