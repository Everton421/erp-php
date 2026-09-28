<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('login/index.php');
}

exigir_csrf();

$usuarioLogin = sanear($_POST['usuario'] ?? '');
$senha        = (string)($_POST['senha'] ?? '');

if ($usuarioLogin === '' || $senha === '') {
    $_SESSION['login_erro'] = 'Informe usuário e senha.';
    $_SESSION['login_email'] = $usuarioLogin;
    redirecionar('login/index.php');
}

try {
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE (usuario = ? OR email = ?) LIMIT 1');
    $stmt->execute([$usuarioLogin, $usuarioLogin]);
    $usuario = $stmt->fetch();

    $agora = new DateTime();

    // Usuário não encontrado: mensagem genérica de segurança
    if (!$usuario) {
        registrar_log('login', 'Tentativa de login: usuário inexistente (' . substr($usuarioLogin, 0, 40) . ')', null, null, null);
        $_SESSION['login_erro'] = 'Usuário ou senha inválidos.';
        redirecionar('login/index.php');
    }

    $id = (int)$usuario['id'];
    $bloqueio = isset($usuario['bloqueado_ate']) && $usuario['bloqueado_ate']
        ? DateTime::createFromFormat('Y-m-d H:i:s', $usuario['bloqueado_ate'])
        : null;

    if ($bloqueio && $bloqueio > $agora) {
        $_SESSION['login_erro'] = 'Conta temporariamente bloqueada por excesso de tentativas. Tente novamente em alguns minutos.';
        redirecionar('login/index.php');
    }

    if ((int)$usuario['status'] !== 1) {
        registrar_log('login', 'Tentativa de login em usuário inativo #' . $id, $id, null, null);
        $_SESSION['login_erro'] = 'Usuário inativo. Contate o administrador.';
        redirecionar('login/index.php');
    }

    if (!password_verify($senha, $usuario['senha'])) {
        $tentativas = (int)$usuario['tentativas_falhas'] + 1;
        $bloquearAte = null;
        if ($tentativas >= 5) {
            $bloquearAte = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');
            $tentativas = 0;
        }
        $stmt = db()->prepare('UPDATE usuarios SET tentativas_falhas = ?, bloqueado_ate = ? WHERE id = ?');
        $stmt->execute([$tentativas, $bloquearAte, $id]);
        registrar_log('login', 'Senha incorreta #' . $id, $id, null, ['tentativas' => $tentativas]);
        $_SESSION['login_erro'] = $bloquearAte
            ? 'Você excedeu o número de tentativas. Conta bloqueada por 15 minutos.'
            : 'Usuário ou senha inválidos.';
        redirecionar('login/index.php');
    }

    efetuar_login($id);
    $destino = url('dashboard/index.php');
    header('Location: ' . $destino);
    exit;

} catch (Throwable $e) {
    erro_banco($e, 'autenticar');
    $_SESSION['login_erro'] = 'Não foi possível autenticar no momento. Tente novamente.';
    redirecionar('login/index.php');
}