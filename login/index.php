<?php
require_once __DIR__ . '/../config/config.php';

$erro = $_SESSION['login_erro'] ?? null;
unset($_SESSION['login_erro']);
$email = $_SESSION['login_email'] ?? '';
$nomeEmpresa = obter_config('empresa_nome', 'Gestor Comercial');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - <?= e($nomeEmpresa) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url_asset(ASSETS . '/css/app.css') ?>">
    <style>
        .password-toggle { cursor: pointer; }
    </style>
</head>
<body class="login-body">
    <div class="login-card card">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo"><i class="bi bi-currency-dollar"></i></div>
                <h1 class="login-titulo mt-3 mb-0"><?= e($nomeEmpresa) ?></h1>
                <p class="login-sub mb-0"><?= e(APP_NAME) ?></p>
            </div>

            <?php if ($erro): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i><?= e($erro) ?>
            </div>
            <?php endif; ?>

            <form method="post" action="<?= url('login/autenticar.php') ?>" id="formLogin" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="usuario">Usuário ou e-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="usuario" name="usuario" value="<?= e($email) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="senha">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                        <button type="button" class="btn btn-outline-secondary password-toggle" data-target="#senha" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 mt-2" id="btnEntrar">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                </button>
                <div class="text-center mt-3">
                    <a href="<?= url('login/esqueci.php') ?>" class="small">Esqueci minha senha</a>
                </div>
            </form>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $('#formLogin').on('submit', function () {
            $('#btnEntrar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Entrando...');
        });
        $('.password-toggle').on('click', function () {
            const inp = $($(this).data('target'));
            inp.attr('type', inp.attr('type') === 'password' ? 'text' : 'password');
            $(this).find('i').toggleClass('bi-eye bi-eye-slash');
        });
    </script>
</body>
</html>