<?php
declare(strict_types=1);

/**
 * Menu lateral. Os itens são exibidos conforme permissão do usuário.
 */

/*
 * Módulo atual = pasta do script relativa à raiz do app.
 * Suporta subpastas (consumo/comandas) e quebra a ambiguidade entre
 * relatorios/index.php e relatorios/logs.php, que compartilham a mesma pasta.
 */
$caminhoRequisicao = (string)parse_url((string)($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH);
$caminhoRelativo   = preg_replace('#^' . preg_quote(BASE_URL, '#') . '/#', '', $caminhoRequisicao);
$moduloAtual       = trim(str_replace('\\', '/', dirname((string)$caminhoRelativo)), '/');
if ($moduloAtual === '.' || $moduloAtual === '/') {
    $moduloAtual = '';
}
$paginaAtual = basename($caminhoRequisicao);

function link_menu(string $modulo, string $moduloAtual, array $permChaves, string $icone, string $rotulo): void
{
    foreach ($permChaves as $chave) {
        if (tem_permissao($chave)) {
            $cls = $moduloAtual === $modulo ? ' ativo' : '';
            echo '<a class="nav-link' . $cls . '" href="' . url($modulo . '/index.php') . '">'
                . '<i class="bi bi-' . $icone . '"></i><span>' . e($rotulo) . '</span></a>';
            return;
        }
    }
}

/**
 * Item de menu com caminho e conjunto de módulos ativos (usado pelo módulo de
 * Consumo, cujos itens vivem em subpastas).
 */
function link_menu_rota(string $caminho, array $modulosAtivos, string $moduloAtual, string $icone, string $rotulo): void
{
    $cls = in_array($moduloAtual, $modulosAtivos, true) ? ' ativo' : '';
    echo '<a class="nav-link' . $cls . '" href="' . url($caminho) . '">'
        . '<i class="bi bi-' . $icone . '"></i><span>' . e($rotulo) . '</span></a>';
}
?>
<nav class="sidebar-nav">
    <div class="nav-secao">Principal</div>
    <?php if (tem_permissao('dashboard_ver')): ?>
    <a class="nav-link<?= $moduloAtual === 'dashboard' ? ' ativo' : '' ?>" href="<?= url('dashboard/index.php') ?>">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
    </a>
    <?php endif; ?>

    <div class="nav-secao">Cadastros</div>
    <?php link_menu('clientes', $moduloAtual, ['clientes_ver'], 'people', 'Clientes'); ?>
    <?php link_menu('fornecedores', $moduloAtual, ['fornecedores_ver'], 'truck', 'Fornecedores'); ?>
    <?php link_menu('produtos', $moduloAtual, ['produtos_ver'], 'box-seam', 'Produtos'); ?>

    <div class="nav-secao">Operações</div>
    <?php link_menu('estoque', $moduloAtual, ['estoque_ver'], 'archive', 'Estoque'); ?>
    <?php link_menu('vendas', $moduloAtual, ['vendas_ver'], 'cart-check', 'Vendas'); ?>
    <?php link_menu('compras', $moduloAtual, ['compras_ver'], 'basket', 'Compras'); ?>

    <div class="nav-secao"><?= e(rotulo_consumo()) ?></div>
    <?php if (tem_permissao('consumo_ver')): ?>
    <?php link_menu_rota('consumo/index.php', ['consumo'], $moduloAtual, 'shop-window', 'Painel do ' . rotulo_consumo()); ?>
    <?php endif; ?>
    <?php if (tem_permissao('comandas_ver')): ?>
    <?php link_menu_rota('consumo/comandas/index.php', ['consumo/comandas'], $moduloAtual, 'receipt-cutoff', 'Comandas'); ?>
    <?php endif; ?>
    <?php if (consumo_producao_ativa() && tem_permissao('cozinha_ver')): ?>
    <?php link_menu_rota('consumo/producao/index.php', ['consumo/producao'], $moduloAtual, 'fire', rotulo_producao()); ?>
    <?php endif; ?>
    <?php if (tem_permissao('caixa_consumo_ver')): ?>
    <?php link_menu_rota('consumo/caixa/index.php', ['consumo/caixa'], $moduloAtual, 'cash-coin', 'Caixa do ' . rotulo_consumo()); ?>
    <?php endif; ?>
    <?php if (tem_permissao('mesas_ver')): ?>
    <?php link_menu_rota('consumo/mesas/index.php', ['consumo/mesas'], $moduloAtual, 'grid-3x3-gap', 'Mesas'); ?>
    <?php endif; ?>
    <?php if (tem_permissao('cardapio_ver')): ?>
    <?php link_menu_rota('consumo/cardapio/index.php', ['consumo/cardapio'], $moduloAtual, 'box-seam', 'Produtos'); ?>
    <?php endif; ?>
    <?php if (tem_permissao('consumo_relatorios')): ?>
    <?php link_menu_rota('consumo/relatorios/index.php', ['consumo/relatorios'], $moduloAtual, 'graph-up', 'Relatórios do ' . rotulo_consumo()); ?>
    <?php endif; ?>

    <div class="nav-secao">Financeiro</div>
    <?php link_menu('contas_receber', $moduloAtual, ['contas_receber_ver'], 'cash-coin', 'Contas a receber'); ?>
    <?php link_menu('contas_pagar', $moduloAtual, ['contas_pagar_ver'], 'receipt', 'Contas a pagar'); ?>
    <?php link_menu('financeiro', $moduloAtual, ['caixa_ver', 'relatorios_ver'], 'graph-up-arrow', 'Fluxo de caixa'); ?>
    <?php link_menu('categorias_financeiras', $moduloAtual, ['contas_pagar_ver', 'contas_receber_ver'], 'tags', 'Cat. financeiras'); ?>

    <div class="nav-secao">Gerenciamento</div>
    <?php if (tem_permissao('categorias_ver') || tem_permissao('marcas_ver') || tem_permissao('unidades_ver')): ?>
    <a class="nav-link<?= in_array($moduloAtual, ['categorias', 'marcas', 'unidades']) ? ' ativo' : '' ?>" href="<?= url('categorias/index.php') ?>">
        <i class="bi bi-diagram-3"></i><span>Categorias e Marcas</span>
    </a>
    <?php endif; ?>
    <?php if (tem_permissao('relatorios_ver')): ?>
    <a class="nav-link<?= $moduloAtual === 'relatorios' && $paginaAtual !== 'logs.php' ? ' ativo' : '' ?>" href="<?= url('relatorios/index.php') ?>">
        <i class="bi bi-file-earmark-bar-graph"></i><span>Relatórios</span>
    </a>
    <?php endif; ?>

    <div class="nav-secao">Administração</div>
    <?php link_menu('usuarios', $moduloAtual, ['usuarios_ver'], 'people-fill', 'Usuários e Perfis'); ?>
    <?php if (tem_permissao('config_ver')): ?>
    <a class="nav-link<?= $moduloAtual === 'configuracao' ? ' ativo' : '' ?>" href="<?= url('configuracao/index.php') ?>">
        <i class="bi bi-gear"></i><span>Configurações</span>
    </a>
    <?php endif; ?>
    <?php if (tem_permissao('logs_ver')): ?>
    <a class="nav-link<?= $paginaAtual === 'logs.php' ? ' ativo' : '' ?>" href="<?= url('relatorios/logs.php') ?>">
        <i class="bi bi-journal-check"></i><span>Auditoria</span>
    </a>
    <?php endif; ?>
</nav>
<div class="sidebar-footer">
    <i class="bi bi-shield-check me-1"></i> Acesso restrito<br>
    <span class="opacity-75">v1.0.0</span>
</div>