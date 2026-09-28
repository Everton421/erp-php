<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('categorias_excluir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('categorias/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$cat = buscar_linha('categorias', $id);
if (!$cat) {
    flash('danger', 'Categoria não encontrada.');
    redirecionar('categorias/index.php');
}

try {
    db()->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
    registrar_log('categorias', 'Categoria excluída #' . $id, $id, $cat, null);
    flash('success', 'Categoria excluída.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_categoria');
    flash('danger', 'Não foi possível excluir a categoria.');
}
redirecionar('categorias/index.php');