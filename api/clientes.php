<?php
require_once __DIR__ . '/../config/config.php';

exigir_login();

if (!tem_permissao('clientes_ver') && !tem_permissao('clientes_criar') && !tem_permissao('vendas_criar')) {
    json_resposta(false, 'Sem permissão para consultar clientes.', null, 403);
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    json_resposta(true, '', []);
}

$termo = '%' . $q . '%';
$stmt = db()->prepare(
    'SELECT id, nome, nome_fantasia, documento, cidade, estado, telefone, celular, email
       FROM clientes
      WHERE status = 1
        AND (nome LIKE ? OR nome_fantasia LIKE ? OR documento LIKE ? OR cidade LIKE ? OR telefone LIKE ? OR celular LIKE ?)
      ORDER BY nome
      LIMIT 20'
);
$stmt->execute([$termo, $termo, $termo, $termo, $termo, $termo]);

$resultado = [];
foreach ($stmt->fetchAll() as $c) {
    $doc = $c['documento'] ? ' • ' . $c['documento'] : '';
    $resultado[] = [
        'id' => (int)$c['id'],
        'nome' => $c['nome'],
        'documento' => $c['documento'],
        'cidade' => $c['cidade'],
        'uf' => $c['estado'],
        'rotulo' => $c['nome'],
        'sub' => ($c['cidade'] ? $c['cidade'] . ($c['estado'] ? '/' . $c['estado'] : '') : '') . $doc,
        'extra' => $c['celular'] ?: ($c['telefone'] ?: ''),
    ];
}

json_resposta(true, '', $resultado);