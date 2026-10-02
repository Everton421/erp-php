<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('cardapio_ver');

$busca = trim((string)($_GET['busca'] ?? ''));
$catFiltro = (int)($_GET['categoria'] ?? 0);

$categorias = consumo_categorias();
$produtos = consumo_produtos([
    'categoria_id' => $catFiltro > 0 ? $catFiltro : '',
    'busca' => $busca,
]);

$qtdAtivos = count(consumo_produtos());
$qtdTotal = (int)db()->query('SELECT COUNT(*) FROM produtos')->fetchColumn();
$qtdInativos = $qtdTotal - $qtdAtivos;
$valorMedio = 0.0;
foreach ($produtos as $p) {
    $valorMedio += (float)$p['preco'];
}
$valorMedio = count($produtos) > 0 ? $valorMedio / count($produtos) : 0.0;

$tituloPagina = 'Produtos';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-box-seam me-2"></i>Produtos</h1>
        <span class="subtitulo">
            Catálogo do <?= e(rotulo_consumo()) ?> &middot;
            <?= count($produtos) ?> produto(s) na listagem &middot;
            <?= $qtdAtivos ?> ativo(s) &middot;
            <?= $qtdInativos ?> inativo(s) &middot;
            preço médio <?= formatar_moeda($valorMedio) ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <?php if (tem_permissao('produtos_ver')): ?>
        <a href="<?= url('produtos/index.php') ?>" class="btn btn-soft">
            <i class="bi bi-box-seam me-1"></i>Módulo Produtos
        </a>
        <?php endif; ?>
        <?php if (tem_permissao('produtos_criar')): ?>
        <a href="<?= url('produtos/form.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Novo produto
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        O <?= e(rotulo_consumo()) ?> usa os produtos cadastrados em <strong>Produtos</strong>.
        O preço praticado vem do <em>preço promocional</em> quando preenchido, senão do <em>preço de venda</em>.
        Esta tela é somente leitura — use o módulo <strong>Produtos</strong> para incluir, alterar ou excluir.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="busca">Buscar produto</label>
                <input type="text" class="form-control" id="busca" name="busca" value="<?= e($busca) ?>"
                       placeholder="Descrição ou código" maxlength="60">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="categoria">Categoria</label>
                <select class="form-select" id="categoria" name="categoria">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $catFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="<?= url('consumo/cardapio/index.php') ?>" class="btn btn-light">Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-geral responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-end">Preço de venda</th>
                        <th class="text-end">Preço no consumo</th>
                        <th>Status</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td>
                            <?php if ($p['foto'] && file_exists(BASE_PATH . '/' . $p['foto'])): ?>
                            <img src="<?= url($p['foto']) ?>" width="38" height="38" class="rounded object-fit-cover" alt="">
                            <?php else: ?>
                            <span class="d-inline-grid" style="width:38px;height:38px;place-items:center;background:#eef1fb;border-radius:8px;color:#8ea0d4">
                                <i class="bi bi-cup-hot"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= e($p['codigo'] ?: '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($p['descricao']) ?></div>
                            <?php if ($p['descricao_complementar']): ?>
                            <small class="text-muted"><?= e($p['descricao_complementar']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['categoria_nome'] ?: '-') ?></td>
                        <td class="text-end" data-moeda-exibir><?= (float)$p['preco_venda'] ?></td>
                        <td class="text-end fw-semibold" data-moeda-exibir><?= (float)$p['preco'] ?></td>
                        <td>
                            <span class="badge bg-success">Ativo</span>
                        </td>
                        <td class="text-nowrap no-print">
                            <?php if (tem_permissao('produtos_editar')): ?>
                            <a href="<?= url('produtos/form.php?id=' . (int)$p['id']) ?>"
                               class="acao-btn btn-soft" data-bs-toggle="tooltip" title="Editar no módulo Produtos">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include INC . 'footer.php'; ?>
