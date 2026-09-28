<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('consumo/cardapio/index.php');
}
exigir_csrf();

$acao = (string)($_POST['acao'] ?? '');
$pdo = db();
$pastaFotos = BASE_PATH . '/assets/img/cardapio';

/**
 * Processa o upload da foto do item. O nome é aleatório, portanto não
 * depende do id; em caso de erro no INSERT o arquivo é removido.
 */
$processarFoto = static function (int $id, ?string $fotoAtual) use ($pastaFotos): ?string {
    if (!empty($_POST['remover_foto']) && $fotoAtual) {
        if (file_exists(BASE_PATH . '/' . $fotoAtual)) {
            @unlink(BASE_PATH . '/' . $fotoAtual);
        }
        return null;
    }
    if (empty($_FILES['foto']['name'])) {
        return $fotoAtual;
    }

    $arquivo = $_FILES['foto'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new RuntimeException('Formato de imagem inválido. Use JPG, PNG ou WEBP.');
    }
    if ($arquivo['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 2MB.');
    }
    if (!is_dir($pastaFotos) && !mkdir($pastaFotos, 0777, true) && !is_dir($pastaFotos)) {
        throw new RuntimeException('Pasta de imagens indisponível.');
    }

    $nomeArq = 'card_' . $id . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
    if (!move_uploaded_file($arquivo['tmp_name'], $pastaFotos . '/' . $nomeArq)) {
        throw new RuntimeException('Não foi possível enviar a imagem.');
    }
    if ($fotoAtual && file_exists(BASE_PATH . '/' . $fotoAtual)) {
        @unlink(BASE_PATH . '/' . $fotoAtual);
    }
    return 'assets/img/cardapio/' . $nomeArq;
};

