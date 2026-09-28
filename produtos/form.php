<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('produtos_ver');

$id = (int)($_GET['id'] ?? 0);
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('produtos_editar');
}

$produto = [
    'id' => 0, 'codigo' => '', 'codigo_barras' => '', 'descricao' => '', 'descricao_complementar' => '',
    'categoria_id' => '', 'subcategoria_id' => '', 'marca_id' => '', 'unidade_id' => '',
    'ncm' => '', 'cest' => '', 'cfop' => '',
    'preco_custo' => '0,00', 'preco_venda' => '0,00', 'preco_promocional' => '',
    'margem_lucro' => '', 'estoque_atual' => '0,000', 'estoque_minimo' => '0,000',
    'estoque_maximo' => '0,000', 'localizacao' => '', 'fornecedor_id' => '', 'foto' => '', 'status' => 1,
];
$codigosExtra = [];

if ($edicao) {
    $linha = buscar_linha('produtos', $id);
    if (!$linha) {
        flash('danger', 'Produto não encontrado.');
        redirecionar('produtos/index.php');
    }
    $produto = $linha;
    $produto['preco_custo'] = formatar_moeda($linha['preco_custo']);
    $produto['preco_venda'] = formatar_moeda($linha['preco_venda']);
    $produto['preco_promocional'] = $linha['preco_promocional'] !== null && (float)$linha['preco_promocional'] > 0
        ? formatar_moeda($linha['preco_promocional']) : '';
    $produto['margem_lucro'] = formatar_numero($linha['margem_lucro'] ?? 0, 2);
    $produto['estoque_atual'] = formatar_qtde($linha['estoque_atual']);
    $produto['estoque_minimo'] = formatar_qtde($linha['estoque_minimo']);
    $produto['estoque_maximo'] = formatar_qtde($linha['estoque_maximo']);

    $stmt = db()->prepare('SELECT id, codigo, tipo FROM produto_codigos WHERE produto_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $codigosExtra = $stmt->fetchAll();
}

$categorias = db()->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();
$marcas = db()->query('SELECT id, nome FROM marcas ORDER BY nome')->fetchAll();
$unidades = db()->query('SELECT id, nome, sigla FROM unidades ORDER BY nome')->fetchAll();
$fornecedores = db()->query('SELECT id, razao_social FROM fornecedores ORDER BY razao_social')->fetchAll();
$subcategorias = db()->query('SELECT id, categoria_id, nome FROM subcategorias ORDER BY nome')->fetchAll();

$tituloPagina = $edicao ? 'Editar produto' : 'Novo produto';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-box-seam me-2"></i><?= $edicao ? 'Editar produto' : 'Novo produto' ?></h1>
        <span class="subtitulo"><?= e($produto['codigo']) ? 'Código interno: ' . e($produto['codigo']) : 'Cadastro de novo produto' ?></span>
    </div>
    <a href="<?= url('produtos/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('produtos/salvar.php') ?>" class="js-converte" id="formProduto" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_produto">
    <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-123"></i>Identificação</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="codigo">Código interno</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" value="<?= e($produto['codigo']) ?>" maxlength="30" <?= $edicao ? 'readonly class="form-control bg-light"' : '' ?>>
                            <?php if (!$edicao): ?><small class="text-muted">Em branco = gerado automaticamente</small><?php endif; ?>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="codigo_barras">Código de barras (EAN-13/8)</label>
                            <input type="text" class="form-control" id="codigo_barras" name="codigo_barras" value="<?= e($produto['codigo_barras']) ?>" maxlength="30" data-mask="int">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="unidade_id">Unidade</label>
                            <select class="form-select" id="unidade_id" name="unidade_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (string)$produto['unidade_id'] === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?> (<?= e($u['sigla']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="descricao">Descrição</label>
                            <input type="text" class="form-control" id="descricao" name="descricao" value="<?= e($produto['descricao']) ?>" required maxlength="150" autofocus>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="descricao_complementar">Descrição complementar</label>
                            <input type="text" class="form-control" id="descricao_complementar" name="descricao_complementar" value="<?= e($produto['descricao_complementar']) ?>" maxlength="255">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="categoria_id">Categoria</label>
                            <select class="form-select" id="categoria_id" name="categoria_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (string)$produto['categoria_id'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="subcategoria_id">Subcategoria</label>
                            <select class="form-select" id="subcategoria_id" name="subcategoria_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($subcategorias as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" data-cat="<?= (int)$s['categoria_id'] ?>" <?= (string)$produto['subcategoria_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="marca_id">Marca</label>
                            <select class="form-select" id="marca_id" name="marca_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($marcas as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (string)$produto['marca_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ncm">NCM</label>
                            <input type="text" class="form-control" id="ncm" name="ncm" value="<?= e($produto['ncm']) ?>" maxlength="10" data-mask="int">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="cest">CEST</label>
                            <input type="text" class="form-control" id="cest" name="cest" value="<?= e($produto['cest']) ?>" maxlength="10">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="cfop">CFOP</label>
                            <input type="text" class="form-control" id="cfop" name="cfop" value="<?= e($produto['cfop']) ?>" maxlength="10" data-mask="int">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="fornecedor_id">Fornecedor</label>
                            <select class="form-select" id="fornecedor_id" name="fornecedor_id">
                                <option value="">Selecione...</option>
                                <?php foreach ($fornecedores as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= (string)$produto['fornecedor_id'] === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['razao_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="localizacao">Localização</label>
                            <input type="text" class="form-control" id="localizacao" name="localizacao" value="<?= e($produto['localizacao']) ?>" maxlength="30" placeholder="Ex.: Corredor A, prateleira 2">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="1" <?= (int)$produto['status'] === 1 ? 'selected' : '' ?>>Ativo</option>
                                <option value="0" <?= (int)$produto['status'] === 0 ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-calculator"></i>Preços e margens</div>
                <div class="card-body-custom">
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Informe <b>custo</b> e <b>margem %</b> para calcular a venda automaticamente, ou informe
                        <b>custo</b> e <b>venda</b> para calcular a margem.
                    </div>
                    <div class="row g-3">
                        <div class="col-6 col-md-2 pb-3">
                            <label class="form-label obrigatorio" for="preco_custo">Preço de custo</label>
                            <input type="text" class="form-control" id="preco_custo" name="preco_custo" data-moeda value="<?= e($produto['preco_custo']) ?>" required>
                        </div>
                        <div class="col-6 col-md-2 pb-3">
                            <label class="form-label" for="margem_lucro">Margem (%)</label>
                            <input type="text" class="form-control" id="margem_lucro" name="margem_lucro" data-mask="moeda" data-decimais="2" value="<?= e($produto['margem_lucro']) ?>" placeholder="33,33">
                        </div>
                        <div class="col-6 col-md-2 pb-3">
                            <label class="form-label obrigatorio" for="preco_venda">Preço de venda</label>
                            <input type="text" class="form-control" id="preco_venda" name="preco_venda" data-moeda value="<?= e($produto['preco_venda']) ?>" required>
                        </div>
                        <div class="col-6 col-md-2 pb-3">
                            <label class="form-label" for="preco_promocional">Preço promocional</label>
                            <input type="text" class="form-control" id="preco_promocional" name="preco_promocional" data-moeda value="<?= e($produto['preco_promocional']) ?>">
                        </div>
                        <div class="col-12 col-md-4 pb-3">
                            <div class="bg-light rounded p-3 h-100">
                                <div class="small text-muted">Lucro por unidade</div>
                                <div class="fs-4 fw-bold" id="lucroCalc">R$ 0,00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-archive"></i>Estoque</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="estoque_atual">Estoque atual</label>
                            <input type="text" class="form-control" id="estoque_atual" name="estoque_atual" data-qtde value="<?= e($produto['estoque_atual']) ?>" <?= $edicao ? 'readonly class="form-control bg-light"' : '' ?>>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="estoque_minimo">Estoque mínimo</label>
                            <input type="text" class="form-control" id="estoque_minimo" name="estoque_minimo" data-qtde value="<?= e($produto['estoque_minimo']) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="estoque_maximo">Estoque máximo</label>
                            <input type="text" class="form-control" id="estoque_maximo" name="estoque_maximo" data-qtde value="<?= e($produto['estoque_maximo']) ?>">
                        </div>
                        <div class="col-6 col-md-3 d-flex align-items-end">
                            <?php if ($edicao && tem_permissao('estoque_ajuste')): ?>
                            <a href="<?= url('estoque/ajuste.php?produto=' . $id) ?>" class="btn btn-soft w-100"><i class="bi bi-arrow-repeat me-1"></i>Ajustar estoque</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="text-muted">O estoque é movimentado pelos módulos de <b>entrada/saída/ajuste</b>, vendas e compras — nunca diretamente aqui.</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-image"></i>Foto do produto</div>
                <div class="card-body-custom text-center">
                    <?php if ($edicao && $produto['foto'] && file_exists(BASE_PATH . '/' . $produto['foto'])): ?>
                    <img src="<?= url($produto['foto']) ?>" id="previewFoto" class="rounded mb-2 object-fit-cover" style="width:140px;height:140px" alt="Foto atual">
                    <?php else: ?>
                    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" id="previewFoto" class="rounded mb-2 border" style="width:140px;height:140px;object-fit:cover" alt="">
                    <?php endif; ?>
                    <input type="file" class="form-control form-control-sm" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted d-block mt-2">JPG, PNG ou WEBP até 2MB.</small>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom">
                    <i class="bi bi-qr-code"></i>Códigos adicionais
                    <button type="button" class="btn btn-sm btn-soft ms-auto" id="btnAddCodigo"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="card-body-custom" id="listaCodigos">
                    <?php foreach ($codigosExtra as $ce): ?>
                    <div class="input-group input-group-sm mb-2 codigo-linha">
                        <input type="text" class="form-control" name="codigos_extra[]" value="<?= e($ce['codigo']) ?>" placeholder="Código" maxlength="30">
                        <select class="form-select w-auto" name="codigos_extra_tipo[]">
                            <option value="BARRAS" <?= $ce['tipo'] === 'BARRAS' ? 'selected' : '' ?>>Barras</option>
                            <option value="INTERNO" <?= $ce['tipo'] === 'INTERNO' ? 'selected' : '' ?>>Interno</option>
                        </select>
                        <button type="button" class="btn btn-outline-danger remover-codigo"><i class="bi bi-x"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-body-custom pt-0">
                    <small class="text-muted">Códigos adicionais permitem busca e leitura de código de barras alternativos.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Salvar produto</button>
        <a href="<?= url('produtos/index.php') ?>" class="btn btn-light">Cancelar</a>
    </div>
</form>

<script>
    $(function () {
        const $custo = $('#preco_custo'), $margem = $('#margem_lucro'), $venda = $('#preco_venda'), $promo = $('#preco_promocional');

        function atualizarLucro() {
            const custo = APP.paraNumero($custo.val());
            const venda = APP.paraNumero($venda.val());
            $('#lucroCalc').text(APP.fmtMoeda(venda - custo));
        }

        function calcularPorCustoMargem() {
            const custo = APP.paraNumero($custo.val());
            const pct = APP.paraNumero($margem.val());
            if (custo > 0 && pct > 0 && pct < 100) {
                const venda = custo / (1 - pct / 100);
                $venda.val(APP.fmtNumero(venda, 2));
                atualizarLucro();
            }
        }

        function calcularMargemPorVenda() {
            const custo = APP.paraNumero($custo.val());
            const venda = APP.paraNumero($venda.val());
            if (custo > 0 && venda > 0) {
                const margem = ((venda - custo) / venda) * 100;
                $margem.val(APP.fmtNumero(margem, 2));
            }
            atualizarLucro();
        }

        $custo.on('blur', function () {
            $custo.val(APP.fmtNumero(APP.paraNumero(this.value), 2));
            calcularPorCustoMargem();
        });
        $margem.on('blur', function () { calcularPorCustoMargem(); });
        $venda.on('blur', function () {
            $venda.val(APP.fmtNumero(APP.paraNumero(this.value), 2));
            calcularMargemPorVenda();
        });
        $promo.on('blur', function () { $promo.val(APP.fmtNumero(APP.paraNumero(this.value), 2)); });
        atualizarLucro();

        // Subcategoria dependente da categoria
        function filtrarSubs() {
            const cat = $('#categoria_id').val();
            $('#subcategoria_id option').each(function () {
                const ok = !$(this).val() || $(this).data('cat') == cat;
                $(this).toggle(ok);
            });
        }
        $('#categoria_id').on('change', filtrarSubs);
        filtrarSubs();

        // Códigos adicionais
        function linhaCodigo(codigo, tipo) {
            return '<div class="input-group input-group-sm mb-2 codigo-linha">' +
                '<input type="text" class="form-control" name="codigos_extra[]" value="' + (codigo || '') + '" placeholder="Código" maxlength="30">' +
                '<select class="form-select w-auto" name="codigos_extra_tipo[]">' +
                '<option value="BARRAS"' + (tipo !== 'INTERNO' ? ' selected' : '') + '>Barras</option>' +
                '<option value="INTERNO"' + (tipo === 'INTERNO' ? ' selected' : '') + '>Interno</option></select>' +
                '<button type="button" class="btn btn-outline-danger remover-codigo"><i class="bi bi-x"></i></button></div>';
        }
        $('#btnAddCodigo').on('click', () => $('#listaCodigos').append(linhaCodigo()));
        $('#listaCodigos').on('click', '.remover-codigo', function () { $(this).closest('.codigo-linha').remove(); });

        // Preview da foto
        $('#foto').on('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => $('#previewFoto').attr('src', e.target.result);
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
</script>
<?php include INC . 'footer.php'; ?>