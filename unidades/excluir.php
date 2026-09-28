<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('unidades_excluir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('unidades/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$un = buscar_linha('unidades', $id);
if (!$un) {
    flash('danger', 'Unidade não encontrada.');
    redirecionar('unidades/index.php');
}

try {
    db()->prepare('DELETE FROM unidades WHERE id = ?')->execute([$id]);
    registrar_log('unidades', 'Unidade excluída #' . $id, $id, $un, null);
    flash('success', 'Unidade excluída.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_unidade');
    flash('danger', 'Não foi possível excluir a unidade.');
}
redirecionar('unidades/index.php');