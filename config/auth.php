<?php
declare(strict_types=1);

/**
 * Autenticação, sessão e controle de permissões.
 * Toda página protegida deve chamar exigir_login() e exigir_permissao();
 * a verificação ocorre sempre no servidor.
 */

function usuario_atual(): ?array
{
    static $usuario = null;
    static $carregado = false;

    if ($carregado) {
        return $usuario;
    }
    $carregado = true;

    if (empty($_SESSION['user_id'])) {
        return $usuario;
    }

    try {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $linha = $stmt->fetch();
        if (!$linha || (int)$linha['status'] !== 1) {
            session_destroy();
            return $usuario = null;
        }
        $usuario = $linha;
    } catch (Throwable $e) {
        erro_banco($e, 'usuario_atual');
    }

    return $usuario;
}

function exigir_login(): void
{
    if (!usuario_atual()) {
        if (is_ajax()) {
            json_resposta(false, 'Sessão expirada. Faça login novamente.', null, 401);
        }
        flash('warning', 'Faça login para acessar o sistema.');
        redirecionar('login/index.php');
    }
}

function efetuar_login(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $usuarioId;
    unset($_SESSION['tentativas']);
    $stmt = db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW(), tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = ?');
    $stmt->execute([$usuarioId]);
    registrar_log('login', 'Login efetuado', $usuarioId);
}

function efetuar_logout(): void
{
    $usuario = usuario_atual();
    if ($usuario) {
        registrar_log('login', 'Logout', (int)$usuario['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* =========================================================
 * PERMISSÕES
 * ========================================================= */
function permissao_carregadas(): array
{
    static $permitidas = null;

    if ($permitidas !== null) {
        return $permitidas;
    }

    $usuario = usuario_atual();
    $permitidas = [];

    if (!$usuario) {
        return $permitidas;
    }

    try {
        $chaves = [];

        if ((int)$usuario['is_admin'] === 1) {
            $stmt = db()->query('SELECT chave FROM permissoes');
            foreach ($stmt->fetchAll() as $l) {
                $chaves[] = $l['chave'];
            }
            return $permitidas = array_flip($chaves);
        }

        // Permissões do perfil
        if (!empty($usuario['perfil_id'])) {
            $stmt = db()->prepare(
                'SELECT p.chave FROM permissoes p
                   JOIN perfil_permissoes pp ON pp.permissao_id = p.id
                  WHERE pp.perfil_id = ? AND pp.permitido = 1'
            );
            $stmt->execute([(int)$usuario['perfil_id']]);
            foreach ($stmt->fetchAll() as $l) {
                $chaves[] = $l['chave'];
            }
        }

        // Overrides individuais
        $stmt = db()->prepare(
            'SELECT p.chave, up.permitido FROM permissoes p
               JOIN usuario_permissoes up ON up.permissao_id = p.id
              WHERE up.usuario_id = ? AND up.permitido IS NOT NULL'
        );
        $stmt->execute([(int)$usuario['id']]);
        foreach ($stmt->fetchAll() as $l) {
            if ((int)$l['permitido'] === 1) {
                $chaves[] = $l['chave'];
            } else {
                $chaves = array_diff($chaves, [$l['chave']]);
            }
        }

        $permitidas = array_flip(array_unique($chaves));
    } catch (Throwable $e) {
        erro_banco($e, 'permissao_carregadas');
    }

    return $permitidas;
}

function tem_permissao(string $chave): bool
{
    $usuario = usuario_atual();
    if (!$usuario) {
        return false;
    }
    if ((int)$usuario['is_admin'] === 1) {
        return true;
    }
    return array_key_exists($chave, permissao_carregadas());
}

function exigir_permissao(string $chave): void
{
    if (tem_permissao($chave)) {
        return;
    }

    if (is_ajax()) {
        json_resposta(false, 'Você não tem permissão para esta ação.', null, 403);
    }

    http_response_code(403);
    flash('danger', 'Você não tem permissão para acessar esta página.');

    // Quem não tem 'dashboard_ver' é levado à própria tela inicial em vez do
    // dashboard, e nunca à página que chamou exigir_permissao(): um Location
    // apontando para si mesmo gera um loop de refresh.
    $destino = tem_permissao('dashboard_ver') ? 'dashboard/index.php' : pagina_inicial();
    if ($destino === '') {
        redirecionar('login/logout.php');
    }

    $atual = (string)parse_url((string)($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH);
    if ($atual === url($destino)) {
        exit;
    }

    redirecionar($destino);
}