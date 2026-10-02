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
 * A pasta consumo/producao/ e as chaves cozinha_* são internas e não devem
 * aparecer em rótulos: use sempre rotulo_producao().
 */
function rotulo_producao(): string
{
    $nome = function_exists('obter_config') ? (string)obter_config('consumo_producao_nome', '') : '';
    $nome = trim($nome);

    return $nome !== '' ? $nome : 'Produção';
}

/**
 * A fila de preparo está ativa? Configurável em Configurações.
 * Quando desativada, o módulo serve a estabelecimentos que só registram
 * a comanda e cobram no fim: os itens já nascem entregues, a fila fica
 * oculta e o fechamento não exige preparo.
 */
function consumo_producao_ativa(): bool
{
    if (!function_exists('obter_config')) {
        return true;
    }

    return (string)obter_config('consumo_producao_ativa', '1') === '1';
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
 * Itens da comanda, mais antigos primeiro (ordem de chegada na fila de preparo).
 */
function comanda_itens(int $comandaId, bool $somenteAtivos = false): array
{
    $sql = 'SELECT i.*, p.descricao AS item_produto
              FROM comanda_itens i
              LEFT JOIN produtos p ON p.id = i.produto_id
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
 *
 * 'PENDENTE' admite 'PRONTO' para que o atendente conclua a entrega
 * sem passar pela produção (pedido já servido, item pronto na hora,
 * ou módulo sem fila de preparo).
 */
function item_transicoes(): array
{
    return [
        'PENDENTE'   => ['PREPARANDO', 'PRONTO', 'CANCELADO'],
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

/**
 * Item ainda pode ser editado (quantidade, observação, remoção).
 * Substitui o teste solto por 'status === PENDENTE': com a produção
 * desativada o item nasce ENTREGUE e precisa continuar editável,
 * senão a comanda perde o ajuste de quantidade.
 */
function item_editavel(array $item): bool
{
    $status = (string)($item['status'] ?? '');

    if ($status === 'PENDENTE') {
        return true;
    }

    return !consumo_producao_ativa() && $status === 'ENTREGUE';
}

/* =========================================================
 * CATÁLOGO DO CONSUMO (produtos do sistema)
 *
 * O módulo de Consumo vende os produtos cadastrados em `produtos`,
 * usando `categorias` para agrupar e `preco_promocional`/`preco_venda`
 * para o preço — o mesmo critério de `vendas/nova.php`.
 * As tabelas `cardapio_*` são legadas e ficam só para compatibilidade.
 * ========================================================= */

function consumo_cores_categoria(): array
{
    return ['#f7a541', '#4f6ef7', '#00c9a7', '#ff6b81', '#a855f7', '#0ea5e9', '#e8590c', '#64748b'];
}

/**
 * Grupo sintético que reúne os produtos sem categoria utilizável
 * (categoria_id nulo, zero ou apontando para registro inexistente).
 * Sem ele, esses produtos não casam com nenhum bloco e somem da lista.
 */
const CONSUMO_SEM_CATEGORIA_ID = 0;

function consumo_grupo_sem_categoria(): array
{
    return [
        'id'            => CONSUMO_SEM_CATEGORIA_ID,
        'nome'          => 'Sem categoria',
        'cor'           => '#64748b',
        'sintetica'     => true,
        'categoria_id'  => CONSUMO_SEM_CATEGORIA_ID,
    ];
}

/**
 * Categorias para o seletor, já com o grupo "Sem categoria" anexado
 * quando existe ao menos um produto sem categoria utilizável.
 */
function consumo_categorias_seletor(): array
{
    $categorias = consumo_categorias();

    $sem = db()->query(
        "SELECT COUNT(*) FROM produtos p
           LEFT JOIN categorias c ON c.id = p.categoria_id
          WHERE p.status = 1 AND (p.categoria_id IS NULL OR p.categoria_id = 0 OR c.id IS NULL)"
    )->fetchColumn();

    if ((int)$sem > 0) {
        $categorias[] = consumo_grupo_sem_categoria();
    }

    return $categorias;
}

/**
 * Categorias do catálogo com uma cor estável para os rótulos do seletor
 * (a tabela `categorias` não tem cor própria).
 *
 * Não restringe por existência de produtos: uma categoria vazia apenas não
 * renderiza bloco, e o grupo "Sem categoria" é sintetizado à parte.
 */
function consumo_categorias(): array
{
    $lista = db()->query('SELECT c.id, c.nome FROM categorias c ORDER BY c.nome')->fetchAll();

    $cores = consumo_cores_categoria();
    foreach ($lista as $i => $cat) {
        $lista[$i]['cor'] = $cores[$i % count($cores)];
    }
    return $lista;
}

/**
 * Escapa os curingas do LIKE para que o termo seja comparado literalmente.
 * Sem isso, quem digitar '%' no seletor de produtos traria o catálogo inteiro
 * e '_' casaria qualquer caractere.
 */
function consumo_termo_busca(string $termo): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($termo)) . '%';
}

/**
 * Produtos ativos disponíveis para a comanda, com `preco` já resolvido.
 * `produtos` é legada em latin1: nas comparações de texto o literal utf8mb4
 * precisa ser convertido dos dois lados.
 */
function consumo_produtos(array $filtros = []): array
{
    $where = ['p.status = 1'];
    $params = [];

    if (($filtros['categoria_id'] ?? '') !== '') {
        $categoriaId = (int)$filtros['categoria_id'];

        if ($categoriaId === CONSUMO_SEM_CATEGORIA_ID) {
            // Grupo sintético: categoria nula, zero ou órfã.
            $where[] = '(p.categoria_id IS NULL OR p.categoria_id = 0 OR c.id IS NULL)';
        } else {
            $where[] = 'p.categoria_id = ?';
            $params[] = $categoriaId;
        }
    }
    if (trim((string)($filtros['busca'] ?? '')) !== '') {
        // O ESCAPE precisa vir em cada LIKE: o MariaDB recusa a cláusula
        // depois de um grupo (A OR B) entre parênteses.
        $like = " LIKE ? ESCAPE '\\\\'";
        $where[] = '(CONVERT(p.descricao USING utf8mb4)' . $like
            . ' OR CONVERT(p.codigo USING utf8mb4)' . $like
            . ' OR CONVERT(p.descricao_complementar USING utf8mb4)' . $like . ')';
        $termo = consumo_termo_busca((string)$filtros['busca']);
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
    }

    $sql = "SELECT p.id, p.codigo, p.descricao, p.descricao_complementar, p.categoria_id, p.foto,
                   p.preco_venda, p.preco_promocional,
                   IF(p.preco_promocional IS NOT NULL AND p.preco_promocional > 0,
                      p.preco_promocional, p.preco_venda) AS preco,
                   c.nome AS categoria_nome
              FROM produtos p
              LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY c.nome, p.descricao';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Produto usado para validar e precificar um item da comanda.
 * Retorna null quando não existe, para a camada de chamada decidir
 * entre item livre e erro.
 */
function consumo_produto(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, codigo, descricao, descricao_complementar, categoria_id, status,
                preco_venda, preco_promocional,
                IF(preco_promocional IS NOT NULL AND preco_promocional > 0,
                   preco_promocional, preco_venda) AS preco
           FROM produtos
          WHERE id = ?
          LIMIT 1'
    );
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