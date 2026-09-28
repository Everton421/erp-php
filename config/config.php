<?php
declare(strict_types=1);

/**
 * Configurações globais do sistema.
 
 */

define('DEV_MODE', true);
define('APP_NAME', 'Gestor Comercial');

define('BASE_PATH', dirname(__DIR__));

define('DB_HOST', '192.168.100.106');
define('DB_NAME', 'edbbuzjw_alessandro');
define('DB_USER', 'root');
define('DB_PASS', 'Nileduz');
define('DB_CHARSET', 'utf8mb4');

define('INC', BASE_PATH . '/includes/');
define('ASSETS', 'assets');

/* ---------------- Sessão segura ---------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_name('VENDASAPP');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------------- Erros ---------------- */
error_reporting(E_ALL);
ini_set('display_errors', DEV_MODE ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/erros.log');
date_default_timezone_set('America/Sao_Paulo');

/* ---------------- BASE_URL ---------------- */
define('BASE_URL', '/vendasapp');

/* ---------------- Núcleo ---------------- */
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/permissoes.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/consumo.php';
require_once BASE_PATH . '/config/auth.php';

/* ---------------- Fuso horário do banco ---------------- */
try {
    db()->exec("SET time_zone = '-03:00'");
} catch (Throwable $e) {
}

/* ---------------- Licença de uso ---------------- */
require_once BASE_PATH . '/config/licenca.php';

try {
    licenca_bootstrap();
    licenca_gate();
} catch (Throwable $e) {
    erro_banco($e, 'licenca');
}