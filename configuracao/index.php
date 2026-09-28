<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('config_ver');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_permissao('config_editar');
    exigir_csrf();

    $campos = [
        'empresa_nome', 'empresa_cnpj', 'empresa_endereco', 'empresa_telefone', 'empresa_email',
        'consumo_nome', 'consumo_producao_nome',
        'moeda_simbolo', 'casas_decimais', 'estoque_negativo', 'venda_exige_cliente',
        'atualizar_custo_compra', 'juros_padrao', 'multa_padrao', 'dias_vencimento', 'nota_rodape_venda',
        'venda_tipo_pedido_padrao', 'compra_tipo_pedido_padrao',
        'venda_forma_pagamento_padrao', 'compra_forma_pagamento_padrao',
    ];

    $stmt = db()->prepare(
        'INSERT INTO configs (chave, valor, atualizada_em) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizada_em = NOW()'
    );

    foreach ($campos as $campo) {
        $valor = sanear($_POST[$campo] ?? '');
        if (in_array($campo, ['estoque_negativo', 'venda_exige_cliente', 'atualizar_custo_compra'], true)) {
            $valor = isset($_POST[$campo]) ? '1' : '0';
        }
        $stmt->execute([$campo, $valor]);
    }

    registrar_log('config', 'Configurações atualizadas');
    flash('success', 'Configurações salvas com sucesso.');
    redirecionar('configuracao/index.php');
}

$tituloPagina = 'Configurações';
include INC . 'header.php';

