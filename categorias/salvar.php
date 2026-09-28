<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('categorias/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$nome = maiusculas($_POST['nome'] ?? '');
$paiId = (int)($_POST['categoria_pai_id'] ?? 0);
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('categorias_editar');
} else {
    exigir_permissao('categorias_criar');
}

if ($nome === '') {
    flash('danger', 'Informe o nome da categoria.');
    voltar();
}

try {
    $stmt = db()->prepare('SELECT id FROM categorias WHERE nome = ? AND id <> ? LIMIT 1');
    $stmt->execute([$nome, $id]);
    if ($stmt->fetch()) {
        flash('danger', 'Já existe uma categoria com este nome.');
        voltar();
    }

    // Se possui categoria pai, grava como subcategoria
    if ($paiId > 0 && !$edicao) {
        $stmt = db()->prepare('SELECT id FROM categorias WHERE id = ?');
        $stmt->execute([$paiId]);
        if (!$stmt->fetch()) {
            flash('danger', 'Categoria pai inválida.');
            voltar();
        }
        $stmt = db()->prepare('INSERT INTO subcategorias (categoria_id, nome, criado_em) VALUES (?, ?, NOW())');
        $stmt->execute([$paiId, $nome]);
        registrar_log('categorias', 'Subcategoria criada', $paiId, null, ['nome' => $nome]);
        flash('success', 'Subcategoria salva com sucesso!');
    } elseif ($edicao) {
        $antes = buscar_linha('categorias', $id);
        db()->prepare('UPDATE categorias SET nome = ? WHERE id = ?')->execute([$nome, $id]);
        registrar_log('categorias', 'Categoria editada #' . $id, $id, $antes, ['nome' => $nome]);
        flash('success', 'Categoria editada com sucesso!');
    } else {
        db()->prepare('INSERT INTO categorias (nome, criado_em) VALUES (?, NOW())')->execute([$nome]);
        registrar_log('categorias', 'Categoria criada', (int)db()->lastInsertId(), null, ['nome' => $nome]);
        flash('success', 'Categoria salva com sucesso!');
    }
} catch (Throwable $e) {
    erro_banco($e, 'salvar_categoria');
    flash('danger', 'Não foi possível salvar a categoria.');
}
redirecionar('categorias/index.php');