<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
exigir_login();
exigir_permissao('produtos_ver');

$pdo = db();
$produtoId = (int)($_GET['id'] ?? 0);

$produtos = $pdo->query(
    'SELECT p.id, p.codigo, p.codigo_barras, p.descricao, p.preco_venda, u.sigla AS unidade
       FROM produtos p
       LEFT JOIN unidades u ON u.id = p.unidade_id
      WHERE p.status = 1
      ORDER BY p.descricao'
)->fetchAll();

$empresaNome = obter_config('empresa_nome', 'Gestor Comercial');

$tituloPagina = 'Impressão de Etiquetas';
include INC . 'header.php';
?>
<div class="page-header print-hide">
    <div>
        <h1><i class="bi bi-upc-scan me-2"></i>Gerador de Etiquetas</h1>
        <span class="subtitulo">Impressão de etiquetas de gôndola e produtos com código de barras</span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir Etiquetas</button>
        <a href="<?= url('produtos/index.php') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>
</div>

<div class="card mb-3 print-hide">
    <div class="card-header-custom"><i class="bi bi-sliders"></i>Filtros e Configurações de Impressão</div>
    <div class="card-body-custom">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label">Selecionar Produto</label>
                <select class="form-select" id="selProduto">
                    <option value="">-- Todos os produtos (ou escolha um) --</option>
                    <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $produtoId === (int)$p['id'] ? 'selected' : '' ?>
                            data-desc="<?= e($p['descricao']) ?>"
                            data-barras="<?= e($p['codigo_barras'] ?: ($p['codigo'] ?: str_pad((string)$p['id'], 8, '0', STR_PAD_LEFT))) ?>"
                            data-preco="<?= formatar_moeda($p['preco_venda']) ?>"
                            data-un="<?= e($p['unidade'] ?? 'UN') ?>">
                        <?= e($p['descricao']) ?> (<?= e($p['codigo'] ?: $p['codigo_barras'] ?: '#' . $p['id']) ?>) - <?= formatar_moeda($p['preco_venda']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Cópias por produto</label>
                <input type="number" class="form-control" id="numCopias" min="1" max="100" value="4">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Tamanho da Etiqueta</label>
                <select class="form-select" id="tamEtiqueta">
                    <option value="padrao">Padrão Gôndola (3 colunas)</option>
                    <option value="compacta">Compacta / Jóias (4 colunas)</option>
                    <option value="grande">Grande / Caixa (2 colunas)</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="button" class="btn btn-soft w-100" id="btnGerar"><i class="bi bi-arrow-repeat me-1"></i>Atualizar</button>
            </div>
        </div>
    </div>
</div>

<!-- Área de Impressão das Etiquetas -->
<div id="folhaEtiquetas" class="grade-etiquetas padrao"></div>

<style>
    .grade-etiquetas {
        display: grid;
        gap: 10px;
        background: transparent;
    }
    .grade-etiquetas.padrao {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    .grade-etiquetas.compacta {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }
    .grade-etiquetas.grande {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
    .etiqueta-item {
        background: #fff;
        color: #000;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 8px 10px;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        page-break-inside: avoid;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    .etiqueta-empresa {
        font-size: 0.68rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .etiqueta-desc {
        font-size: 0.85rem;
        font-weight: 700;
        line-height: 1.15;
        margin: 3px 0;
        max-height: 2.3em;
        overflow: hidden;
    }
    .etiqueta-barcode svg {
        width: 100%;
        max-height: 44px;
    }
    .etiqueta-preco {
        font-size: 1.15rem;
        font-weight: 800;
        color: #0f172a;
        margin-top: 2px;
    }

    @media print {
        body { background: #fff !important; }
        .page-header, .topbar, .sidebar, .print-hide, .app-header { display: none !important; }
        .app-wrapper { margin: 0 !important; padding: 0 !important; }
        .app-main { padding: 0 !important; }
        .grade-etiquetas {
            gap: 4mm !important;
        }
        .grade-etiquetas.padrao {
            grid-template-columns: repeat(3, 1fr) !important;
        }
        .grade-etiquetas.compacta {
            grid-template-columns: repeat(4, 1fr) !important;
        }
        .grade-etiquetas.grande {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .etiqueta-item {
            border: 1px solid #ccc !important;
            box-shadow: none !important;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    const PRODUTOS = <?= json_encode(array_map(static fn($p) => [
        'id' => (int)$p['id'],
        'desc' => (string)$p['descricao'],
        'barras' => (string)($p['codigo_barras'] ?: ($p['codigo'] ?: str_pad((string)$p['id'], 8, '0', STR_PAD_LEFT))),
        'preco' => formatar_moeda($p['preco_venda']),
        'un' => (string)($p['unidade'] ?? 'UN')
    ], $produtos), JSON_UNESCAPED_UNICODE) ?>;
    const EMPRESA = <?= json_encode($empresaNome, JSON_UNESCAPED_UNICODE) ?>;

    function renderizarEtiquetas() {
        const selId = $('#selProduto').val();
        const copias = Math.max(parseInt($('#numCopias').val(), 10) || 1, 1);
        const tam = $('#tamEtiqueta').val();
        const $folha = $('#folhaEtiquetas');

        $folha.removeClass('padrao compacta grande').addClass(tam).empty();

        let lista = [];
        if (selId) {
            const p = PRODUTOS.find(x => x.id == selId);
            if (p) {
                for (let i = 0; i < copias; i++) lista.push(p);
            }
        } else {
            PRODUTOS.forEach(p => {
                for (let i = 0; i < copias; i++) lista.push(p);
            });
        }

        if (!lista.length) {
            $folha.html('<div class="alert alert-info print-hide">Nenhum produto selecionado para gerar etiquetas.</div>');
            return;
        }

        lista.forEach((item, idx) => {
            const svgId = 'barcode_' + idx;
            const $card = $(
                '<div class="etiqueta-item">' +
                '  <div class="etiqueta-empresa">' + APP.esc(EMPRESA) + '</div>' +
                '  <div class="etiqueta-desc">' + APP.esc(item.desc) + '</div>' +
                '  <div class="etiqueta-barcode"><svg id="' + svgId + '"></svg></div>' +
                '  <div class="etiqueta-preco">' + item.preco + ' <span style="font-size:0.75rem;font-weight:normal">/' + APP.esc(item.un) + '</span></div>' +
                '</div>'
            );
            $folha.append($card);

            try {
                JsBarcode('#' + svgId, item.barras, {
                    format: item.barras.length === 13 && /^\d+$/.test(item.barras) ? "EAN13" : "CODE128",
                    width: 1.4,
                    height: 36,
                    fontSize: 10,
                    margin: 0,
                    displayValue: true
                });
            } catch (err) {
                // Fallback para CODE128 genérico
                try {
                    JsBarcode('#' + svgId, item.barras, { format: "CODE128", width: 1.2, height: 32, fontSize: 10, margin: 0 });
                } catch(e) {}
            }
        });
    }

    $(function() {
        renderizarEtiquetas();
        $('#btnGerar, #selProduto, #tamEtiqueta').on('change click', function() {
            renderizarEtiquetas();
        });
    });
</script>
<?php include INC . 'footer.php'; ?>
