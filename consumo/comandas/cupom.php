<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('comandas_imprimir');

$id = (int)($_GET['id'] ?? 0);
$comanda = comanda_buscar($id);
if (!$comanda) {
    flash('danger', 'Comanda não encontrada.');
    redirecionar('consumo/comandas/index.php');
}

$pdo = db();
$itens = comanda_itens($id);

$stmt = $pdo->prepare(
    'SELECT fp.nome, pg.valor, pg.qtde_parcelas, pg.data_pagamento
       FROM comanda_pagamentos pg
       JOIN formas_pagamento fp ON fp.id = pg.forma_pagamento_id
      WHERE pg.comanda_id = ?
      ORDER BY pg.id'
);
$stmt->execute([$id]);
$pagamentos = $stmt->fetchAll();

$empresa = obter_config('empresa_nome', rotulo_consumo());
$documento = comanda_documento($comanda);
$qtdItens = count(array_filter($itens, fn($i) => (string)$i['status'] !== 'CANCELADO'));

/* Cupom térmico: não usa o layout padrão, apenas a folha de estilo do ERP. */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupom <?= e($comanda['numero']) ?></title>
    <link rel="stylesheet" href="<?= url_asset(ASSETS . '/css/app.css') ?>">
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body {
            width: 72mm;
            margin: 0 auto;
            padding: 8px 0;
            font-family: "Consolas", "Courier New", monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #fff;
        }
        .centro { text-align: center; }
        .dir { text-align: right; }
        .esq { text-align: left; }
        .negrito { font-weight: 700; }
        .g { font-size: 15px; font-weight: 700; }
        .p { font-size: 10px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .quebra { border: 0; border-top: 1px solid #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.dir, th.dir { text-align: right; white-space: nowrap; }
        .item { page-break-inside: avoid; }
        .obs { font-size: 10px; padding-left: 8px; }
        .total { font-size: 14px; font-weight: 700; }
        .acoes { margin-top: 10px; text-align: center; }
        .acoes button {
            padding: 8px 14px;
            font-size: 12px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
            border-radius: 4px;
        }
        .fios { margin-top: 12px; font-size: 9px; letter-spacing: 1px; }
        .fios div { overflow: hidden; white-space: nowrap; }
        @media screen {
            body { box-shadow: 0 2px 14px rgba(0,0,0,.18); margin: 18px auto; padding: 10px; }
        }
    </style>
</head>
<body>
<div class="centro">
    <div class="g"><?= e($empresa) ?></div>
    <div class="p"><?= e((string)obter_config('empresa_endereco', '')) ?></div>
    <?php if (obter_config('empresa_telefone', '')): ?>
    <div class="p">Tel: <?= e((string)obter_config('empresa_telefone', '')) ?></div>
    <?php endif; ?>
    <div class="p">CNPJ: <?= e((string)obter_config('empresa_cnpj', '—')) ?></div>
</div>

<hr>
<div class="centro negrito">COMANDA <?= e($comanda['numero']) ?></div>
<div class="centro p"><?= e($documento) ?></div>
<hr>

<table>
    <tr>
        <td>Mesa:</td>
        <td class="negrito">
            <?= (int)$comanda['mesa_numero'] ?>
            <?= $comanda['mesa_nome'] ? e($comanda['mesa_nome']) : '' ?>
        </td>
    </tr>
    <tr>
        <td>Atendente:</td>
        <td><?= e($comanda['garcom_nome']) ?></td>
    </tr>
    <tr>
        <td>Abertura:</td>
        <td><?= formatar_datahora((string)$comanda['data_abertura']) ?></td>
    </tr>
    <?php if ($comanda['data_fechamento']): ?>
    <tr>
        <td>Fechamento:</td>
        <td><?= formatar_datahora((string)$comanda['data_fechamento']) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td>Itens:</td>
        <td><?= (int)$qtdItens ?></td>
    </tr>
</table>

<?php if ($comanda['observacao']): ?>
<hr>
<div class="esq p"><b>OBS:</b> <?= e($comanda['observacao']) ?></div>
<?php endif; ?>

<hr>
<table>
    <thead>
    <tr>
        <th style="width:34px">QTD</th>
        <th>DESCRICAO</th>
        <th class="dir" style="width:62px">VALOR</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($itens as $i): ?>
    <?php if ((string)$i['status'] === 'CANCELADO'): continue; endif; ?>
    <tr class="item">
        <td class="negrito"><?= formatar_numero((float)$i['quantidade'], 0) ?></td>
        <td><?= e($i['descricao']) ?></td>
        <td class="dir"><?= formatar_moeda($i['total']) ?></td>
    </tr>
    <?php if ($i['observacoes']): ?>
    <tr>
        <td></td>
        <td class="obs">* <?= e($i['observacoes']) ?></td>
        <td></td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>

<hr>
<table>
    <tr>
        <td>Subtotal</td>
        <td class="dir"><?= formatar_moeda($comanda['subtotal']) ?></td>
    </tr>
    <?php if ((float)$comanda['desconto'] > 0): ?>
    <tr>
        <td>Desconto</td>
        <td class="dir">- <?= formatar_moeda($comanda['desconto']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ((float)$comanda['acrescimo'] > 0): ?>
    <tr>
        <td>Acrescimo</td>
        <td class="dir">+ <?= formatar_moeda($comanda['acrescimo']) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="total">TOTAL</td>
        <td class="dir total"><?= formatar_moeda($comanda['total']) ?></td>
    </tr>
</table>

<?php if ($pagamentos): ?>
<hr>
<div class="negrito">PAGAMENTOS</div>
<table>
    <?php foreach ($pagamentos as $pg): ?>
    <tr>
        <td><?= e($pg['nome']) ?></td>
        <td class="dir"><?= formatar_moeda($pg['valor']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <td class="negrito">Pago</td>
        <td class="dir negrito"><?= formatar_moeda($comanda['valor_pago']) ?></td>
    </tr>
    <?php if ((float)$comanda['troco'] > 0): ?>
    <tr>
        <td>Troco</td>
        <td class="dir"><?= formatar_moeda($comanda['troco']) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="negrito">Saldo</td>
        <td class="dir negrito">
            <?= formatar_moeda(max(0, (float)$comanda['total'] - (float)$comanda['valor_pago'])) ?>
        </td>
    </tr>
</table>
<?php else: ?>
<hr>
<table>
    <tr>
        <td class="negrito">STATUS</td>
        <td class="dir negrito"><?= e($comanda['status_pagamento']) ?></td>
    </tr>
</table>
<?php endif; ?>

<hr>
<div class="fios">
    <div>* * * * * * * * * * * * * * * * * * * * * * * *</div>
    <div>* * * * * * * * * * * * * * * * * * * * * * * *</div>
    <div>* * * * * * * * * * * * * * * * * * * * * * * *</div>
</div>
<div class="centro p" style="margin-top:6px">
   Emitido em <?= date('d/m/Y H:i') ?> &middot; <?= e($_SESSION['usuario_nome'] ?? 'Sistema') ?><br>
    <?= e($documento) ?>
</div>

<div class="acoes no-print">
    <button type="button" onclick="window.print()">IMPRIMIR</button>
</div>
</body>
</html>
