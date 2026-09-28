<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('produtos/index.php');
}
exigir_csrf();

$acao = $_POST['acao'] ?? '';
$pdo = db();

switch ($acao) {

    case 'salvar_produto':
        if (empty($_POST['id'])) {
            exigir_permissao('produtos_criar');
        } else {
            exigir_permissao('produtos_editar');
        }

        $id = (int)($_POST['id'] ?? 0);
        $edicao = $id > 0;

        $codigo = maiusculas($_POST['codigo'] ?? '');
        $barras = maiusculas($_POST['codigo_barras'] ?? '');
        $descricao = maiusculas($_POST['descricao'] ?? '');
        $descComp = maiusculas($_POST['descricao_complementar'] ?? '');
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $subcategoriaId = (int)($_POST['subcategoria_id'] ?? 0);
        $marcaId = (int)($_POST['marca_id'] ?? 0);
        $unidadeId = (int)($_POST['unidade_id'] ?? 0);
        $ncm = maiusculas($_POST['ncm'] ?? '');
        $cest = maiusculas($_POST['cest'] ?? '');
        $cfop = maiusculas($_POST['cfop'] ?? '');
        $custo = parse_decimal($_POST['preco_custo'] ?? '0');
        $venda = parse_decimal($_POST['preco_venda'] ?? '0');
        $promo = ($_POST['preco_promocional'] ?? '') !== '' ? parse_decimal($_POST['preco_promocional']) : null;
        $margem = parse_decimal($_POST['margem_lucro'] ?? '0');
        $estMin = parse_decimal($_POST['estoque_minimo'] ?? '0');
        $estMax = parse_decimal($_POST['estoque_maximo'] ?? '0');
        $localizacao = maiusculas($_POST['localizacao'] ?? '');
        $fornecedorId = (int)($_POST['fornecedor_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);

        if ($descricao === '') {
            flash('danger', 'Informe a descrição do produto.');
            voltar();
        }
        if ($custo < 0 || $venda < 0) {
            flash('danger', 'Preços inválidos.');
            voltar();
        }

        try {
            // Unicidade de códigos
            if ($barras !== '') {
                $stmt = $pdo->prepare(
                    'SELECT id FROM produtos WHERE codigo_barras = ? AND id <> ?
                      UNION SELECT pc.produto_id FROM produto_codigos pc WHERE pc.codigo = ? AND pc.produto_id <> ? LIMIT 1'
                );
                $stmt->execute([$barras, $id, $barras, $id]);
                if ($stmt->fetch()) {
                    flash('danger', 'Já existe um produto com este código de barras.');
                    voltar();
                }
            }
            $codigosExtra = array_map('maiusculas', array_values(array_filter((array)($_POST['codigos_extra'] ?? []), fn($c) => sanear($c) !== '')));
            $tiposExtra = (array)($_POST['codigos_extra_tipo'] ?? []);
            foreach ($codigosExtra as $ce) {
                $stmt = $pdo->prepare(
                    'SELECT id FROM produtos WHERE codigo_barras = ? AND id <> ?
                      UNION SELECT pc.produto_id FROM produto_codigos pc WHERE pc.codigo = ? AND pc.produto_id <> ? LIMIT 1'
                );
                $stmt->execute([$ce, $id, $ce, $id]);
                if ($stmt->fetch()) {
                    flash('danger', 'Um dos códigos adicionais já está em uso.');
                    voltar();
                }
            }

            // Foto
            $fotoAtual = null;
            if ($edicao) {
                $atual = buscar_linha('produtos', $id);
                $fotoAtual = $atual['foto'] ?? null;
            }
            $fotoSalva = $fotoAtual;
            if (!empty($_FILES['foto']['name'])) {
                $arquivo = $_FILES['foto'];
                $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                if (!in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    flash('danger', 'Formato de imagem inválido.');
                    voltar();
                }
                if ($arquivo['size'] > 2 * 1024 * 1024) {
                    flash('danger', 'A imagem deve ter no máximo 2MB.');
                    voltar();
                }
                $pastaLarga = BASE_PATH . '/assets/img/produtos';
                if (!is_dir($pastaLarga)) {
                    mkdir($pastaLarga, 0777, true);
                }
                $nomeArq = 'prod_' . ($edicao ? $id : 'temp') . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
                if (!move_uploaded_file($arquivo['tmp_name'], $pastaLarga . '/' . $nomeArq)) {
                    flash('danger', 'Não foi possível enviar a imagem.');
                    voltar();
                }
                $fotoSalva = 'assets/img/produtos/' . $nomeArq;
                if ($edicao && $fotoAtual && $fotoAtual !== $fotoSalva && file_exists(BASE_PATH . '/' . $fotoAtual)) {
                    @unlink(BASE_PATH . '/' . $fotoAtual);
                }
            }
            if (!$edicao) {
                $fotoSalva = null; // foto é aplicada após inserir (nome com id)
                if (!empty($_FILES['foto']['tmp_name'])) {
                    $tempFoto = $_FILES['foto'];
                    $tempExt = strtolower(pathinfo($tempFoto['name'], PATHINFO_EXTENSION));
                    $_SESSION['foto_temp'] = ['tmp' => $tempFoto['tmp_name'], 'ext' => $tempExt, 'size' => $tempFoto['size']];
                }
            }

            if ($edicao) {
                $antes = buscar_linha('produtos', $id);
                $stmt = $pdo->prepare(
                    'UPDATE produtos SET codigo_barras=?, descricao=?, descricao_complementar=?, categoria_id=?, subcategoria_id=?,
                            marca_id=?, unidade_id=?, ncm=?, cest=?, cfop=?, preco_custo=?, preco_venda=?, preco_promocional=?,
                            margem_lucro=?, estoque_minimo=?, estoque_maximo=?, localizacao=?, fornecedor_id=?, foto=?,
                            status=?, atualizado_em=NOW()
                      WHERE id=?'
                );
                $stmt->execute([$barras, $descricao, $descComp, $categoriaId ?: null, $subcategoriaId ?: null,
                    $marcaId ?: null, $unidadeId ?: null, $ncm, $cest, $cfop, $custo, $venda, $promo,
                    $margem, $estMin, $estMax, $localizacao, $fornecedorId ?: null, $fotoSalva, $status, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO produtos (codigo, codigo_barras, descricao, descricao_complementar, categoria_id, subcategoria_id,
                            marca_id, unidade_id, ncm, cest, cfop, preco_custo, preco_venda, preco_promocional, margem_lucro,
                            estoque_atual, estoque_minimo, estoque_maximo, localizacao, fornecedor_id, status, criado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$codigo ?: null, $barras, $descricao, $descComp, $categoriaId ?: null, $subcategoriaId ?: null,
                    $marcaId ?: null, $unidadeId ?: null, $ncm, $cest, $cfop, $custo, $venda, $promo, $margem,
                    $estMin, $estMax, $localizacao, $fornecedorId ?: null, $status]);
                $id = (int)$pdo->lastInsertId();
                if ($codigo === '') {
                    $pdo->prepare("UPDATE produtos SET codigo = CONCAT('P', LPAD(?, 5, '0')) WHERE id = ?")->execute([$id, $id]);
                }
                if (!empty($_SESSION['foto_temp'])) {
                    $t = $_SESSION['foto_temp'];
                    $nomeArq = 'prod_' . $id . '_' . bin2hex(random_bytes(4)) . '.' . $t['ext'];
                    if (move_uploaded_file($t['tmp'], BASE_PATH . '/assets/img/produtos/' . $nomeArq)) {
                        $pdo->prepare('UPDATE produtos SET foto = ? WHERE id = ?')->execute(['assets/img/produtos/' . $nomeArq, $id]);
                    }
                    unset($_SESSION['foto_temp']);
                }
            }

            // Códigos adicionais
            $pdo->prepare('DELETE FROM produto_codigos WHERE produto_id = ?')->execute([$id]);
            if ($codigosExtra) {
                $stmt = $pdo->prepare('INSERT INTO produto_codigos (produto_id, codigo, tipo) VALUES (?, ?, ?)');
                foreach ($codigosExtra as $i => $ce) {
                    $tipo = (($tiposExtra[$i] ?? '') === 'INTERNO') ? 'INTERNO' : 'BARRAS';
                    $stmt->execute([$id, $ce, $tipo]);
                }
            }

            registrar_log('produtos', ($edicao ? 'Produto editado' : 'Produto criado') . ' #' . $id, $id, $antes ?? null, [
                'descricao' => $descricao, 'custo' => $custo, 'venda' => $venda,
            ]);
            flash('success', 'Produto salvo com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_produto');
            flash('danger', 'Não foi possível salvar o produto. Verifique os dados informados.');
        }
        redirecionar('produtos/index.php');
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('produtos/index.php');
}