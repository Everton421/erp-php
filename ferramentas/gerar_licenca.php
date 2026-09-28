<?php
/**
 * Gera a chave de licença de uma instalação.
 *
 * Uso (linha de comando):
 *   php ferramentas/gerar_licenca.php                 -> usa a instalação local (banco)
 *   php ferramentas/gerar_licenca.php <codigo_instala>-> usa um código informado
 *
 * Exemplo de saída: GC1-ABCD-EF12-3456-7890-WXYZ
 */
require_once __DIR__ . '/../config/config.php';

$instalacao = trim((string)($_SERVER['argv'][1] ?? ''));

if ($instalacao === '') {
    $instalacao = licenca_instalacao();
}

echo 'Instalação: ' . $instalacao . PHP_EOL;
echo 'Licença:    ' . licenca_esperada($instalacao) . PHP_EOL;