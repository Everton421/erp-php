<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resposta(false, 'Método não permitido.', null, 405);
}

exigir_login();
exigir_csrf();

$usuario = usuario_atual();
$atual = (string)($_POST['senha_atual'] ?? '');
$nova  = (string)($_POST['senha_nova'] ?? '');
$nova2 = (string)($_POST['senha_nova2'] ?? '');

if (!password_verify($atual, $usuario['senha'])) {
    json_resposta(false, 'A senha atual está incorreta.');
}
if (strlen($nova) < 6) {
    json_resposta(false, 'A nova senha deve ter no mínimo 6 caracteres.');
}
if ($nova !== $nova2) {
    json_resposta(false, 'As senhas não conferem.');
}

try {
    $stmt = db()->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
    $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), (int)$usuario['id']]);
    registrar_log('usuarios', 'Alteração de própria senha', (int)$usuario['id']);
    json_resposta(true, 'Senha alterada com sucesso!');
} catch (Throwable $e) {
    erro_banco($e, 'alterar_senha');
    json_resposta(false, 'Não foi possível alterar a senha. Tente novamente.');
}