switch ($acao) {

    case 'salvar_item':
        exigir_permissao('cardapio_editar');

        $id = (int)($_POST['id'] ?? 0);
        $edicao = $id > 0;
        $antes = $edicao ? cardapio_item($id) : null;
        if ($edicao && !$antes) {
            flash('danger', 'Item não encontrado.');
            redirecionar('consumo/cardapio/index.php');
        }

        $descricao = maiusculas($_POST['descricao'] ?? '');
        $descricaoComplementar = (string)($_POST['descricao_complementar'] ?? '');
        $codigo = maiusculas($_POST['codigo'] ?? '');
        $observacoes = (string)($_POST['observacoes'] ?? '');
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $preco = parse_decimal($_POST['preco'] ?? '0');
        $tempoPreparo = (int)($_POST['tempo_preparo'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

        if ($descricao === '') {
            flash('danger', 'Informe a descrição do item.');
            voltar();
        }
        if ($preco < 0) {
            flash('danger', 'Preço inválido.');
            voltar();
        }
        if ($tempoPreparo < 0 || $tempoPreparo > 9999) {
            flash('danger', 'Tempo de preparo inválido.');
            voltar();
        }

        $fotoSalva = null;
        try {
            if ($codigo !== '') {
                $stmt = $pdo->prepare('SELECT id FROM cardapio_itens WHERE codigo = ? AND id <> ? LIMIT 1');
                $stmt->execute([$codigo, $id]);
                if ($stmt->fetch()) {
                    flash('danger', 'Já existe um item com este código.');
                    voltar();
                }
            }

            if ($categoriaId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM cardapio_categorias WHERE id = ? LIMIT 1');
                $stmt->execute([$categoriaId]);
                if (!$stmt->fetch()) {
                    $categoriaId = 0;
                }
            }

            if ($edicao) {
                $fotoSalva = $processarFoto($id, (string)($antes['foto'] ?? ''));
                $stmt = $pdo->prepare(
                    'UPDATE cardapio_itens
                        SET categoria_id = ?, codigo = ?, descricao = ?, descricao_complementar = ?, preco = ?,
                            tempo_preparo = ?, observacoes = ?, foto = ?, ativo = ?, atualizado_em = NOW()
                      WHERE id = ?'
                );
                $stmt->execute([$categoriaId ?: null, $codigo ?: null, $descricao, $descricaoComplementar, $preco,
                    $tempoPreparo ?: null, $observacoes, $fotoSalva, $ativo, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO cardapio_itens
                        (categoria_id, codigo, descricao, descricao_complementar, preco, tempo_preparo,
                         observacoes, foto, ativo, criado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$categoriaId ?: null, $codigo ?: null, $descricao, $descricaoComplementar, $preco,
                    $tempoPreparo ?: null, $observacoes, null, $ativo]);
                $id = (int)$pdo->lastInsertId();
                $fotoSalva = $processarFoto($id, null);
                if ($fotoSalva !== null) {
                    $pdo->prepare('UPDATE cardapio_itens SET foto = ? WHERE id = ?')->execute([$fotoSalva, $id]);
                }
            }

            registrar_log('consumo', ($edicao ? 'Item de cardápio editado' : 'Item de cardápio criado') . ' #' . $id,
                $id, $antes, ['descricao' => $descricao, 'preco' => $preco, 'ativo' => $ativo]);
            flash('success', 'Item do cardápio salvo com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_item_cardapio');
            if ($id > 0 && !$edicao && $fotoSalva && file_exists(BASE_PATH . '/' . $fotoSalva)) {
                @unlink(BASE_PATH . '/' . $fotoSalva);
            }
            flash('danger', $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar o item.');
        }
        redirecionar('consumo/cardapio/index.php');
        break;

    case 'alternar_item':
        exigir_permissao('cardapio_editar');
        $id = (int)($_POST['id'] ?? 0);
        $antes = cardapio_item($id);
        if (!$antes) {
            flash('danger', 'Item não encontrado.');
            redirecionar('consumo/cardapio/index.php');
        }
        $novo = (int)$antes['ativo'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE cardapio_itens SET ativo = ?, atualizado_em = NOW() WHERE id = ?')->execute([$novo, $id]);
        registrar_log('consumo', 'Item de cardápio ' . ($novo === 1 ? 'ativado' : 'desativado') . ' #' . $id,
            $id, $antes, ['ativo' => $novo]);
        flash('success', 'Item ' . ($novo === 1 ? 'ativado' : 'desativado') . ' com sucesso!');
        redirecionar('consumo/cardapio/index.php');
        break;

    case 'excluir_item':
        exigir_permissao('cardapio_editar');
        $id = (int)($_POST['id'] ?? 0);
        $antes = cardapio_item($id);
        if (!$antes) {
            flash('danger', 'Item não encontrado.');
            redirecionar('consumo/cardapio/index.php');
        }
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM comanda_itens WHERE cardapio_item_id = ?');
            $stmt->execute([$id]);
            $usado = (int)$stmt->fetchColumn();

            $pdo->prepare('DELETE FROM cardapio_itens WHERE id = ?')->execute([$id]);
            if ($antes['foto'] && file_exists(BASE_PATH . '/' . $antes['foto'])) {
                @unlink(BASE_PATH . '/' . $antes['foto']);
            }
            registrar_log('consumo', 'Item de cardápio excluído #' . $id, $id, $antes, null);
            flash('success', $usado > 0
                ? 'Item excluído. Os lançamentos em comandas foram preservados.'
                : 'Item excluído com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'excluir_item_cardapio');
            flash('danger', 'Não foi possível excluir o item.');
        }
        redirecionar('consumo/cardapio/index.php');
        break;

    case 'salvar_categoria':
        exigir_permissao('cardapio_editar');
        $id = (int)($_POST['id'] ?? 0);
        $edicao = $id > 0;
        $nome = maiusculas($_POST['nome'] ?? '');
        $cor = (string)($_POST['cor'] ?? '#4f6ef7');
        $ordem = (int)($_POST['ordem'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

        if ($nome === '') {
            flash('danger', 'Informe o nome da categoria.');
            redirecionar('consumo/cardapio/categorias.php');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $cor = '#4f6ef7';
        }
        if ($ordem < 0 || $ordem > 999) {
            $ordem = 0;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM cardapio_categorias WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $antes = $id > 0 ? $stmt->fetch() : null;

            if ($edicao && !$antes) {
                flash('danger', 'Categoria não encontrada.');
                redirecionar('consumo/cardapio/categorias.php');
            }

            $stmt = $pdo->prepare('SELECT id FROM cardapio_categorias WHERE nome = ? AND id <> ? LIMIT 1');
            $stmt->execute([$nome, $id]);
            if ($stmt->fetch()) {
                flash('danger', 'Já existe uma categoria com este nome.');
                redirecionar('consumo/cardapio/categorias.php');
            }

            if ($edicao) {
                $pdo->prepare(
                    'UPDATE cardapio_categorias SET nome = ?, cor = ?, ordem = ?, ativo = ? WHERE id = ?'
                )->execute([$nome, $cor, $ordem, $ativo, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO cardapio_categorias (nome, cor, ordem, ativo, criado_em) VALUES (?, ?, ?, ?, NOW())'
                )->execute([$nome, $cor, $ordem, $ativo]);
                $id = (int)$pdo->lastInsertId();
            }

            registrar_log('consumo', ($edicao ? 'Categoria de cardápio editada' : 'Categoria de cardápio criada')
                . ' #' . $id, $id, $antes, ['nome' => $nome, 'cor' => $cor, 'ordem' => $ordem, 'ativo' => $ativo]);
            flash('success', 'Categoria salva com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_categoria_cardapio');
            flash('danger', 'Não foi possível salvar a categoria.');
        }
        redirecionar('consumo/cardapio/categorias.php');
        break;

    case 'excluir_categoria':
        exigir_permissao('cardapio_editar');
        $id = (int)($_POST['id'] ?? 0);
        $antes = buscar_linha('cardapio_categorias', $id);
        if (!$antes) {
            flash('danger', 'Categoria não encontrada.');
            redirecionar('consumo/cardapio/categorias.php');
        }
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cardapio_itens WHERE categoria_id = ?');
            $stmt->execute([$id]);
            $qtdItens = (int)$stmt->fetchColumn();

            $pdo->prepare('DELETE FROM cardapio_categorias WHERE id = ?')->execute([$id]);
            registrar_log('consumo', 'Categoria de cardápio excluída #' . $id, $id, $antes, null);
            flash('success', $qtdItens > 0
                ? 'Categoria excluída. Os ' . $qtdItens . ' item(ns) ficaram sem categoria.'
                : 'Categoria excluída com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'excluir_categoria_cardapio');
            flash('danger', 'Não foi possível excluir a categoria.');
        }
        redirecionar('consumo/cardapio/categorias.php');
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('consumo/cardapio/index.php');
}
