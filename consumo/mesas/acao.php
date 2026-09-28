<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('mesas_editar');

$acao = (string)($_POST['acao'] ?? 'liberar');
$mesaId = (int)($_POST['mesa_id'] ?? 0);
$ajax = is_ajax();

/**
 * A grade de mesas recarrega sozinha; a resposta serve tanto para o
 * AJAX quanto para o envio de formulário tradicional.
 */
$responder = static function (bool $ok, string $msg, array $dados = []) use ($ajax): void {
    if ($ajax) {
        json_resposta($ok, $msg, $dados, $ok ? 200 : 400);
    }
    flash($ok ? 'success' : 'danger', $msg);
    redirecionar('consumo/mesas/index.php');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($ajax) {
        json_resposta(false, 'Método não permitido.', null, 405);
    }
    redirecionar('consumo/mesas/index.php');
}
exigir_csrf();

$pdo = db();
$mesa = mesa_buscar($mesaId);
if (!$mesa) {
    $responder(false, 'Mesa não encontrada.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM comandas WHERE mesa_id = ? AND status = 'ABERTA'");
$stmt->execute([$mesaId]);
$comandaAberta = (int)$stmt->fetchColumn() > 0;

try {
    switch ($acao) {

        case 'liberar':
            if ($comandaAberta) {
                $responder(false, 'A mesa possui comanda aberta. Feche ou cancele a comanda antes de liberá-la.');
            }
            if ($mesa['status'] === 'LIVRE') {
                $responder(true, 'A mesa já está livre.');
            }
            $antes = $mesa;
            $pdo->prepare("UPDATE mesas SET status = 'LIVRE' WHERE id = ? AND status <> 'OCUPADA'")->execute([$mesaId]);
            registrar_log('consumo', 'Mesa liberada #' . $mesaId, $mesaId, $antes, ['status' => 'LIVRE']);
            $responder(true, 'Mesa liberada com sucesso.');

        case 'reservar':
            if ($comandaAberta) {
                $responder(false, 'A mesa possui comanda aberta e não pode ser reservada.');
            }
            if ($mesa['status'] === 'RESERVADA') {
                $responder(true, 'A mesa já está reservada.');
            }
            $antes = $mesa;
            $pdo->prepare("UPDATE mesas SET status = 'RESERVADA' WHERE id = ? AND status = 'LIVRE'")->execute([$mesaId]);
            registrar_log('consumo', 'Mesa reservada #' . $mesaId, $mesaId, $antes, ['status' => 'RESERVADA']);
            $responder(true, 'Mesa reservada com sucesso.');

        default:
            $responder(false, 'Ação inválida.');
    }
} catch (Throwable $e) {
    erro_banco($e, 'acao_mesa');
    $responder(false, 'Não foi possível concluir a ação na mesa.');
}
