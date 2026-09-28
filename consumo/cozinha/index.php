<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('cozinha_ver');

$tituloPagina = rotulo_producao();
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-fire me-2"></i><?= e(rotulo_producao()) ?></h1>
        <span class="subtitulo">
            Fila de preparo atualizada a cada 10 segundos &middot;
            itens em atraso ficam destacados em vermelho
        </span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-soft js-kds-som" data-bs-toggle="tooltip" title="Ativar o som dos avisos">
            <i class="bi bi-volume-up"></i>Som
        </button>
        <button type="button" class="btn btn-soft js-kds-tela" data-bs-toggle="tooltip" title="Alternar tela cheia">
            <i class="bi bi-fullscreen"></i>
        </button>
    </div>
</div>

<div class="kds-barras">
    <div class="kds-contador">
        <i class="bi bi-hourglass-split text-warning fs-4"></i>
        <div>
            <div class="n" data-kds-count="pendente">0</div>
            <div class="r">Aguardando</div>
        </div>
    </div>
    <div class="kds-contador">
        <i class="bi bi-fire text-info fs-4"></i>
        <div>
            <div class="n" data-kds-count="preparando">0</div>
            <div class="r">Preparando</div>
        </div>
    </div>
    <div class="kds-contador">
        <i class="bi bi-check2-circle text-success fs-4"></i>
        <div>
            <div class="n" data-kds-count="pronto">0</div>
            <div class="r">Prontos</div>
        </div>
    </div>
    <div class="kds-contador">
        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
        <div>
            <div class="n" data-kds-count="atrasado">0</div>
            <div class="r">Atrasados</div>
        </div>
    </div>
</div>

<div class="kds-colunas" data-kds-colunas data-intervalo="10">
    <div class="kds-coluna">
        <h3>
            <i class="bi bi-hourglass-split text-warning"></i>Aguardando
            <span class="badge text-bg-secondary ms-auto" data-kds-count="pendente">0</span>
        </h3>
        <div data-kds-lista="PENDENTE">
            <div class="text-center text-muted small py-3">Nada por aqui.</div>
        </div>
    </div>

    <div class="kds-coluna">
        <h3>
            <i class="bi bi-fire text-info"></i>Preparando
            <span class="badge text-bg-secondary ms-auto" data-kds-count="preparando">0</span>
        </h3>
        <div data-kds-lista="PREPARANDO">
            <div class="text-center text-muted small py-3">Nada por aqui.</div>
        </div>
    </div>

    <div class="kds-coluna">
        <h3>
            <i class="bi bi-check2-circle text-success"></i>Prontos para entrega
            <span class="badge text-bg-secondary ms-auto" data-kds-count="pronto">0</span>
        </h3>
        <div data-kds-lista="PRONTO">
            <div class="text-center text-muted small py-3">Nada por aqui.</div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
            <span><i class="bi bi-1-circle me-1"></i>Clique em <b>Iniciar preparo</b> ao iniciar o item.</span>
            <span><i class="bi bi-2-circle me-1"></i>Ao terminar, clique em <b>Marcar pronto</b>.</span>
            <span><i class="bi bi-3-circle me-1"></i>Após a entrega ao cliente, clique em <b>Concluir entrega</b>.</span>
        </div>
    </div>
</div>

<script>
    // Navegação por teclado: 1/2/3 movem o foco entre as colunas
    $(document).on('keydown', function (e) {
        if (['input', 'textarea', 'select'].includes((e.target.tagName || '').toLowerCase())) return;
        if (e.key === 'f' || e.key === 'F') {
            CONSUMO.kds.telaCheia(!CONSUMO.kds.telaCheia);
        }
    });
</script>
<?php include INC . 'footer.php'; ?>
