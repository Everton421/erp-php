<?php
require_once __DIR__ . '/../config/config.php';

if (usuario_atual()) {
    redirecionar('dashboard/index.php');
}

$enviado = false;
$erro    = null;
$linkDebug = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $email = sanear($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        try {
            $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $token = bin2hex(random_bytes(32));
                $stmt = db()->prepare(
                    "UPDATE recuperacao_tokens SET usado = 1 WHERE usuario_id = ?"
                );
                $stmt->execute([(int)$usuario['id']]);

                $stmt = db()->prepare(
                    'INSERT INTO recuperacao_tokens (usuario_id, token, validade) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
                );
                $stmt->execute([(int)$usuario['id'], $token]);

                registrar_log('recuperacao', 'Solicitação de recuperação de senha', (int)$usuario['id']);

                $link = url('login/redefinir.php') . '?token=' . $token;
                $corpo = "Olá " . $usuario['nome'] . ",\n\n"
                    . "Para redefinir sua senha de acesso ao sistema, acesse o link abaixo:\n"
                    . $link . "\n\n"
                    . "O link é válido por 1 hora.\n\nSe não foi você, ignore este e-mail.";

                $headers = [
                    'From: ' . (obter_config('empresa_nome', 'Sistema') . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>'),
                    'Content-Type: text/plain; charset=utf-8',
                    'MIME-Version: 1.0',
                ];

                $okMail = @mail($usuario['email'], 'Redefinição de senha', $corpo, implode("\r\n", $headers));

                if (defined('DEV_MODE') && DEV_MODE) {
                    $linkDebug = $link;
                }
                $enviado = true;
            } else {
                // E-mail não cadastrado: aguardar resposta genérica para não expor usuários
                $enviado = true;
            }
        } catch (Throwable $e) {
            erro_banco($e, 'esqueci_senha');
            $erro = 'Não foi possível processar a solicitação. Tente novamente.';
        }
    }
}

$nomeEmpresa = obter_config('empresa_nome', 'Gestor Comercial');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha - <?= e($nomeEmpresa) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url(ASSETS . '/css/app.css') ?>">
</head>
<body class="login-body">
    <div class="login-card card">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo"><i class="bi bi-key"></i></div>
                <h1 class="login-titulo mt-3 mb-0">Recuperar senha</h1>
                <p class="login-sub mb-0">Informe seu e-mail cadastrado</p>
            </div>

            <?php if ($enviado): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-1"></i>
                Se o e-mail informado estiver cadastrado, enviaremos as instruções de redefinição.
            </div>
            <?php if ($linkDebug): ?>
            <div class="alert alert-warning small">
                <b>Modo de desenvolvimento:</b> o e-mail não pôde ser confirmado.
                Utilize o link abaixo para testar:<br>
                <a href="<?= url($linkDebug) ?>"><?= e($linkDebug) ?></a>
            </div>
            <?php endif; ?>
            <a href="<?= url('login/index.php') ?>" class="btn btn-outline-secondary w-100">Voltar para o login</a>

            <?php else: ?>
            <?php if ($erro): ?>
            <div class="alert alert-danger py-2 small"><?= e($erro) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                    <i class="bi bi-envelope-arrow-up me-1"></i> Enviar instruções
                </button>
                <div class="text-center mt-3">
                    <a href="<?= url('login/index.php') ?>" class="small">Voltar para o login</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>