<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('fornecedores_ver');

$id = (int)($_GET['id'] ?? 0);
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('fornecedores_editar');
}

$fornecedor = [
    'id' => 0, 'codigo' => '', 'razao_social' => '', 'nome_fantasia' => '', 'documento' => '',
    'inscricao_estadual' => '', 'email' => '', 'telefone' => '', 'celular' => '', 'cep' => '',
    'endereco' => '', 'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '',
    'estado' => '', 'observacoes' => '', 'status' => 1,
];

if ($edicao) {
    $linha = buscar_linha('fornecedores', $id);
    if (!$linha) {
        flash('danger', 'Fornecedor não encontrado.');
        redirecionar('fornecedores/index.php');
    }
    $fornecedor = $linha;
}

$tituloPagina = $edicao ? 'Editar fornecedor' : 'Novo fornecedor';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-building-add me-2"></i><?= $edicao ? 'Editar fornecedor' : 'Novo fornecedor' ?></h1>
        <span class="subtitulo"><?= e($fornecedor['codigo']) ? 'Código: ' . e($fornecedor['codigo']) : 'Cadastro de novo fornecedor' ?></span>
    </div>
    <a href="<?= url('fornecedores/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('fornecedores/salvar.php') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_fornecedor">
    <input type="hidden" name="id" value="<?= (int)$fornecedor['id'] ?>">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-briefcase"></i>Identificação</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label obrigatorio" for="razao_social">Razão social</label>
                            <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?= e($fornecedor['razao_social']) ?>" required maxlength="150" autofocus>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="nome_fantasia">Nome fantasia</label>
                            <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="<?= e($fornecedor['nome_fantasia']) ?>" maxlength="150">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="documento">CNPJ</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="documento" name="documento" data-mask="cnpj" value="<?= e($fornecedor['documento']) ?>" maxlength="18" placeholder="00.000.000/0000-00">
                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarCnpjFornec" title="Buscar dados da empresa na Receita Federal">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="inscricao_estadual">Inscrição estadual</label>
                            <input type="text" class="form-control" id="inscricao_estadual" name="inscricao_estadual" value="<?= e($fornecedor['inscricao_estadual']) ?>" maxlength="30">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="email">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($fornecedor['email']) ?>" maxlength="120">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefone">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" data-mask="telefone" value="<?= e($fornecedor['telefone']) ?>" maxlength="15">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="celular">Celular</label>
                            <input type="text" class="form-control" id="celular" name="celular" data-mask="celular" value="<?= e($fornecedor['celular']) ?>" maxlength="16">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-geo-alt"></i>Endereço</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="cep">CEP</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="cep" name="cep" data-mask="cep" value="<?= e($fornecedor['cep']) ?>" maxlength="9" placeholder="00000-000">
                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarCep" title="Buscar CEP"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="endereco">Endereço</label>
                            <input type="text" class="form-control" id="endereco" name="endereco" value="<?= e($fornecedor['endereco']) ?>" maxlength="150">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="numero">Número</label>
                            <input type="text" class="form-control" id="numero" name="numero" value="<?= e($fornecedor['numero']) ?>" maxlength="15">
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label" for="estado">UF</label>
                            <select class="form-select" id="estado" name="estado"><?= select_uf($fornecedor['estado']) ?></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="bairro">Bairro</label>
                            <input type="text" class="form-control" id="bairro" name="bairro" value="<?= e($fornecedor['bairro']) ?>" maxlength="80">
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="cidade">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" value="<?= e($fornecedor['cidade']) ?>" maxlength="80">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="complemento">Complemento</label>
                            <input type="text" class="form-control" id="complemento" name="complemento" value="<?= e($fornecedor['complemento']) ?>" maxlength="80">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-sticky"></i>Observações e status</div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label" for="observacoes">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?= e($fornecedor['observacoes']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" <?= (int)$fornecedor['status'] === 1 ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= (int)$fornecedor['status'] === 0 ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <?php if ($edicao): ?>
                    <div class="small text-muted">Cadastrado em <b><?= formatar_datahora($fornecedor['criado_em']) ?></b></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Salvar fornecedor</button>
                <a href="<?= url('fornecedores/index.php') ?>" class="btn btn-light">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<script>
    $('#btnBuscarCnpjFornec').on('click', function () {
        const cnpj = $('#documento').val().replace(/\D/g, '');
        if (cnpj.length !== 14) return APP.toast('warning', 'Informe um CNPJ válido com 14 dígitos.');
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        APP.ajax(APP.baseUrl + '/api/cnpj.php', { cnpj: cnpj }, function (res) {
            $btn.prop('disabled', false).html('<i class="bi bi-search"></i>');
            if (res.dados) {
                const d = res.dados;
                if (d.razao_social && !$('#razao_social').val()) $('#razao_social').val(d.razao_social);
                if (d.nome_fantasia && !$('#nome_fantasia').val()) $('#nome_fantasia').val(d.nome_fantasia);
                if (d.cep) $('#cep').val(d.cep);
                if (d.logradouro) $('#endereco').val(d.logradouro);
                if (d.numero && !$('#numero').val()) $('#numero').val(d.numero);
                if (d.complemento && !$('#complemento').val()) $('#complemento').val(d.complemento);
                if (d.bairro) $('#bairro').val(d.bairro);
                if (d.cidade) $('#cidade').val(d.cidade);
                if (d.uf) $('#estado').val(d.uf);
                if (d.telefone && !$('#telefone').val()) $('#telefone').val(d.telefone);
                if (d.email && !$('#email').val()) $('#email').val(d.email);
                APP.toast('success', 'Dados do CNPJ importados com sucesso!');
            }
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="bi bi-search"></i>');
            APP.toast('error', res && res.msg ? res.msg : 'Não foi possível consultar o CNPJ.');
        });
    });

    $('#cep').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#btnBuscarCep').click(); }
    });

    $('#btnBuscarCep').on('click', function () {
        const cep = $('#cep').val().replace(/\D/g, '');
        if (cep.length !== 8) return APP.toast('warning', 'Informe um CEP válido.');
        APP.ajax(APP.baseUrl + '/api/cep.php', { cep: cep }, function (res) {
            $('#endereco').val(res.dados.logradouro || '');
            $('#bairro').val(res.dados.bairro || '');
            $('#cidade').val(res.dados.cidade || '');
            $('#estado').val(res.dados.uf || '');
            APP.toast('success', 'CEP localizado com sucesso!');
        }, function (res) {
            APP.toast('error', res && res.msg ? res.msg : 'CEP não encontrado.');
        });
    });
</script>
<?php include INC . 'footer.php'; ?>