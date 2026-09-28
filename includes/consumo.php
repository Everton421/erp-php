<?php
declare(strict_types=1);

/**
 * Regras de domínio do módulo de Consumo.
 * Carregado por config/config.php; usa db(), e() e os helpers já existentes.
 */

/* =========================================================
 * IDENTIFICAÇÃO DO MÓDULO
 * ========================================================= */

/**
 * Nome exibido do módulo, configurável em Configurações.
 * Padrão "Consumo", para que o mesmo sistema sirva a restaurantes,
 * bares, lanchonetes, salões e qualquer outro estabelecimento com comanda.
 */
function rotulo_consumo(): string
{
    $nome = function_exists('obter_config') ? (string)obter_config('consumo_nome', '') : '';
    $nome = trim($nome);

    return $nome !== '' ? $nome : 'Consumo';
}

/**
 * Nome exibido da fila de preparo, configurável em Configurações.
 * Padrão "Produção", para que o mesmo sistema sirva a restaurantes,
 * clínicas, salões de beleza e qualquer outro estabelecimento com comanda.
 * A pasta consumo/cozinha/ e as chaves cozinha_* são internas e não devem
 * aparecer em rótulos: use sempre rotulo_producao().
 */
function rotulo_producao(): string
{
    $nome = function_exists('obter_config') ? (string)obter_config('consumo_producao_nome', '') : '';
    $nome = trim($nome);

    return $nome !== '' ? $nome : 'Produção';
}

/* =========================================================
 * MESA
 * ========================================================= */

/**
 * Libera a mesa somente se não houver comanda aberta vinculada a ela.
 * Usado ao fechar ou cancelar uma comanda.
 */
function mesa_liberar(int $mesaId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare(
        "UPDATE mesas SET status = 'LIVRE'
          WHERE id = ? AND status = 'OCUPADA'
            AND NOT EXISTS (SELECT 1 FROM comandas WHERE mesa_id = ? AND status = 'ABERTA')"
    );
    $stmt->execute([$mesaId, $mesaId]);
}

