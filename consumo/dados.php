<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

$acao = (string)($_GET['acao'] ?? '');
$pdo = db();

/* =========================================================
 * PRODUTOS — alimenta o seletor do atendente
 * Catálogo vem de `produtos`/`categorias`, não do estoque.
 * Aceita `q`: descrição, código ou descrição complementar.
 * ========================================================= */
if ($acao === 'produtos') {
    if (!tem_permissao('comandas_item') && !tem_permissao('cardapio_ver')) {
        json_resposta(false, 'Sem permissão para acessar os produtos.', null, 403);
    }

    $busca = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($busca) > 60) {
        $busca = mb_substr($busca, 0, 60);
    }

    $itens = $busca !== '' ? consumo_produtos(['busca' => $busca]) : consumo_produtos();

    // `consumo_categorias_seletor()` anexa o grupo "Sem categoria" quando há
    // produto órfão; sem ele o produto não casaria com nenhum bloco.
    json_resposta(true, '', [
        'categorias' => consumo_categorias_seletor(),
        'itens'      => $itens,
        'total'      => count($itens),
        'busca'      => $busca,
    ]);
}

/* =========================================================
 * PRODUÇÃO — um cartão por item, do mais antigo para o mais novo
 * ========================================================= */
if ($acao === 'producao') {
    if (!consumo_producao_ativa()) {
        json_resposta(false, rotulo_producao() . ' está desativado nas configurações.', null, 403);
    }
    if (!tem_permissao('cozinha_ver')) {
        json_resposta(false, 'Sem permissão para acessar ' . rotulo_producao() . '.', null, 403);
    }

    $stmt = $pdo->query(
        "SELECT i.id AS item_id, i.status, i.descricao, i.quantidade, i.observacoes, i.data_pedido,
                c.id AS comanda_id, c.numero AS comanda,
                m.numero AS mesa_numero, m.nome AS mesa_nome,
                u.nome AS garcom,
                GREATEST(TIMESTAMPDIFF(MINUTE, i.data_pedido, NOW()), 0) AS minutos
           FROM comanda_itens i
           JOIN comandas c ON c.id = i.comanda_id
           JOIN mesas m ON m.id = c.mesa_id
           JOIN usuarios u ON u.id = c.garcom_id
          WHERE c.status = 'ABERTA'
            AND i.status IN ('PENDENTE', 'PREPARANDO', 'PRONTO')
          ORDER BY i.data_pedido, i.id"
    );

    $grupos = ['PENDENTE' => [], 'PREPARANDO' => [], 'PRONTO' => []];
    $prontosIds = [];

    foreach ($stmt->fetchAll() as $linha) {
        $status = (string)$linha['status'];
        $mesa = 'Mesa ' . (int)$linha['mesa_numero'];
        if (!empty($linha['mesa_nome'])) {
            $mesa .= ' · ' . $linha['mesa_nome'];
        }

        $grupos[$status][] = [
            'item_id' => (int)$linha['item_id'],
            'comanda_id' => (int)$linha['comanda_id'],
            'comanda' => (string)$linha['comanda'],
            'status' => $status,
            'mesa' => $mesa,
            'garcom' => (string)$linha['garcom'],
            'minutos' => (int)$linha['minutos'],
            'itens' => [[
                'qtd' => (float)$linha['quantidade'],
                'descricao' => (string)$linha['descricao'],
                'observacao' => (string)($linha['observacoes'] ?? ''),
            ]],
        ];

        if ($status === 'PRONTO') {
            $prontosIds[] = (int)$linha['item_id'];
        }
    }

    json_resposta(true, '', [
        'PENDENTE' => $grupos['PENDENTE'],
        'PREPARANDO' => $grupos['PREPARANDO'],
        'PRONTO' => $grupos['PRONTO'],
        'ultimos_prontos' => $prontosIds,
        'total' => count($grupos['PENDENTE']) + count($grupos['PREPARANDO']) + count($grupos['PRONTO']),
    ]);
}

/* =========================================================
 * MESAS — grade atualizada pelo próprio navegador
 * ========================================================= */
if ($acao === 'mesas') {
    if (!tem_permissao('mesas_ver')) {
        json_resposta(false, 'Sem permissão para acessar as mesas.', null, 403);
    }

    $filtro = (string)($_GET['status'] ?? 'todos');
    $where = [];
    $params = [];

    if (in_array($filtro, ['LIVRE', 'OCUPADA', 'RESERVADA'], true)) {
        $where[] = 'm.status = ?';
        $params[] = $filtro;
    } elseif ($filtro === 'ativas') {
        $where[] = 'm.ativo = 1';
    } else {
        $filtro = 'todos';
    }

    $sql = "SELECT m.id, m.numero, m.nome, m.capacidade, m.status, m.ativo,
                   c.id AS comanda_id, c.numero AS comanda, c.total, c.data_abertura,
                   g.nome AS garcom
              FROM mesas m
              LEFT JOIN comandas c ON c.mesa_id = m.id AND c.status = 'ABERTA'
              LEFT JOIN usuarios g ON g.id = c.garcom_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY m.numero';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $mesas = [];
    foreach ($stmt->fetchAll() as $m) {
        $mesas[] = [
            'id' => (int)$m['id'],
            'numero' => (int)$m['numero'],
            'nome' => (string)($m['nome'] ?? ''),
            'capacidade' => (int)$m['capacidade'],
            'status' => (string)$m['status'],
            'ativo' => (int)$m['ativo'] === 1,
            'comanda_id' => $m['comanda_id'] ? (int)$m['comanda_id'] : null,
            'comanda' => (string)($m['comanda'] ?? ''),
            'total' => (float)($m['total'] ?? 0),
            'garcom' => (string)($m['garcom'] ?? ''),
            'tempo' => $m['data_abertura'] ? formatar_tempo_decorrido((string)$m['data_abertura']) : '—',
        ];
    }

    json_resposta(true, '', ['mesas' => $mesas, 'total' => count($mesas)]);
}

json_resposta(false, 'Ação inválida.', null, 400);
