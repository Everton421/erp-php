<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('vendas_ver');

$id = (int)($_GET['id'] ?? 0);
$venda = buscar_linha('vendas', $id);
if (!$venda) {
    flash('danger', 'Venda não encontrada.');
    redirecionar('vendas/index.php');
}

$cliente = $venda['cliente_id'] ? buscar_linha('clientes', (int)$venda['cliente_id']) : null;
$vendedor = $venda['vendedor_id'] ? buscar_linha('usuarios', (int)$venda['vendedor_id']) : null;
$criador = buscar_linha('usuarios', (int)$venda['criado_por']);

$stmt = db()->prepare(
    'SELECT vi.*, p.descricao AS produto_desc, p.codigo, u.sigla AS unidade
       FROM venda_itens vi
       JOIN produtos p ON p.id = vi.produto_id
       LEFT JOIN unidades u ON u.id = p.unidade_id
      WHERE vi.venda_id = ?
      ORDER BY vi.id'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT vp.*, fp.nome AS forma
       FROM venda_pagamentos vp
       JOIN formas_pagamento fp ON fp.id = vp.forma_pagamento_id
      WHERE vp.venda_id = ? ORDER BY vp.id'
);
$stmt->execute([$id]);
$pagamentos = $stmt->fetchAll();

$empresa = [
    'nome' => obter_config('empresa_nome', 'Minha Empresa'),
    'cnpj' => obter_config('empresa_cnpj', ''),
    'endereco' => obter_config('empresa_endereco', ''),
    'telefone' => obter_config('empresa_telefone', ''),
    'rodape' => obter_config('nota_rodape_venda', 'Obrigado pela preferência!'),
];

$largura = ($_GET['w'] ?? '80') === '58' ? '58mm' : '80mm';
$ehOrcamento = $venda['status'] === 'ORCAMENTO';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupom - <?= e($venda['numero']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #e2e8f0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.25;
            color: #000;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .botoes-topo {
            margin-bottom: 12px;
            display: flex;
            gap: 8px;
        }
        .btn-acao {
            background: #1e293b;
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 13px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .btn-acao:hover { background: #334155; }
        .cupom {
            background: #fff;
            width: <?= $largura ?>;
            min-height: 100mm;
            padding: 5mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .divisor {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .tabela-itens {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 4px;
        }
        .tabela-itens th, .tabela-itens td {
            padding: 2px 0;
            vertical-align: top;
        }
        .tabela-itens th {
            border-bottom: 1px solid #000;
            text-align: left;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .botoes-topo {
                display: none !important;
            }
            .cupom {
                box-shadow: none;
                width: 100%;
                padding: 2mm;
            }
        }
    </style>
</head>
<body>
    <div class="botoes-topo">
        <button class="btn-acao" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <button class="btn-acao" onclick="window.close()"><i class="bi bi-x-lg"></i> Fechar</button>
    </div>

    <div class="cupom">
        <div class="text-center">
            <h2 style="font-size:14px;font-weight:bold"><?= e($empresa['nome']) ?></h2>
            <?php if ($empresa['cnpj']): ?><div>CNPJ: <?= e($empresa['cnpj']) ?></div><?php endif; ?>
            <?php if ($empresa['endereco']): ?><div><?= e($empresa['endereco']) ?></div><?php endif; ?>
            <?php if ($empresa['telefone']): ?><div>Tel: <?= e($empresa['telefone']) ?></div><?php endif; ?>
            <div class="divisor"></div>
            <div class="fw-bold" style="font-size:12px">
                <?= $ehOrcamento ? 'ORÇAMENTO DE VENDA' : 'CUPOM NÃO FISCAL' ?>
            </div>
            <div>Nº <?= e($venda['numero']) ?> • <?= date('d/m/Y H:i', strtotime($venda['data_venda'])) ?></div>
        </div>

        <div class="divisor"></div>

        <div>
            <div><b>Cliente:</b> <?= e($cliente['nome'] ?? 'Consumidor Final') ?></div>
            <?php if (!empty($cliente['documento'])): ?><div><b>CPF/CNPJ:</b> <?= e($cliente['documento']) ?></div><?php endif; ?>
            <?php if ($vendedor): ?><div><b>Vendedor:</b> <?= e($vendedor['nome']) ?></div><?php endif; ?>
        </div>

        <div class="divisor"></div>

        <table class="tabela-itens">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-end">Qtd</th>
                    <th class="text-end">Vl.Un</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $i => $item): ?>
                <tr>
                    <td colspan="4" style="padding-top:3px">
                        <b><?= $i + 1 ?>. <?= e($item['produto_desc']) ?></b>
                        <?php if ($item['codigo']): ?><small>(<?= e($item['codigo']) ?>)</small><?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td class="text-end"><?= formatar_qtde($item['quantidade']) ?></td>
                    <td class="text-end"><?= formatar_moeda($item['preco_unitario']) ?></td>
                    <td class="text-end fw-bold"><?= formatar_moeda($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divisor"></div>

        <table style="width:100%;font-size:11px">
            <tr><td>Subtotal:</td><td class="text-end"><?= formatar_moeda($venda['subtotal']) ?></td></tr>
            <?php if ((float)$venda['desconto'] > 0): ?>
            <tr><td>Desconto:</td><td class="text-end">- <?= formatar_moeda($venda['desconto']) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)$venda['acrescimo'] > 0): ?>
            <tr><td>Acréscimo:</td><td class="text-end">+ <?= formatar_moeda($venda['acrescimo']) ?></td></tr>
            <?php endif; ?>
            <tr style="font-size:13px;font-weight:bold">
                <td style="padding-top:4px">TOTAL:</td>
                <td class="text-end" style="padding-top:4px"><?= formatar_moeda($venda['total']) ?></td>
            </tr>
        </table>

        <?php if (count($pagamentos)): ?>
        <div class="divisor"></div>
        <div><b>FORMA DE PAGAMENTO:</b></div>
        <table style="width:100%;font-size:11px">
            <?php foreach ($pagamentos as $pg): ?>
            <tr>
                <td><?= e($pg['forma']) ?><?= $pg['qtde_parcelas'] > 1 ? ' (' . $pg['qtde_parcelas'] . 'x)' : '' ?></td>
                <td class="text-end"><?= formatar_moeda($pg['valor']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if (!empty($venda['observacao'])): ?>
        <div class="divisor"></div>
        <div><b>Obs:</b> <?= nl2br(e($venda['observacao'])) ?></div>
        <?php endif; ?>

        <div class="divisor"></div>

        <div class="text-center" style="font-size:10px;margin-top:4px">
            <div><?= nl2br(e($empresa['rodape'])) ?></div>
            <div style="margin-top:4px;color:#555">Op: <?= e($criador['nome'] ?? 'Sistema') ?></div>
        </div>
    </div>

    <?php if (!empty($_GET['auto'])): ?>
    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
    <?php endif; ?>
</body>
</html>
