<?php
require_once __DIR__ . '/../config/config.php';

exigir_login();

if (!tem_permissao('produtos_ver') && !tem_permissao('vendas_criar') && !tem_permissao('compras_criar')
    && !tem_permissao('estoque_entrada') && !tem_permissao('estoque_saida') && !tem_permissao('estoque_ajuste')) {
    json_resposta(false, 'Sem permissão para consultar produtos.', null, 403);
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    json_resposta(true, '', []);
}

$termo = '%' . $q . '%';
$stmt = db()->prepare(
    'SELECT p.id, p.codigo, p.codigo_barras, p.descricao, p.descricao_complementar,
            p.preco_custo, p.preco_venda, p.preco_promocional, p.estoque_atual, p.estoque_minimo, p.foto,
            u.sigla AS unidade
       FROM produtos p
       LEFT JOIN unidades u ON u.id = p.unidade_id
       LEFT JOIN produto_codigos pc ON pc.produto_id = p.id
      WHERE p.status = 1
        AND (p.descricao LIKE ? OR p.descricao_complementar LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ? OR pc.codigo LIKE ?)
      GROUP BY p.id
      ORDER BY p.descricao
      LIMIT 20'
);
$stmt->execute([$termo, $termo, $termo, $termo, $termo]);

$resultado = [];
foreach ($stmt->fetchAll() as $p) {
    $resultado[] = [
        'id' => (int)$p['id'],
        'codigo' => $p['codigo'],
        'codigo_barras' => $p['codigo_barras'],
        'descricao' => $p['descricao'],
        'preco_custo' => (float)$p['preco_custo'],
        'preco_venda' => (float)$p['preco_venda'],
        'preco_promocional' => $p['preco_promocional'] !== null ? (float)$p['preco_promocional'] : null,
        'estoque_atual' => (float)$p['estoque_atual'],
        'estoque_minimo' => (float)$p['estoque_minimo'],
        'unidade' => $p['unidade'],
        'foto' => $p['foto'],
        'rotulo' => $p['descricao'],
        'sub' => (($p['codigo_barras'] ? 'Barras: ' . $p['codigo_barras'] : 'Código: ' . ($p['codigo'] ?: '-'))),
        'extra' => number_format((float)$p['preco_venda'], 2, ',', '.'),
    ];
}

json_resposta(true, '', $resultado);