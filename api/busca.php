<?php
require_once __DIR__ . '/../config/config.php';

exigir_login();

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    json_resposta(true, '', []);
}

$resultados = [];

if (tem_permissao('produtos_ver') || tem_permissao('vendas_criar')) {
    $param = '%' . $q . '%';
    $stmt = db()->prepare(
        'SELECT id, codigo, codigo_barras, descricao, preco_venda
           FROM produtos 
          WHERE status = 1 AND (descricao LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ?)
          LIMIT 6'
    );
    $stmt->execute([$param, $param, $param]);
    foreach ($stmt->fetchAll() as $p) {
        $resultados[] = [
            'tipo' => 'produto',
            'label' => $p['descricao'],
            'sub' => 'Cód: ' . ($p['codigo'] ?: ($p['codigo_barras'] ?: '-')) . ' • Preço: ' . formatar_moeda($p['preco_venda']),
            'url' => '/produtos/form.php?id=' . (int)$p['id'],
        ];
    }
}

if (tem_permissao('clientes_ver') || tem_permissao('vendas_criar')) {
    $param = '%' . $q . '%';
    $stmt = db()->prepare(
        'SELECT id, nome, nome_fantasia, documento, cidade 
           FROM clientes 
          WHERE status = 1 AND (nome LIKE ? OR nome_fantasia LIKE ? OR documento LIKE ?)
          LIMIT 5'
    );
    $stmt->execute([$param, $param, $param]);
    foreach ($stmt->fetchAll() as $c) {
        $sub = ($c['documento'] ?: '-');
        if ($c['nome_fantasia']) {
            $sub = $c['nome_fantasia'] . ' • ' . $sub;
        }
        if ($c['cidade']) {
            $sub .= ' • ' . $c['cidade'];
        }
        $urlDestino = tem_permissao('clientes_ver') ? '/clientes/ver.php?id=' . (int)$c['id'] : '/clientes/form.php?id=' . (int)$c['id'];
        $resultados[] = [
            'tipo' => 'cliente',
            'label' => $c['nome'],
            'sub' => $sub,
            'url' => $urlDestino,
        ];
    }
}

if (tem_permissao('fornecedores_ver') || tem_permissao('compras_criar')) {
    $param = '%' . $q . '%';
    $stmt = db()->prepare(
        'SELECT id, razao_social, nome_fantasia, documento, cidade 
           FROM fornecedores 
          WHERE status = 1 AND (razao_social LIKE ? OR nome_fantasia LIKE ? OR documento LIKE ?)
          LIMIT 5'
    );
    $stmt->execute([$param, $param, $param]);
    foreach ($stmt->fetchAll() as $f) {
        $sub = ($f['documento'] ?: '-');
        if ($f['nome_fantasia']) {
            $sub = $f['nome_fantasia'] . ' • ' . $sub;
        }
        if ($f['cidade']) {
            $sub .= ' • ' . $f['cidade'];
        }
        $urlDestino = tem_permissao('fornecedores_ver') ? '/fornecedores/ver.php?id=' . (int)$f['id'] : '/fornecedores/form.php?id=' . (int)$f['id'];
        $resultados[] = [
            'tipo' => 'fornecedor',
            'label' => $f['razao_social'],
            'sub' => $sub,
            'url' => $urlDestino,
        ];
    }
}

if (tem_permissao('comandas_ver')) {
    $param = '%' . $q . '%';
    $somenteMinhas = !tem_permissao('comandas_ver_todas');
    $stmt = db()->prepare(
        'SELECT c.id, c.numero, c.status, c.total, c.data_abertura,
                m.numero AS mesa_numero, u.nome AS garcom_nome
           FROM comandas c
           JOIN mesas m ON m.id = c.mesa_id
           JOIN usuarios u ON u.id = c.garcom_id
          WHERE (c.numero LIKE ? OR m.numero = ? OR u.nome LIKE ?)
            AND (? = 0 OR c.garcom_id = ?)
          ORDER BY c.data_abertura DESC
          LIMIT 5'
    );
    $usuarioId = (int)(usuario_atual()['id'] ?? 0);
    $stmt->execute([$param, (int)$q, $param, $somenteMinhas ? 1 : 0, $usuarioId]);
    foreach ($stmt->fetchAll() as $c) {
        $resultados[] = [
            'tipo' => 'comanda',
            'label' => 'Comanda ' . $c['numero'] . ' • Mesa ' . (int)$c['mesa_numero'],
            'sub' => $c['garcom_nome'] . ' • ' . $c['status'] . ' • ' . formatar_moeda($c['total']),
            'url' => '/consumo/comandas/ver.php?id=' . (int)$c['id'],
        ];
    }
}

json_resposta(true, '', $resultados);