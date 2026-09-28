<?php
declare(strict_types=1);

/**
 * Conexão PDO única (singleton), compartilhada por toda a aplicação.
 * Todas as consultas devem usar prepared statements.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[BANCO] Falha de conexão: ' . $e->getMessage());
            http_response_code(500);
            if (defined('DEV_MODE') && DEV_MODE) {
                exit('Falha na conexão com o banco de dados. Verifique se o MySQL/MariaDB está ativo e se os dados em config/config.php estão corretos.');
            }
            exit('Serviço temporariamente indisponível.');
        }
    }

    return $pdo;
}

/**
 * Registrar erros de banco de forma segura (sem exibir ao usuário).
 */
function erro_banco(Throwable $e, string $contexto): void
{
    error_log('[BANCO][' . $contexto . '] ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
}