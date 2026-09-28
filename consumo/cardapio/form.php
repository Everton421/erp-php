<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();
exigir_permissao('cardapio_editar');

$id = (int)($_GET['id'] ?? 0);
$edicao = $id > 0;

$item = [
    'id' => 0, 'categoria_id' => '', 'codigo' => '', 'descricao' => '',
    'descricao_complementar' => '', 'preco' => '0,00', 'tempo_preparo' => '',
    'observacoes' => '', 'foto' => '', 'ativo' => 1,
];

if ($edicao) {
    $item = cardapio_item($id);
    if (!$item) {
        flash('danger', 'Item do cardápio não encontrado.');
        redirecionar('consumo/cardapio/index.php');
    }
    $item['preco'] = formatar_moeda($item['preco']);
}

$categorias = cardapio_categorias(false);

$tituloPagina = $edicao ? 'Editar item do cardápio' : 'Novo item do cardápio';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-text me-2"></i><?= $edicao ? 'Editar item' : 'Novo item' ?></h1>
        <span class="subtitulo">
            <?= $edicao ? 'Item #' . (int)$item['id'] : 'Cadastro de item do cardápio' ?>
        </span>
    </div>
    <a href="<?= url('consumo/cardapio/index.php') ?>" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<form method="post" action="<?= url('consumo/cardapio/salvar.php') ?>" class="js-converte" id="formItem" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_item">
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-card-text"></i>Identificação</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="categoria_id">Categoria</label>
                            <select class="form-select" id="categoria_id" name="categoria_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                        <?= (string)$item['categoria_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$categorias): ?>
                            <small class="text-danger">
                                Nenhuma categoria cadastrada.
                                <a href="<?= url('consumo/cardapio/categorias.php') ?>">Cadastrar agora</a>.
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="codigo">Código</label>
                            <input type="text" class="form-control" id="codigo" name="codigo"
                                   value="<?= e($item['codigo']) ?>" maxlength="20" placeholder="Ex.: C003">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="tempo_preparo">Tempo de preparo (min)</label>
                            <input type="text" class="form-control" id="tempo_preparo" name="tempo_preparo"
                                   data-mask="int" value="<?= e((string)($item['tempo_preparo'] ?? '')) ?>"
                                   maxlength="4" placeholder="Ex.: 15">
                        </div>
                        <div class="col-12">
                            <label class="form-label obrigatorio" for="descricao">Descrição</label>
                            <input type="text" class="form-control" id="descricao" name="descricao"
                                   value="<?= e($item['descricao']) ?>" required maxlength="150" autofocus>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="descricao_complementar">Descrição complementar</label>
                            <input type="text" class="form-control" id="descricao_complementar"
                                   name="descricao_complementar"
                                   value="<?= e((string)($item['descricao_complementar'] ?? '')) ?>"
                                   maxlength="255" placeholder="Ex.: porção para duas pessoas">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="observacoes">Observações internas</label>
                            <input type="text" class="form-control" id="observacoes" name="observacoes"
                                   value="<?= e((string)($item['observacoes'] ?? '')) ?>" maxlength="255"
                                   placeholder="Ex.: ingredientes, modo de preparo, alternativa">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-cash-coin"></i>Preço e status</div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="preco">Preço de venda</label>
                        <input type="text" class="form-control form-control-lg" id="preco" name="preco"
                               data-moeda value="<?= e($item['preco']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ativo">Status</label>
                        <select class="form-select" id="ativo" name="ativo">
                            <option value="1" <?= (int)$item['ativo'] === 1 ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= (int)$item['ativo'] === 0 ? 'selected' : '' ?>>Inativo</option>
                        </select>
                        <small class="text-muted">Itens inativos não aparecem no seletor do atendente.</small>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-image"></i>Foto do item</div>
                <div class="card-body-custom text-center">
                    <?php if ($edicao && $item['foto'] && file_exists(BASE_PATH . '/' . $item['foto'])): ?>
                    <img src="<?= url($item['foto']) ?>" id="previewFoto" class="rounded mb-2 object-fit-cover"
                         style="width:150px;height:150px" alt="Foto atual">
                    <?php else: ?>
                    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" id="previewFoto"
                         class="rounded mb-2 border" style="width:150px;height:150px;object-fit:cover" alt="">
                    <?php endif; ?>
                    <input type="file" class="form-control form-control-sm" id="foto" name="foto"
                           accept="image/jpeg,image/png,image/webp">
                    <?php if ($edicao && $item['foto']): ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remover_foto" name="remover_foto" value="1">
                        <label class="form-check-label small" for="remover_foto">Remover a foto atual</label>
                    </div>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-2">JPG, PNG ou WEBP até 2MB.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i><?= $edicao ? 'Salvar alterações' : 'Cadastrar item' ?>
        </button>
        <a href="<?= url('consumo/cardapio/index.php') ?>" class="btn btn-light">Cancelar</a>
    </div>
</form>

<script>
    $(function () {
        $('#preco').on('blur', function () {
            this.value = APP.fmtNumero(APP.paraNumero(this.value), 2);
        });

        $('#foto').on('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = (ev) => $('#previewFoto').attr('src', ev.target.result);
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
</script>
<?php include INC . 'footer.php'; ?>
