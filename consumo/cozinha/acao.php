<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('cozinha_alterar_status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resposta(false, 'Método não permitido.', null, 405);
}
exigir_csrf();

$itemId = (int)($_POST['item_id'] ?? 0);
$novoStatus = strtoupper(trim((string)($_POST['status'] ?? '')));
$pdo = db();
$usuarioId = (int)(usuario_atual()['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM comanda_itens WHERE id = ? LIMIT 1');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    json_resposta(false, 'Item não encontrado.', null, 404);
}

$comanda = comanda_buscar((int)$item['comanda_id']);
if (!$comanda || (string)$comanda['status'] !== 'ABERTA') {
    json_resposta(false, 'A comanda deste item não está mais aberta.', null, 409);
}

$statusAtual = (string)$item['status'];
if (!item_transicao_valida($statusAtual, $novoStatus)) {
    json_resposta(false, 'Não é possível mudar de ' . $statusAtual . ' para ' . $novoStatus . '.', null, 422);
}

try {
    $pdo->beginTransaction();

    $preparador = $novoStatus === 'PREPARANDO' ? $usuarioId : $item['preparador_id'];
    $entreguePor = $novoStatus === 'ENTREGUE' ? $usuarioId : $item['entregue_por'];

    // O status atual vai no WHERE: duas pessoas clicando ao mesmo tempo não
    // produzem uma transição inválida.
    $stmt = $pdo->prepare(
        "UPDATE comanda_itens
            SET status = ?, preparador_id = ?, entregue_por = ?, data_atualizacao = NOW()
          WHERE id = ? AND status = ?"
    );
    $stmt->execute([$novoStatus, $preparador ?: null, $entreguePor ?: null, $itemId, $statusAtual]);
    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        json_resposta(false, 'Outro usuário alterou este item. A tela será atualizada.', null, 409);
    }

    // Ao entregar, se não restar nada pendente a comanda avisa o atendimento.
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'status_item_cozinha');
    json_resposta(false, 'Não foi possível alterar o status do item.', null, 500);
}

registrar_log('consumo', 'Item #' . $itemId . ' na comanda ' . $comanda['numero'] . ': '
    . $statusAtual . ' -> ' . $novoStatus, $itemId, ['status' => $statusAtual], ['status' => $novoStatus]);

json_resposta(true, 'Item atualizado para ' . $novoStatus . '.', [
    'item_id' => $itemId,
    'status' => $novoStatus,
]);
