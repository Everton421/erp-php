<?php
declare(strict_types=1);

/**
 * Compatibilidade: o módulo de restaurante foi renomeado para `consumo/`.
 * Este shim existe apenas para URLs antigas (/restaurante/...) e pode ser
 * removido quando nenhum link salvo, favorito ou cupom impresso apontar
 * mais para a pasta antiga.
 */

$caminho = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$destino = preg_replace('#/restaurante(/|$)#', '/consumo$1', $caminho, 1);
$destino = $destino !== null && $destino !== '' ? $destino : '/consumo/';

$query = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
if ($query !== '') {
    $destino .= '?' . $query;
}

header('Location: ' . $destino, true, 301);
