<?php
require_once __DIR__ . '/../config/config.php';

exigir_csrf();

$codigo = trim((string)($_POST['licenca'] ?? ''));
if ($codigo !== '' && licenca_validar($codigo)) {
    salvar_config('licenca_chave', $codigo);
    $_SESSION['licenca_ok'] = 'Licença ativada com sucesso.';
    redirecionar('login/index.php');
}

$_SESSION['licenca_erro'] = 'A chave informada não é válida para esta instalação. Verifique o código e tente novamente.';
redirecionar('licenca/index.php');