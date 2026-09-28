<?php
declare(strict_types=1);

/**
 * Abre o layout padrão (topbar + sidebar + área de conteúdo).
 * A página deve ter chamado config e exigir_login antes deste include.
 */

$usuarioLogado = usuario_atual();
$nomeEmpresa = obter_config('empresa_nome', 'Gestor Comercial');
$paginaConsumo = strpos((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/consumo/') !== false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($tituloPagina ?? 'Painel') ?> - <?= e($nomeEmpresa) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= url(ASSETS . '/js/app.js') ?>"></script>
    <link rel="stylesheet" href="<?= url(ASSETS . '/css/app.css') ?>">
<?php if ($paginaConsumo): ?>
<link rel="stylesheet" href="<?= url(ASSETS . '/css/consumo.css') ?>">
<?php endif; ?>
    <script>
        (function() {
            if (localStorage.getItem('app_tema') === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('dark-mode');
                });
            }
        })();
    </script>
</head>
<body class="app-body">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="<?= url('dashboard/index.php') ?>">
            <span class="logo-ico bi bi-currency-dollar"></span>
            <span><?= e($nomeEmpresa) ?></span>
        </a>
        <?php include INC . 'sidebar.php'; ?>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-wrapper">
        <!-- Topbar -->
        <header class="topbar">
            <button class="btn-icone topbar-mobile-burger" id="btnSidebarMobile" type="button" aria-label="Menu">
                <i class="bi bi-list"></i>
            </button>
            <button class="btn-icone d-none d-lg-grid" id="btnSidebarDesktop" type="button" aria-label="Recolher menu">
                <i class="bi bi-layout-sidebar-inset-reverse"></i>
            </button>

            <div class="busca-global">
                <div class="input-group input-group-sm position-relative">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control pe-5" id="buscaGlobal" placeholder="Buscar produto, cliente, fornecedor..." autocomplete="off">
                    <kbd class="shortcut-badge d-none d-md-inline" title="Atalho: Ctrl + K">Ctrl K</kbd>
                </div>
                <div class="busca-resultados"></div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-1">
                <?php if (tem_permissao('comandas_criar')): ?>
                <a href="<?= url('consumo/comandas/nova.php') ?>" class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center gap-1 me-1" title="Abrir comanda (Atalho: F3)">
                    <i class="bi bi-plus-lg"></i> Abrir comanda <kbd class="bg-white text-dark ms-1 d-none d-lg-inline-block" style="font-size:0.65rem">F3</kbd>
                </a>
                <?php elseif (tem_permissao('vendas_criar')): ?>
                <a href="<?= url('vendas/nova.php') ?>" class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center gap-1 me-1" title="Nova venda (Atalho: F2)">
                    <i class="bi bi-plus-lg"></i> Nova venda <kbd class="bg-white text-dark ms-1 d-none d-lg-inline-block" style="font-size:0.65rem">F2</kbd>
                </a>
                <?php endif; ?>

                <!-- Botão Atalhos de Teclado -->
                <button class="btn-icone" type="button" data-bs-toggle="modal" data-bs-target="#modalAtalhos" title="Atalhos do teclado (?)">
                    <i class="bi bi-keyboard"></i>
                </button>

                <!-- Botão Alternar Tema Escuro/Claro -->
                <button class="btn-icone" id="btnTemaDark" type="button" title="Alternar modo escuro">
                    <i class="bi bi-moon-stars" id="icoTema"></i>
                </button>

                <!-- Dropdown de Notificações / Alertas -->
                <div class="dropdown">
                    <button class="btn-icone" id="btnNotificacoes" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações e alertas">
                        <i class="bi bi-bell"></i>
                        <span class="badge-notif d-none" id="badgeNotif">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow">
                        <div class="notif-header">
                            <span><i class="bi bi-bell me-2"></i>Alertas do Sistema</span>
                            <span class="badge bg-primary-subtle text-primary" id="notifTotalBadge">0</span>
                        </div>
                        <div class="notif-body" id="notifCorpo">
                            <div class="p-3 text-center text-muted small">
                                <span class="spinner-border spinner-border-sm me-1"></span> Carregando alertas...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Perfil do Usuário -->
                <div class="dropdown">
                    <button class="btn-icone" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Usuário">
                        <i class="bi bi-person-circle" style="font-size:1.4rem"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <div class="px-3 py-2">
                                <div class="fw-bold"><?= e($usuarioLogado['nome'] ?? '') ?></div>
                                <small class="text-muted"><?= e($usuarioLogado['usuario'] ?? '') ?></small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalSenha"><i class="bi bi-key me-2"></i>Alterar senha</button></li>
                        <li><a class="dropdown-item text-danger" href="<?= url('login/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <?php if (function_exists('licenca_status') && licenca_status() === 'trial'): ?>
        <div class="licenca-banner">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-info-circle-fill"></i>
                <span>Versão de demonstração — restam <b><?= (int)licenca_dias_restantes() ?></b> dia(s).</span>
                <a class="btn btn-sm btn-outline-primary ms-auto" href="<?= url('licenca/index.php') ?>">Ativar licença</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modal de Atalhos de Teclado -->
        <div class="modal fade" id="modalAtalhos" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-keyboard me-2"></i>Atalhos Rápidos de Teclado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-cart-plus me-2 text-primary"></i>Nova venda</span>
                                <kbd>F2</kbd>
                            </li>
                            <?php if (tem_permissao('comandas_criar')): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Abrir comanda</span>
                                <kbd>F3</kbd>
                            </li>
                            <?php endif; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-search me-2 text-primary"></i>Buscar produto, cliente ou fornecedor</span>
                                <div><kbd>Ctrl</kbd> + <kbd>K</kbd> ou <kbd>/</kbd></div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-moon-stars me-2 text-primary"></i>Alternar modo escuro / claro</span>
                                <div><kbd>Ctrl</kbd> + <kbd>J</kbd></div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-x-circle me-2 text-primary"></i>Fechar buscas e modais</span>
                                <kbd>Esc</kbd>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-question-circle me-2 text-primary"></i>Abrir este mapa de atalhos</span>
                                <kbd>?</kbd>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Entendi</button>
                    </div>
                </div>
            </div>
        </div>

        <main class="app-main">