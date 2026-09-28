<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('produtos_excluir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('produtos/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$produto = buscar_linha('produtos', $id);
if (!$produto) {
    flash('danger', 'Produto não encontrado.');
    redirecionar('produtos/index.php');
}

try {
    // Exclui foto se existir
    if ($produto['foto'] && file_exists(BASE_PATH . '/' . $produto['foto'])) {
        @unlink(BASE_PATH . '/' . $produto['foto']);
    }
    db()->prepare('DELETE FROM produtos WHERE id = ?')->execute([$id]);
    registrar_log('produtos', 'Produto excluído #' . $id, $id, $produto, null);
    flash('success', 'Produto excluído com sucesso.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_produto');
    flash('danger', 'Não foi possível excluir o produto.');
}
redirecionar('produtos/index.php');