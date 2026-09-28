<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('clientes_ver');

$id = (int)($_GET['id'] ?? 0);
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('clientes_editar');
}

$cliente = [
    'id' => 0, 'codigo' => '', 'tipo' => 'FISICA', 'nome' => '', 'nome_fantasia' => '',
    'documento' => '', 'inscricao_estadual' => '', 'nascimento' => '', 'email' => '',
    'telefone' => '', 'celular' => '', 'cep' => '', 'endereco' => '', 'numero' => '',
    'complemento' => '', 'bairro' => '', 'cidade' => '', 'estado' => '',
    'observacoes' => '', 'status' => 1,
];

if ($edicao) {
    $linha = buscar_linha('clientes', $id);
    if (!$linha) {
        flash('danger', 'Cliente não encontrado.');
        redirecionar('clientes/index.php');
    }
    $cliente = $linha;
}

$tituloPagina = $edicao ? 'Editar cliente' : 'Novo cliente';
include INC . 'header.php';
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-person-plus-fill me-2"></i><?= $edicao ? 'Editar cliente' : 'Novo cliente' ?></h1>
        <span class="subtitulo"><?= e($cliente['codigo']) ? 'Código: ' . e($cliente['codigo']) : 'Cadastro de novo cliente' ?></span>
    </div>
    <a href="<?= url('clientes/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

<form method="post" action="<?= url('clientes/salvar.php') ?>" class="js-converte" id="formCliente">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_cliente">
    <input type="hidden" name="id" value="<?= (int)$cliente['id'] ?>">

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-person-bounding-box"></i>Identificação</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="tipo">Tipo</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="FISICA" <?= $cliente['tipo'] === 'FISICA' ? 'selected' : '' ?>>Pessoa Física</option>
                                <option value="JURIDICA" <?= $cliente['tipo'] === 'JURIDICA' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-9">
                            <label class="form-label obrigatorio" id="lblNome" for="nome">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= e($cliente['nome']) ?>" required maxlength="150" autofocus>
                        </div>
                        <div class="col-12 col-md-6 div-fantasia">
                            <label class="form-label" for="nome_fantasia">Nome fantasia</label>
                            <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="<?= e($cliente['nome_fantasia']) ?>" maxlength="150">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" id="lblDoc" for="documento">CPF</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="documento" name="documento" data-mask="cpfCnpj" value="<?= e($cliente['documento']) ?>" maxlength="18" placeholder="000.000.000-00">
                                <button type="button" class="btn btn-outline-secondary d-none" id="btnBuscarCnpj" title="Consultar dados da empresa na Receita Federal">
                                    <i class="bi bi-search me-1"></i>Buscar CNPJ
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="inscricao_estadual">Inscrição estadual / RG</label>
                            <input type="text" class="form-control" id="inscricao_estadual" name="inscricao_estadual" value="<?= e($cliente['inscricao_estadual']) ?>" maxlength="30">
                        </div>
                        <div class="col-12 col-md-6 div-nascimento">
                            <label class="form-label" for="nascimento">Data de nascimento</label>
                            <input type="text" class="form-control" id="nascimento" name="nascimento" data-mask="data" value="<?= e($cliente['nascimento'] ? formatar_data($cliente['nascimento']) : '') ?>" maxlength="10" placeholder="dd/mm/aaaa">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-envelope"></i>Contato</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($cliente['email']) ?>" maxlength="120">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="telefone">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" data-mask="telefone" value="<?= e($cliente['telefone']) ?>" maxlength="15">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="celular">Celular</label>
                            <input type="text" class="form-control" id="celular" name="celular" data-mask="celular" value="<?= e($cliente['celular']) ?>" maxlength="16">
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
                                <input type="text" class="form-control" id="cep" name="cep" data-mask="cep" value="<?= e($cliente['cep']) ?>" maxlength="9" placeholder="00000-000">
                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarCep" title="Buscar CEP"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="endereco">Endereço</label>
                            <input type="text" class="form-control" id="endereco" name="endereco" value="<?= e($cliente['endereco']) ?>" maxlength="150">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="numero">Número</label>
                            <input type="text" class="form-control" id="numero" name="numero" value="<?= e($cliente['numero']) ?>" maxlength="15">
                        </div>
                        <div class="col-6 col-md-1 col-lg-1">
                            <label class="form-label" for="estado">UF</label>
                            <select class="form-select" id="estado" name="estado"><?= select_uf($cliente['estado']) ?></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="bairro">Bairro</label>
                            <input type="text" class="form-control" id="bairro" name="bairro" value="<?= e($cliente['bairro']) ?>" maxlength="80">
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="cidade">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" value="<?= e($cliente['cidade']) ?>" maxlength="80">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="complemento">Complemento</label>
                            <input type="text" class="form-control" id="complemento" name="complemento" value="<?= e($cliente['complemento']) ?>" maxlength="80">
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
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?= e($cliente['observacoes']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" <?= (int)$cliente['status'] === 1 ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= (int)$cliente['status'] === 0 ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <?php if ($edicao): ?>
                    <div class="small text-muted">
                        Cadastrado em <b><?= formatar_datahora($cliente['criado_em']) ?></b>.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Salvar cliente</button>
                <a href="<?= url('clientes/index.php') ?>" class="btn btn-light">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<script>
    function toggleTipo() {
        const t = $('#tipo').val();
        $('#lblNome').text(t === 'JURIDICA' ? 'Razão social' : 'Nome');
        $('#lblDoc').text(t === 'JURIDICA' ? 'CNPJ' : 'CPF');
        $('#documento').attr('placeholder', t === 'JURIDICA' ? '00.000.000/0000-00' : '000.000.000-00');
        $('.div-fantasia').toggle(t === 'JURIDICA');
        $('.div-nascimento').toggle(t === 'FISICA');
        $('#btnBuscarCnpj').toggleClass('d-none', t !== 'JURIDICA');
    }
    $('#tipo').on('change', toggleTipo);
    toggleTipo();

    $('#btnBuscarCnpj').on('click', function () {
        const cnpj = $('#documento').val().replace(/\D/g, '');
        if (cnpj.length !== 14) return APP.toast('warning', 'Informe um CNPJ válido com 14 dígitos.');
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Buscando...');

        APP.ajax(APP.baseUrl + '/api/cnpj.php', { cnpj: cnpj }, function (res) {
            $btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>Buscar CNPJ');
            if (res.dados) {
                const d = res.dados;
                if (d.razao_social && !$('#nome').val()) $('#nome').val(d.razao_social);
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
            $btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>Buscar CNPJ');
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