<?php
require_once __DIR__ . '/../config/config.php';

if (usuario_atual()) {
    redirecionar('dashboard/index.php');
}

$sucesso = false;
$erro    = null;
$token   = sanear($_GET['token'] ?? ($_POST['token'] ?? ''));

if ($token === '') {
    redirecionar('login/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $nova = (string)($_POST['senha_nova'] ?? '');
    $nova2 = (string)($_POST['senha_nova2'] ?? '');

    if (strlen($nova) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif ($nova !== $nova2) {
        $erro = 'As senhas não conferem.';
    } else {
        try {
            $stmt = db()->prepare(
                "SELECT * FROM recuperacao_tokens
                  WHERE token = ? AND usado = 0 AND validade > NOW()
                  ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$token]);
            $rec = $stmt->fetch();

            if (!$rec) {
                $erro = 'Link inválido ou expirado. Solicite uma nova recuperação.';
            } else {
                $stmt = db()->prepare('UPDATE usuarios SET senha = ?, tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = ?');
                $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), (int)$rec['usuario_id']]);

                $stmt = db()->prepare('UPDATE recuperacao_tokens SET usado = 1 WHERE id = ?');
                $stmt->execute([(int)$rec['id']]);

                registrar_log('recuperacao', 'Senha redefinida via token', (int)$rec['usuario_id']);
                $sucesso = true;
            }
        } catch (Throwable $e) {
            erro_banco($e, 'redefinir_senha');
            $erro = 'Não foi possível concluir a operação. Tente novamente.';
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
    <title>Redefinir senha - <?= e($nomeEmpresa) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url(ASSETS . '/css/app.css') ?>">
</head>
<body class="login-body">
    <div class="login-card card">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo"><i class="bi bi-shield-lock"></i></div>
                <h1 class="login-titulo mt-3 mb-0">Redefinir senha</h1>
            </div>

            <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-1"></i> Senha redefinida com sucesso! Você já pode entrar.
            </div>
            <a href="<?= url('login/index.php') ?>" class="btn btn-primary w-100">Ir para o login</a>

            <?php else: ?>
            <?php if ($erro): ?>
            <div class="alert alert-danger py-2 small"><?= e($erro) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3">
                    <label class="form-label" for="senha_nova">Nova senha</label>
                    <input type="password" class="form-control" id="senha_nova" name="senha_nova" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="senha_nova2">Confirmar nova senha</label>
                    <input type="password" class="form-control" id="senha_nova2" name="senha_nova2" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                    <i class="bi bi-check-lg me-1"></i> Redefinir senha
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>