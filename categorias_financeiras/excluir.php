<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('categorias_financeiras/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$categoria = buscar_linha('categorias_financeiras', $id);
if (!$categoria) {
    flash('danger', 'Categoria não encontrada.');
    redirecionar('categorias_financeiras/index.php');
}

try {
    db()->prepare('DELETE FROM categorias_financeiras WHERE id = ?')->execute([$id]);
    registrar_log('categorias_fin', 'Categoria financeira excluída #' . $id, $id, $categoria, null);
    flash('success', 'Categoria excluída.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_categoria_fin');
    flash('danger', 'Não foi possível excluir a categoria.');
}
redirecionar('categorias_financeiras/index.php');