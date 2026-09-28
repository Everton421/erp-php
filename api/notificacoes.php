<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

exigir_login();

$pdo = db();
$notificacoes = [];
$totalAlertas = 0;

// 1. Alerta de Estoque Baixo
if (tem_permissao('estoque_ver') || tem_permissao('produtos_ver')) {
    $stmt = $pdo->query(
        "SELECT id, codigo, descricao, estoque_atual, estoque_minimo
           FROM produtos
          WHERE status = 1 AND estoque_minimo > 0 AND estoque_atual <= estoque_minimo
          ORDER BY estoque_atual ASC
          LIMIT 8"
    );
    $itensEstoque = $stmt->fetchAll();
    $qtdEstoqueBaixo = (int)$pdo->query(
        "SELECT COUNT(*) FROM produtos WHERE status = 1 AND estoque_minimo > 0 AND estoque_atual <= estoque_minimo"
    )->fetchColumn();

    if ($qtdEstoqueBaixo > 0) {
        $totalAlertas += $qtdEstoqueBaixo;
        $notificacoes[] = [
            'tipo' => 'estoque',
            'icone' => 'bi-exclamation-triangle-fill',
            'classe' => 'text-warning',
            'titulo' => $qtdEstoqueBaixo . ' produto(s) com estoque baixo',
            'descricao' => 'Produtos que atingiram ou estão abaixo do estoque mínimo.',
            'url' => url('estoque/reposicao.php'),
            'itens' => array_map(static fn($p) => [
                'id' => (int)$p['id'],
                'texto' => $p['descricao'] . ' (Saldo: ' . formatar_qtde($p['estoque_atual']) . ' / Mín: ' . formatar_qtde($p['estoque_minimo']) . ')',
                'url' => url('produtos/form.php?id=' . (int)$p['id']),
            ], $itensEstoque),
        ];
    }
}

// 2. Alerta de Contas a Receber Vencidas
if (tem_permissao('contas_receber_ver')) {
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total_qtd, COALESCE(SUM(valor - valor_pago), 0) AS total_val
           FROM contas_receber
          WHERE status IN ('PENDENTE', 'PARCIAL') AND vencimento < CURDATE()"
    );
    $crVencido = $stmt->fetch();
    $qtdCr = (int)($crVencido['total_qtd'] ?? 0);
    $valCr = (float)($crVencido['total_val'] ?? 0);

    if ($qtdCr > 0) {
        $totalAlertas += $qtdCr;
        $notificacoes[] = [
            'tipo' => 'receber',
            'icone' => 'bi-cash-coin',
            'classe' => 'text-danger',
            'titulo' => $qtdCr . ' conta(s) a receber vencida(s)',
            'descricao' => 'Total em atraso: ' . formatar_moeda($valCr),
            'url' => url('contas_receber/index.php?status=VENCIDO'),
        ];
    }
}

// 3. Alerta de Contas a Pagar Vencendo Hoje ou Atrasadas
if (tem_permissao('contas_pagar_ver')) {
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total_qtd, COALESCE(SUM(valor - valor_pago), 0) AS total_val
           FROM contas_pagar
          WHERE status IN ('PENDENTE', 'PARCIAL') AND vencimento <= CURDATE()"
    );
    $cpPendente = $stmt->fetch();
    $qtdCp = (int)($cpPendente['total_qtd'] ?? 0);
    $valCp = (float)($cpPendente['total_val'] ?? 0);

    if ($qtdCp > 0) {
        $totalAlertas += $qtdCp;
        $notificacoes[] = [
            'tipo' => 'pagar',
            'icone' => 'bi-receipt',
            'classe' => 'text-primary',
            'titulo' => $qtdCp . ' conta(s) a pagar hoje ou atrasadas',
            'descricao' => 'Total a liquidar: ' . formatar_moeda($valCp),
            'url' => url('contas_pagar/index.php?status=PENDENTE'),
        ];
    }
}

// 4. Consumo: comandas fechadas aguardando pagamento
if (tem_permissao('caixa_consumo_ver')) {
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total_qtd, COALESCE(SUM(total - valor_pago), 0) AS total_val
           FROM comandas
          WHERE status = 'FECHADA' AND status_pagamento <> 'PAGO'"
    );
    $pendentes = $stmt->fetch();
    $qtdComandas = (int)($pendentes['total_qtd'] ?? 0);
    $valComandas = (float)($pendentes['total_val'] ?? 0);

    if ($qtdComandas > 0) {
        $totalAlertas += $qtdComandas;
        $notificacoes[] = [
            'tipo' => 'comanda',
            'icone' => 'bi-receipt-cutoff',
            'classe' => 'text-danger',
            'titulo' => $qtdComandas . ' comanda(s) aguardando pagamento',
            'descricao' => 'Total a receber: ' . formatar_moeda($valComandas),
            'url' => url('consumo/caixa/index.php?status=ABERTO'),
        ];
    }
}

// 5. Consumo: itens na fila da produção
if (tem_permissao('cozinha_ver')) {
    $fila = (int)$pdo->query(
        "SELECT COUNT(*)
           FROM comanda_itens i
           JOIN comandas c ON c.id = i.comanda_id
          WHERE i.status IN ('PENDENTE', 'PREPARANDO') AND c.status = 'ABERTA'"
    )->fetchColumn();

    if ($fila > 0) {
        $totalAlertas += $fila;
        $notificacoes[] = [
            'tipo' => 'cozinha',
            'icone' => 'bi-fire',
            'classe' => 'text-warning',
            'titulo' => $fila . ' pedido(s) na fila de ' . lcfirst(rotulo_producao()),
            'descricao' => 'Itens aguardando preparo ou finalização.',
            'url' => url('consumo/cozinha/index.php'),
        ];
    }
}

json_resposta(true, '', [
    'total' => $totalAlertas,
    'notificacoes' => $notificacoes,
]);
