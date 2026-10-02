<?php
require_once __DIR__ . '/../config/config.php';

$status = licenca_status();
$dias = licenca_dias_restantes();
$instalacao = licenca_instalacao();
$erro = $_SESSION['licenca_erro'] ?? null;
unset($_SESSION['licenca_erro']);
$nomeEmpresa = obter_config('empresa_nome', 'Gestor Comercial');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Ativar licença - <?= e($nomeEmpresa) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url_asset(ASSETS . '/css/app.css') ?>">
</head>
<body class="login-body">
    <div class="login-card card">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo"><i class="bi bi-shield-lock"></i></div>
                <h1 class="login-titulo mt-3 mb-0">Ativação da licença</h1>
                <p class="login-sub mb-0"><?= e($nomeEmpresa) ?> - <?= e(APP_NAME) ?></p>
            </div>

            <?php if ($status === 'trial'): ?>
                <div class="alert alert-info py-2 small" role="alert">
                    <i class="bi bi-clock-history me-1"></i>
                    Versão de demonstração — restam <b><?= (int)$dias ?></b> dia(s).
                    O sistema continua liberado para teste.
                </div>
            <?php else: ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    O período de demonstração terminou. Informe uma chave de licença para continuar usando o sistema.
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
            <div class="alert alert-warning py-2 small" role="alert">
                <i class="bi bi-x-circle me-1"></i><?= e($erro) ?>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="codInstalacao">Código de instalação</label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="codInstalacao" value="<?= e($instalacao) ?>" readonly>
                    <button type="button" class="btn btn-outline-secondary" id="btnCopiar" title="Copiar código">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="form-text">
                    Envie este código ao seu fornecedor para receber a chave de licença.
                </div>
            </div>

            <form method="post" action="<?= url('licenca/validar.php') ?>" id="formLicenca" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="licenca">Chave de licença</label>
                    <input type="text" class="form-control font-monospace text-uppercase text-center" id="licenca" name="licenca"
                           placeholder="GC1-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2" id="btnAtivar">
                    <i class="bi bi-check2-circle me-1"></i> Ativar licença
                </button>
            </form>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $('#btnCopiar').on('click', function () {
            const el = $('#codInstalacao')[0];
            el.select();
            el.setSelectionRange(0, 99999);
            const ok = navigator.clipboard && navigator.clipboard.writeText($('#codInstalacao').val());
            if (ok) {
                $(this).html('<i class="bi bi-check-lg"></i>');
                setTimeout(() => $(this).html('<i class="bi bi-clipboard"></i>'), 1200);
            } else {
                document.execCommand('copy');
            }
        });
        $('#formLicenca').on('submit', function () {
            $('#btnAtivar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Ativando...');
        });
    </script>
</body>
</html>