<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('clientes_excluir');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('clientes/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$cliente = buscar_linha('clientes', $id);

if (!$cliente) {
    flash('danger', 'Cliente não encontrado.');
    redirecionar('clientes/index.php');
}

try {
    db()->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
    registrar_log('clientes', 'Cliente excluído #' . $id, $id, $cliente, null);
    flash('success', 'Cliente excluído com sucesso.');
} catch (Throwable $e) {
    erro_banco($e, 'excluir_cliente');
    flash('danger', 'Não foi possível excluir o cliente.');
}
redirecionar('clientes/index.php');