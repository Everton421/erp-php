<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('categorias_financeiras/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$nome = maiusculas($_POST['nome'] ?? '');
$tipo = ($_POST['tipo'] ?? '') === 'DESPESA' ? 'DESPESA' : 'RECEITA';
$edicao = $id > 0;

if ($nome === '') {
    flash('danger', 'Informe o nome da categoria.');
    voltar();
}

try {
    $stmt = db()->prepare('SELECT id FROM categorias_financeiras WHERE nome = ? AND tipo = ? AND id <> ? LIMIT 1');
    $stmt->execute([$nome, $tipo, $id]);
    if ($stmt->fetch()) {
        flash('danger', 'Já existe uma categoria com este nome e tipo.');
        voltar();
    }
    if ($edicao) {
        $antes = buscar_linha('categorias_financeiras', $id);
        db()->prepare('UPDATE categorias_financeiras SET nome = ?, tipo = ? WHERE id = ?')->execute([$nome, $tipo, $id]);
        registrar_log('categorias_fin', 'Categoria financeira editada #' . $id, $id, $antes, ['nome' => $nome, 'tipo' => $tipo]);
        flash('success', 'Categoria editada com sucesso!');
    } else {
        db()->prepare('INSERT INTO categorias_financeiras (tipo, nome, criado_em) VALUES (?, ?, NOW())')->execute([$tipo, $nome]);
        registrar_log('categorias_fin', 'Categoria financeira criada', (int)db()->lastInsertId(), null, ['nome' => $nome, 'tipo' => $tipo]);
        flash('success', 'Categoria salva com sucesso!');
    }
} catch (Throwable $e) {
    erro_banco($e, 'salvar_categoria_fin');
    flash('danger', 'Não foi possível salvar a categoria.');
}
redirecionar('categorias_financeiras/index.php');