$tiposVenda = db()->query("SELECT id, nome FROM tipos_pedido WHERE modulo = 'VENDA' AND ativo = 1 ORDER BY nome")->fetchAll();
$tiposCompra = db()->query("SELECT id, nome FROM tipos_pedido WHERE modulo = 'COMPRA' AND ativo = 1 ORDER BY nome")->fetchAll();
$formas = db()->query('SELECT id, nome FROM formas_pagamento WHERE ativo = 1 ORDER BY nome')->fetchAll();
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-gear me-2"></i>Configurações</h1>
        <span class="subtitulo">Empresa e regras do sistema</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <form method="post" class="js-converte">
            <?= csrf_field() ?>
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-building"></i>Dados da empresa</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Razão social / Nome da empresa</label>
                            <input type="text" class="form-control" name="empresa_nome" value="<?= e(obter_config('empresa_nome', 'Minha Empresa LTDA')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">CNPJ</label>
                            <input type="text" class="form-control" name="empresa_cnpj" data-mask="cpfCnpj" value="<?= e(obter_config('empresa_cnpj', '')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Telefone</label>
                            <input type="text" class="form-control" name="empresa_telefone" data-mask="celular" value="<?= e(obter_config('empresa_telefone', '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Endereço</label>
                            <input type="text" class="form-control" name="empresa_endereco" value="<?= e(obter_config('empresa_endereco', '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" name="empresa_email" value="<?= e(obter_config('empresa_email', '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nome do módulo de consumo</label>
                            <input type="text" class="form-control" name="consumo_nome" maxlength="40"
                                   placeholder="Consumo" value="<?= e(obter_config('consumo_nome', 'Consumo')) ?>">
                            <small class="form-text text-muted">Aparece no menu, nos títulos e nos relatórios. Use o nome do seu estabelecimento: Consumo, Pedidos, Atendimento, Bar…</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nome da fila de produção</label>
                            <input type="text" class="form-control" name="consumo_producao_nome" maxlength="40"
                                   placeholder="Produção" value="<?= e(obter_config('consumo_producao_nome', 'Produção')) ?>">
                            <small class="form-text text-muted">Cabeçalho da fila de preparo, no menu e nos títulos: Produção, Atendimento, Enfermagem, Oficina…</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-sliders"></i>Regras operacionais</div>
                <div class="card-body-custom">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="estoque_negativo" id="cfgNeg" value="1" <?= obter_config('estoque_negativo', '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cfgNeg">Permitir estoque negativo</label>
                        <div class="small text-muted">Quando desativado, saídas acima do saldo são bloqueadas.</div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="venda_exige_cliente" id="cfgCli" value="1" <?= obter_config('venda_exige_cliente', '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cfgCli">Exigir cliente na venda</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="atualizar_custo_compra" id="cfgCusto" value="1" <?= obter_config('atualizar_custo_compra', '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cfgCusto">Atualizar custo do produto na compra</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label">Dias de vencimento</label>
                            <input type="text" class="form-control" name="dias_vencimento" data-mask="int" value="<?= e(obter_config('dias_vencimento', '30')) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Juros padrão (%)</label>
                            <input type="text" class="form-control" name="juros_padrao" data-moeda value="<?= e(obter_config('juros_padrao', '1')) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Multa padrão (%)</label>
                            <input type="text" class="form-control" name="multa_padrao" data-moeda value="<?= e(obter_config('multa_padrao', '2')) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Casas decimais</label>
                            <select class="form-select" name="casas_decimais">
                                <?php for ($i = 0; $i <= 4; $i++): ?>
                                <option value="<?= $i ?>" <?= (string)obter_config('casas_decimais', '2') === (string)$i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Símbolo da moeda</label>
                            <input type="text" class="form-control" name="moeda_simbolo" value="<?= e(obter_config('moeda_simbolo', 'R$')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Rodapé da venda (impressão)</label>
                            <textarea class="form-control" name="nota_rodape_venda" rows="2" maxlength="500" placeholder="Ex.: Obrigado pela preferência!"><?= e(obter_config('nota_rodape_venda', '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-tag"></i>Padrões de pedido</div>
                <div class="card-body-custom">
                    <p class="small text-muted mb-3">Defina os padrões que virão selecionados ao abrir uma nova venda ou compra.</p>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Tipo de pedido padrão (venda)</label>
                            <select class="form-select" name="venda_tipo_pedido_padrao">
                                <option value="">Sem padrão (primeiro da lista)</option>
                                <?php foreach ($tiposVenda as $tp): ?>
                                <option value="<?= (int)$tp['id'] ?>" <?= (string)obter_config('venda_tipo_pedido_padrao', '') === (string)$tp['id'] ? 'selected' : '' ?>><?= e($tp['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Tipo de pedido padrão (compra)</label>
                            <select class="form-select" name="compra_tipo_pedido_padrao">
                                <option value="">Sem padrão (primeiro da lista)</option>
                                <?php foreach ($tiposCompra as $tp): ?>
                                <option value="<?= (int)$tp['id'] ?>" <?= (string)obter_config('compra_tipo_pedido_padrao', '') === (string)$tp['id'] ? 'selected' : '' ?>><?= e($tp['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Forma de pagamento padrão (venda)</label>
                            <select class="form-select" name="venda_forma_pagamento_padrao">
                                <option value="">Sem padrão (primeiro da lista)</option>
                                <?php foreach ($formas as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= (string)obter_config('venda_forma_pagamento_padrao', '') === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Forma de pagamento padrão (compra)</label>
                            <select class="form-select" name="compra_forma_pagamento_padrao">
                                <option value="">Sem padrão (primeiro da lista)</option>
                                <?php foreach ($formas as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= (string)obter_config('compra_forma_pagamento_padrao', '') === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (tem_permissao('config_editar')): ?>
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Salvar configurações</button>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Coluna Lateral: Backup & Segurança -->
    <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-shield-lock"></i>Cópia de Segurança (Backup)</div>
                <div class="card-body-custom">
                    <p class="small text-muted mb-3">
                        Gere um arquivo <code>.sql</code> completo contendo a estrutura de tabelas e todos os registros do sistema (clientes, produtos, vendas, financeiro e movimentações de estoque).
                    </p>
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-database me-1 text-primary"></i>
                        Banco de dados: <b><?= e(DB_NAME) ?></b>
                    </div>
                    <?php if ((int)($usuarioLogado['is_admin'] ?? 0) === 1 || tem_permissao('config_ver')): ?>
                    <form action="<?= url('configuracao/backup.php') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-primary w-100 py-2">
                            <i class="bi bi-database-down me-1"></i>Baixar Backup do Banco de Dados (.sql)
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-custom"><i class="bi bi-info-circle"></i>Informações do Sistema</div>
                <div class="card-body-custom small">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Versão do Sistema</span>
                        <b>1.1.0 (Comercial Pro)</b>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Versão do PHP</span>
                        <b><?= PHP_VERSION ?></b>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Servidor</span>
                        <b><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?></b>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Fuso Horário</span>
                        <b><?= date_default_timezone_get() ?></b>
                    </div>
                </div>
            </div>
        </div>
</div>
<?php include INC . 'footer.php'; ?>