<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
exigir_login();

$user = usuario_atual();
if ((int)($user['is_admin'] ?? 0) !== 1 && !tem_permissao('config_ver')) {
    flash('danger', 'Apenas administradores podem gerar cópias de segurança do banco.');
    redirecionar('configuracao/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('configuracao/index.php');
}
exigir_csrf();

$pdo = db();
$dbName = DB_NAME;
$dataHora = date('Y-m-d_H-i-s');
$nomeArquivo = 'backup_' . $dbName . '_' . $dataHora . '.sql';

// Montar o dump SQL
$output = "-- =====================================================\n";
$output .= "-- GESTOR COMERCIAL - BACKUP DO BANCO DE DADOS\n";
$output .= "-- Data/Hora: " . date('d/m/Y H:i:s') . "\n";
$output .= "-- Banco: " . $dbName . "\n";
$output .= "-- Usuário: " . ($user['usuario'] ?? 'admin') . "\n";
$output .= "-- =====================================================\n\n";
$output .= "SET NAMES utf8mb4;\n";
$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

try {
    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tabelas as $tab) {
        // Obter DDL da tabela
        $createRow = $pdo->query('SHOW CREATE TABLE `' . $tab . '`')->fetch();
        $createSql = $createRow['Create Table'] ?? '';

        $output .= "-- Estrutura da tabela `{$tab}`\n";
        $output .= "DROP TABLE IF EXISTS `{$tab}`;\n";
        $output .= $createSql . ";\n\n";

        // Obter dados da tabela
        $stmt = $pdo->query('SELECT * FROM `' . $tab . '`');
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($linhas) > 0) {
            $output .= "-- Dados da tabela `{$tab}`\n";
            foreach ($linhas as $linha) {
                $colunas = array_keys($linha);
                $valores = array_map(function ($val) use ($pdo) {
                    if ($val === null) {
                        return 'NULL';
                    }
                    return $pdo->quote((string)$val);
                }, array_values($linha));

                $output .= "INSERT INTO `{$tab}` (`" . implode('`, `', $colunas) . "`) VALUES (" . implode(', ', $valores) . ");\n";
            }
            $output .= "\n";
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "-- FIM DO BACKUP\n";

    registrar_log('config', 'Cópia de segurança (backup SQL) gerada com sucesso');

    // Forçar download
    header('Content-Description: File Transfer');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Content-Length: ' . strlen($output));
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $output;
    exit;

} catch (Throwable $e) {
    erro_banco($e, 'gerar_backup');
    flash('danger', 'Ocorreu um erro ao gerar o backup do banco de dados.');
    redirecionar('configuracao/index.php');
}
