<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('marcas_excluir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('marcas/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$marca = buscar_linha('marcas', $id);
if (!$marca) {
    flash('danger', 'Marca não encontrada.');
    redirecionar('marcas/index.php');
}

try {
    db()->prepare('DELETE FROM marcas WHERE id = ?')->execute([$id]);
    registrar_log('marcas', 'Marca excluída #' . $id, $id, $marca, null);
    flash('success', 'Marca excluída.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_marca');
    flash('danger', 'Não foi possível excluir a marca.');
}
redirecionar('marcas/index.php');