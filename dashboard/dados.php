<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('dashboard_ver');

$grafico = $_GET['grafico'] ?? 'vendas';
$periodo = $_GET['periodo'] ?? 'mes';
$pdo = db();

function intervalo_periodo(string $periodo): array
{
    return match ($periodo) {
        'dia' => ['DIARIO', '7 DAY', 'd/m'],
        'semana' => ['SEMANA', '4 WEEK', 'd/m/Y'],
        'mes' => ['MENSAL', '12 MONTH', 'm/Y'],
        'ano' => ['ANUAL', '5 YEAR', 'Y'],
        default => ['MENSAL', '12 MONTH', 'm/Y'],
    };
}

if ($grafico === 'vendas') {
    [$tipo, $offset, $formato] = intervalo_periodo($periodo);

    $labels = [];
    $valores = [];
    $quantidades = [];

    switch ($tipo) {
        case 'DIARIO':
            $sql = "SELECT DATE(data_venda) AS p, SUM(total) AS v, COUNT(*) AS q
                      FROM vendas WHERE status = 'FINALIZADA' AND data_venda >= DATE_SUB(CURDATE(), INTERVAL " . $offset . ")
                      GROUP BY DATE(data_venda) ORDER BY p";
            foreach ($pdo->query($sql) as $r) {
                $labels[] = date('d/m', strtotime($r['p']));
                $valores[] = (float)$r['v'];
                $quantidades[] = (int)$r['q'];
            }
            break;
        case 'SEMANA':
            $sql = "SELECT YEARWEEK(data_venda) AS p, MIN(data_venda) AS ini, SUM(total) AS v, COUNT(*) AS q
                      FROM vendas WHERE status = 'FINALIZADA' AND data_venda >= DATE_SUB(CURDATE(), INTERVAL " . $offset . ")
                      GROUP BY YEARWEEK(data_venda) ORDER BY p";
            foreach ($pdo->query($sql) as $r) {
                $labels[] = date('d/m', strtotime($r['ini']));
                $valores[] = (float)$r['v'];
                $quantidades[] = (int)$r['q'];
            }
            break;
        default:
            $sql = "SELECT DATE_FORMAT(data_venda, '%Y-%m') AS p, SUM(total) AS v, COUNT(*) AS q
                      FROM vendas WHERE status = 'FINALIZADA' AND data_venda >= DATE_SUB(CURDATE(), INTERVAL " . $offset . ")
                      GROUP BY DATE_FORMAT(data_venda, '%Y-%m') ORDER BY p";
            foreach ($pdo->query($sql) as $r) {
                $labels[] = date('m/Y', strtotime($r['p'] . '-01'));
                $valores[] = (float)$r['v'];
                $quantidades[] = (int)$r['q'];
            }
            break;
    }

    json_resposta(true, '', compact('labels', 'valores', 'quantidades'));
}

if ($grafico === 'financeiro') {
    $tipo = $_GET['tipo'] ?? 'fluxo';
    $pdo = db();

    if ($tipo === 'fluxo') {
        // Últimos 12 meses: entradas e saídas
        $labels = [];
        $entradas = [];
        $saidas = [];
        $sql = "SELECT DATE_FORMAT(data_movimento, '%Y-%m') AS p,
                       COALESCE(SUM(CASE WHEN tipo = 'ENTRADA' AND estorno = 0 THEN valor END), 0) AS e,
                       COALESCE(SUM(CASE WHEN tipo = 'SAIDA'  AND estorno = 0 THEN valor END), 0) AS s,
                       COALESCE(SUM(CASE WHEN estorno = 1 THEN valor END), 0) AS est
                  FROM fluxo_caixa
                 WHERE data_movimento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(data_movimento, '%Y-%m') ORDER BY p";
        foreach ($pdo->query($sql) as $r) {
            $labels[] = date('m/Y', strtotime($r['p'] . '-01'));
            $entradas[] = (float)$r['e'] - (float)$r['est'];
            $saidas[] = (float)$r['s'] - (float)$r['est'];
        }
        json_resposta(true, '', compact('labels', 'entradas', 'saidas'));
    }

    if ($tipo === 'situacao') {
        $resumo = [
            'receber_pendente' => 0, 'receber_vencida' => 0, 'receber_paga' => 0,
            'pagar_pendente' => 0, 'pagar_vencida' => 0, 'pagar_paga' => 0,
        ];
        foreach ($pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento >= CURDATE() THEN valor - valor_pago END),0) AS receber_pendente,
                COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento < CURDATE() THEN valor - valor_pago END),0) AS receber_vencida,
                COALESCE(SUM(CASE WHEN status = 'PAGO' THEN valor END),0) AS receber_paga
             FROM contas_receber") as $r) {
            $resumo['receber_pendente'] = (float)$r['receber_pendente'];
            $resumo['receber_vencida'] = (float)$r['receber_vencida'];
            $resumo['receber_paga'] = (float)$r['receber_paga'];
        }
        foreach ($pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento >= CURDATE() THEN valor - valor_pago END),0) AS pagar_pendente,
                COALESCE(SUM(CASE WHEN status IN ('PENDENTE','PARCIAL') AND vencimento < CURDATE() THEN valor - valor_pago END),0) AS pagar_vencida,
                COALESCE(SUM(CASE WHEN status = 'PAGO' THEN valor END),0) AS pagar_paga
             FROM contas_pagar") as $r) {
            $resumo['pagar_pendente'] = (float)$r['pagar_pendente'];
            $resumo['pagar_vencida'] = (float)$r['pagar_vencida'];
            $resumo['pagar_paga'] = (float)$r['pagar_paga'];
        }
        json_resposta(true, '', $resumo);
    }
}

json_resposta(false, 'Gráfico não reconhecido.', null, 400);