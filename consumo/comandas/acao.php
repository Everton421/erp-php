<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resposta(false, 'Método não permitido.', null, 405);
}
exigir_csrf();

$acao = (string)($_POST['acao'] ?? '');
$itemId = (int)($_POST['item_id'] ?? 0);
$pdo = db();
$usuarioId = (int)(usuario_atual()['id'] ?? 0);

$falhar = static function (string $msg, int $status = 400): void {
    json_resposta(false, $msg, null, $status);
};

/**
 * Carrega o item e a comanda, validando que a comanda está aberta e que o
 * usuário tem permissão para alterá-la.
 */
$contexto = static function (int $itemId) use ($pdo, $falhar): array {
    $stmt = $pdo->prepare('SELECT * FROM comanda_itens WHERE id = ? LIMIT 1');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        $falhar('Item não encontrado.');
    }
    $comanda = comanda_buscar((int)$item['comanda_id']);
    if (!$comanda || (string)$comanda['status'] !== 'ABERTA') {
        $falhar('A comanda não está mais aberta.');
    }
    if (!comanda_pode_manipular($comanda)) {
        $falhar('Você não pode alterar comandas de outros atendentes.');
    }
    return [$item, $comanda];
};

try {
    switch ($acao) {

        /* ---------------- Quantidade ---------------- */
        case 'qtd':
            exigir_permissao('comandas_item');
            [$item, $comanda] = $contexto($itemId);

            if (!item_editavel($item)) {
                json_resposta(false, 'A quantidade só pode ser alterada antes de ' . rotulo_producao() . ' iniciar o preparo.', null, 409);
            }

            $quantidade = parse_decimal($_POST['qtd'] ?? '1');
            if ($quantidade <= 0 || $quantidade > 999) {
                $falhar('Quantidade inválida (entre 0,001 e 999).');
            }

            $subtotal = round((float)$item['preco_unitario'] * $quantidade, 2);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE comanda_itens SET quantidade = ?, subtotal = ?, total = ?
                  WHERE id = ? AND status = ?"
            );
            $stmt->execute([$quantidade, $subtotal, $subtotal, $itemId, $item['status']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $falhar('O item não pôde ser alterado porque mudou de status. Atualize a tela.', 409);
            }
            comanda_recalcular((int)$comanda['id'], $pdo);
            $pdo->commit();

            $comanda = comanda_recalcular((int)$comanda['id']);
            json_resposta(true, 'Quantidade atualizada.', [
                'comanda' => [
                    'subtotal' => (float)$comanda['subtotal'],
                    'total' => (float)$comanda['total'],
                ],
            ]);
            break;

        /* ---------------- Remover item ---------------- */
        case 'remover':
            exigir_permissao('comandas_item');
            [$item, $comanda] = $contexto($itemId);

            if (!item_editavel($item)) {
                json_resposta(false, 'O item já está em preparo. Use a opção de cancelar item.', null, 409);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM comanda_itens WHERE id = ? AND status = ?');
            $stmt->execute([$itemId, $item['status']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $falhar('O item não pôde ser removido porque já mudou de status.', 409);
            }
            comanda_recalcular((int)$comanda['id'], $pdo);
            $pdo->commit();

            registrar_log('consumo', 'Item removido da comanda ' . $comanda['numero'], (int)$comanda['id'],
                ['item_id' => $itemId, 'descricao' => $item['descricao']], null);
            json_resposta(true, 'Item removido.');
            break;

        /* ---------------- Observação do item ---------------- */
        case 'observacao':
            exigir_permissao('comandas_item');
            [$item, $comanda] = $contexto($itemId);

            $observacao = trim((string)($_POST['observacao'] ?? ''));
            if (mb_strlen($observacao) > 255) {
                $falhar('A observação deve ter no máximo 255 caracteres.');
            }
            $stmt = $pdo->prepare('UPDATE comanda_itens SET observacoes = ? WHERE id = ?');
            $stmt->execute([$observacao !== '' ? $observacao : null, $itemId]);

            registrar_log('consumo', 'Observação alterada na comanda ' . $comanda['numero'], $itemId,
                ['item_id' => $itemId, 'observacao' => $item['observacoes']], ['observacao' => $observacao]);
            json_resposta(true, 'Observação atualizada.');
            break;

        /* ---------------- Cancelar item ---------------- */
        case 'cancelar_item':
            exigir_permissao('comandas_item');
            [$item, $comanda] = $contexto($itemId);

            if (!item_transicao_valida((string)$item['status'], 'CANCELADO')) {
                json_resposta(false, 'Este item não pode mais ser cancelado.', null, 409);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE comanda_itens SET status = 'CANCELADO', data_atualizacao = NOW()
                  WHERE id = ? AND status = ?"
            );
            $stmt->execute([$itemId, $item['status']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $falhar('O status do item mudou. Atualize a tela e tente novamente.', 409);
            }
            comanda_recalcular((int)$comanda['id'], $pdo);
            $pdo->commit();

            registrar_log('consumo', 'Item cancelado na comanda ' . $comanda['numero'], $itemId,
                ['item_id' => $itemId, 'descricao' => $item['descricao'], 'status' => $item['status']],
                ['status' => 'CANCELADO']);
            json_resposta(true, 'Item cancelado e removido do total.');
            break;

        /* ---------------- Status do item ---------------- */
        case 'status':
            exigir_permissao('comandas_item');
            [$item, $comanda] = $contexto($itemId);

            $novo = strtoupper(trim((string)($_POST['status'] ?? '')));
            if (!in_array($novo, ['PENDENTE', 'PREPARANDO', 'PRONTO', 'ENTREGUE', 'CANCELADO'], true)) {
                $falhar('Status inválido.');
            }
            if ($novo === (string)$item['status']) {
                json_resposta(true, 'O item já está com esse status.');
                break;
            }
            if (!item_transicao_valida((string)$item['status'], $novo)) {
                json_resposta(false,
                    'Não é possível mudar de ' . $item['status'] . ' para ' . $novo . '.', null, 409);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE comanda_itens SET status = ?, data_atualizacao = NOW()
                  WHERE id = ? AND status = ?"
            );
            $stmt->execute([$novo, $itemId, $item['status']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $falhar('O status do item mudou. Atualize a tela e tente novamente.', 409);
            }
            comanda_recalcular((int)$comanda['id'], $pdo);
            $pdo->commit();

            registrar_log('consumo', 'Status do item alterado na comanda ' . $comanda['numero'], $itemId,
                ['item_id' => $itemId, 'descricao' => $item['descricao'], 'status' => $item['status']],
                ['status' => $novo]);
            json_resposta(true, 'Item marcado como ' . $novo . '.');
            break;

        /* ---------------- Desconto / acréscimo ---------------- */
        case 'desconto':
            exigir_permissao('comandas_desconto');
            $comandaId = (int)($_POST['comanda_id'] ?? 0);
            $comanda = comanda_buscar($comandaId);
            if (!$comanda || (string)$comanda['status'] !== 'ABERTA') {
                $falhar('A comanda não está aberta.');
            }
            if (!comanda_pode_manipular($comanda)) {
                $falhar('Você não pode alterar comandas de outros atendentes.');
            }

            $desconto = parse_decimal($_POST['desconto'] ?? '0');
            $acrescimo = parse_decimal($_POST['acrescimo'] ?? '0');
            if ($desconto < 0 || $acrescimo < 0) {
                $falhar('Desconto e acréscimo não podem ser negativos.');
            }
            if ($desconto + $acrescimo > (float)$comanda['subtotal']) {
                json_resposta(false, 'O desconto e o acréscimo não podem superar o subtotal.', null, 422);
            }

            $antes = $comanda;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE comandas SET desconto = ?, acrescimo = ? WHERE id = ?')
                ->execute([$desconto, $acrescimo, $comandaId]);
            $comanda = comanda_recalcular($comandaId, $pdo);
            $pdo->commit();

            registrar_log('consumo', 'Desconto/acréscimo na comanda ' . $comanda['numero'], $comandaId,
                ['desconto' => $antes['desconto'], 'acrescimo' => $antes['acrescimo']],
                ['desconto' => $desconto, 'acrescimo' => $acrescimo]);
            json_resposta(true, 'Desconto e acréscimo aplicados.', ['comanda' => [
                'subtotal' => (float)$comanda['subtotal'],
                'desconto' => (float)$comanda['desconto'],
                'acrescimo' => (float)$comanda['acrescimo'],
                'total' => (float)$comanda['total'],
            ]]);
            break;

        /* ---------------- Fechar comanda ---------------- */
        case 'fechar':
            exigir_permissao('comandas_fechar');
            $comandaId = (int)($_POST['comanda_id'] ?? 0);
            $comanda = comanda_buscar($comandaId);
            if (!$comanda || (string)$comanda['status'] !== 'ABERTA') {
                $falhar('A comanda não está aberta.');
            }
            if (!comanda_pode_manipular($comanda)) {
                $falhar('Você não pode fechar comandas de outros atendentes.');
            }

            $pendentes = consumo_producao_ativa() ? comanda_itens_pendentes($comandaId) : [];
            if ($pendentes) {
                $lista = [];
                foreach (array_slice($pendentes, 0, 5) as $p) {
                    $lista[] = formatar_qtde($p['quantidade']) . 'x ' . $p['descricao'];
                }
                $extra = count($pendentes) > 5 ? ' e mais ' . (count($pendentes) - 5) . ' item(ns)' : '';
                json_resposta(false,
                    'A comanda não pode ser fechada: ' . count($pendentes)
                    . ' item(ns) ainda em preparo. ' . implode(', ', $lista) . $extra, null, 409);
            }

            $itens = comanda_itens($comandaId, true);
            if (!$itens) {
                json_resposta(false, 'A comanda está sem itens. Adicione itens ou cancele a comanda.', null, 422);
            }

            $antes = $comanda;
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE comandas
                    SET status = 'FECHADA', data_fechamento = NOW(), fechado_por = ?,
                        status_pagamento = 'PENDENTE', valor_pago = 0, atualizado_em = NOW()
                  WHERE id = ? AND status = 'ABERTA'"
            );
            $stmt->execute([$usuarioId ?: null, $comandaId]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $falhar('A comanda foi alterada por outro usuário. Atualize a tela.', 409);
            }
            comanda_recalcular($comandaId, $pdo);
            mesa_liberar((int)$comanda['mesa_id'], $pdo);
            $pdo->commit();

            registrar_log('consumo', 'Comanda ' . $comanda['numero'] . ' fechada', $comandaId, $antes, [
                'status' => 'FECHADA', 'total' => $antes['total'],
            ]);
            json_resposta(true, 'Comanda ' . $comanda['numero'] . ' enviada para o caixa.', [
                'id' => $comandaId, 'numero' => $comanda['numero'],
            ]);
            break;

        /* ---------------- Cancelar comanda ---------------- */
        case 'cancelar_comanda':
            exigir_permissao('comandas_cancelar');
            $comandaId = (int)($_POST['comanda_id'] ?? 0);
            $motivo = trim((string)($_POST['motivo'] ?? ''));
            $comanda = comanda_buscar($comandaId);
            if (!$comanda || (string)$comanda['status'] !== 'ABERTA') {
                $falhar('A comanda não está aberta.');
            }
            if ((float)$comanda['valor_pago'] > 0) {
                json_resposta(false, 'Esta comanda já possui pagamento registrado e não pode ser cancelada.', null, 409);
            }
            if (mb_strlen($motivo) > 255) {
                $falhar('O motivo deve ter no máximo 255 caracteres.');
            }

            $antes = $comanda;
            $pdo->beginTransaction();
            $pdo->prepare(
                "UPDATE comanda_itens SET status = 'CANCELADO', data_atualizacao = NOW()
                  WHERE comanda_id = ? AND status <> 'CANCELADO'"
            )->execute([$comandaId]);
            $pdo->prepare(
                "UPDATE comandas
                    SET status = 'CANCELADA', cancelado_por = ?, cancelado_em = NOW(), motivo_cancelamento = ?,
                        data_fechamento = NOW(), subtotal = 0, desconto = 0, acrescimo = 0, total = 0,
                        atualizado_em = NOW()
                  WHERE id = ? AND status = 'ABERTA'"
            )->execute([$usuarioId ?: null, $motivo !== '' ? $motivo : null, $comandaId]);
            $pdo->prepare('DELETE FROM comanda_pagamentos WHERE comanda_id = ?')->execute([$comandaId]);
            $pdo->prepare("UPDATE contas_receber SET status = 'CANCELADO' WHERE documento = ? AND status <> 'PAGO'")
                ->execute([comanda_documento($comanda)]);
            mesa_liberar((int)$comanda['mesa_id'], $pdo);
            $pdo->commit();

            registrar_log('consumo', 'Comanda ' . $comanda['numero'] . ' cancelada', $comandaId, $antes, [
                'status' => 'CANCELADA', 'motivo' => $motivo,
            ]);
            json_resposta(true, 'Comanda ' . $comanda['numero'] . ' cancelada.', [
                'id' => $comandaId, 'numero' => $comanda['numero'],
            ]);
            break;

        default:
            $falhar('Ação inválida.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'acao_comanda');
    json_resposta(false, 'Não foi possível concluir a ação. Verifique os dados e tente novamente.', null, 500);
}
