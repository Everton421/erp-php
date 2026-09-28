<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('fornecedores_excluir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('fornecedores/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$fornecedor = buscar_linha('fornecedores', $id);

if (!$fornecedor) {
    flash('danger', 'Fornecedor não encontrado.');
    redirecionar('fornecedores/index.php');
}

try {
    db()->prepare('DELETE FROM fornecedores WHERE id = ?')->execute([$id]);
    registrar_log('fornecedores', 'Fornecedor excluído #' . $id, $id, $fornecedor, null);
    flash('success', 'Fornecedor excluído com sucesso.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_fornecedor');
    flash('danger', 'Não foi possível excluir o fornecedor.');
}
redirecionar('fornecedores/index.php');