<?php
declare(strict_types=1);

/**
 * Compatibilidade: a fila de preparo foi movida de `consumo/cozinha/` para
 * `consumo/producao/`. Este shim existe apenas para URLs antigas e pode ser
 * removido quando nenhum link salvo apontar mais para a pasta antiga.
 */

$caminho = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$destino = (string)preg_replace('#/consumo/cozinha(/|$)#', '/consumo/producao$1', $caminho, 1);

$query = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
if ($query !== '') {
    $destino .= '?' . $query;
}

header('Location: ' . $destino, true, 301);
exit;