function mesa_buscar(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM mesas WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function mesa_rotulo(array $mesa): string
{
    $rotulo = 'Mesa ' . (int)$mesa['numero'];
    if (!empty($mesa['nome'])) {
        $rotulo .= ' · ' . $mesa['nome'];
    }
    return $rotulo . ' (' . (int)$mesa['capacidade'] . ' lugares)';
}

/* =========================================================
 * COMANDA
 * ========================================================= */

/**
 * Documento usado no financeiro (contas_receber) e na auditoria.
 * Mantido em ASCII porque a tabela contas_receber é latin1.
 */
function comanda_documento(array $comanda): string
{
    return 'COMANDA-' . $comanda['numero'];
}

function comanda_referencia(array $comanda): string
{
    return 'COMANDA#' . (int)$comanda['id'];
}

/**
 * Comanda completa (mesa + atendente), para telas e ações.
 */
function comanda_buscar(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT c.*, m.numero AS mesa_numero, m.nome AS mesa_nome, m.capacidade AS mesa_capacidade,
                m.status AS mesa_status, u.nome AS garcom_nome, u.usuario AS garcom_usuario,
                fp.nome AS forma_nome
           FROM comandas c
           JOIN mesas m ON m.id = c.mesa_id
           JOIN usuarios u ON u.id = c.garcom_id
           LEFT JOIN formas_pagamento fp ON fp.id = c.forma_pagamento_id
          WHERE c.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * O usuário pode ver/agir nesta comanda?
 * Quem tem comandas_ver_todas enxerga todas; os demais, apenas as próprias.
 */
function comanda_pode_manipular(array $comanda): bool
{
    if (tem_permissao('comandas_ver_todas')) {
        return true;
    }
    return (int)$comanda['garcom_id'] === (int)(usuario_atual()['id'] ?? 0);
}

/**
 * Recalcula subtotal e total da comanda a partir dos itens não cancelados.
 * Chamada dentro da mesma transação de toda alteração de itens,
 * desconto/acréscimo, fechamento ou cancelamento.
 *
 * @return array Comanda com os valores já gravados.
 */
function comanda_recalcular(int $comandaId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? db();

    $stmt = $pdo->prepare(
        "UPDATE comandas c
            SET c.subtotal = (SELECT COALESCE(SUM(i.total), 0) FROM comanda_itens i
                               WHERE i.comanda_id = c.id AND i.status <> 'CANCELADO'),
                c.total    = GREATEST(0, ROUND(
                                  (SELECT COALESCE(SUM(i.total), 0) FROM comanda_itens i
                                    WHERE i.comanda_id = c.id AND i.status <> 'CANCELADO')
                                  - c.desconto + c.acrescimo, 2)),
                c.atualizado_em = NOW()
          WHERE c.id = ?"
    );
    $stmt->execute([$comandaId]);

    $stmt = $pdo->prepare('SELECT * FROM comandas WHERE id = ? LIMIT 1');
    $stmt->execute([$comandaId]);
    $comanda = $stmt->fetch();

    if (!$comanda) {
        throw new RuntimeException('Comanda #' . $comandaId . ' não encontrada.');
    }

    return $comanda;
}

/**
 * Itens da comanda, mais antigos primeiro (ordem de chegada na cozinha).
 */
function comanda_itens(int $comandaId, bool $somenteAtivos = false): array
{
    $sql = 'SELECT i.*, p.descricao AS item_cardapio
              FROM comanda_itens i
              LEFT JOIN cardapio_itens p ON p.id = i.cardapio_item_id
             WHERE i.comanda_id = ?';
    if ($somenteAtivos) {
        $sql .= " AND i.status <> 'CANCELADO'";
    }
    $sql .= ' ORDER BY i.data_pedido, i.id';

    $stmt = db()->prepare($sql);
    $stmt->execute([$comandaId]);
    return $stmt->fetchAll();
}

/**
 * Itens que ainda não chegaram à mesa, por comanda.
 * Usado para bloquear o fechamento e no aviso do atendente.
 */
function comanda_itens_pendentes(int $comandaId): array
{
    $stmt = db()->prepare(
        "SELECT id, descricao, quantidade
           FROM comanda_itens
          WHERE comanda_id = ? AND status IN ('PENDENTE', 'PREPARANDO')
          ORDER BY data_pedido"
    );
    $stmt->execute([$comandaId]);
    return $stmt->fetchAll();
}

/* =========================================================
 * STATUS DO ITEM (máquina de estados)
 * ========================================================= */

/**
 * Transições permitidas do status do item. O status atual é a chave;
 * a lista de valores é a única saída admitida — a Update também
 * recebe o status atual no WHERE, evitando saltos inválidos.
 */
function item_transicoes(): array
{
    return [
        'PENDENTE'   => ['PREPARANDO', 'CANCELADO'],
        'PREPARANDO' => ['PRONTO', 'PENDENTE', 'CANCELADO'],
        'PRONTO'     => ['ENTREGUE', 'CANCELADO'],
        'ENTREGUE'   => [],
        'CANCELADO'  => [],
    ];
}

function item_transicao_valida(string $de, string $para): bool
{
    return in_array($para, item_transicoes()[$de] ?? [], true);
}

/* =========================================================
 * CARDÁPIO
 * ========================================================= */

function cardapio_categorias(bool $somenteAtivas = true): array
{
    $sql = 'SELECT * FROM cardapio_categorias';
    if ($somenteAtivas) {
        $sql .= ' WHERE ativo = 1';
    }
    $sql .= ' ORDER BY ordem, nome';
    return db()->query($sql)->fetchAll();
}

function cardapio_itens(array $filtros = []): array
{
    $where = [];
    $params = [];

    if (($filtros['categoria_id'] ?? '') !== '') {
        $where[] = 'i.categoria_id = ?';
        $params[] = (int)$filtros['categoria_id'];
    }
    if (($filtros['busca'] ?? '') !== '') {
        $where[] = '(i.descricao LIKE ? OR i.codigo LIKE ?)';
        $termo = '%' . $filtros['busca'] . '%';
        $params[] = $termo;
        $params[] = $termo;
    }
    if (array_key_exists('ativo', $filtros)) {
        $where[] = 'i.ativo = ?';
        $params[] = (int)$filtros['ativo'];
    }

    $sql = 'SELECT i.*, c.nome AS categoria_nome, c.cor AS categoria_cor
              FROM cardapio_itens i
              LEFT JOIN cardapio_categorias c ON c.id = i.categoria_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.ordem, c.nome, i.descricao';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Resolve o preço de um item do cardápio. Retorna null se não existir,
 * para a camada de chamada decidir entre item livre e erro.
 */
function cardapio_item(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM cardapio_itens WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* =========================================================
 * FORMATAÇÃO
 * ========================================================= */

/**
 * "12 min" · "1h05" · "1d2h" a partir de um datetime.
 */
function formatar_tempo_decorrido(?string $data): string
{
    if (!$data) {
        return '—';
    }
    $ts = strtotime($data);
    if (!$ts) {
        return '—';
    }
    $min = max(0, (int)floor((time() - $ts) / 60));
    if ($min < 60) {
        return $min . ' min';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h < 24) {
        return $h . 'h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
    }
    $d = intdiv($h, 24);
    return $d . 'd' . ($h % 24) . 'h';
}
