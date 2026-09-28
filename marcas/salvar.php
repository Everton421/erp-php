<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('marcas/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$nome = maiusculas($_POST['nome'] ?? '');
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('marcas_editar');
} else {
    exigir_permissao('marcas_criar');
}

if ($nome === '') {
    flash('danger', 'Informe o nome da marca.');
    voltar();
}

try {
    $stmt = db()->prepare('SELECT id FROM marcas WHERE nome = ? AND id <> ? LIMIT 1');
    $stmt->execute([$nome, $id]);
    if ($stmt->fetch()) {
        flash('danger', 'Já existe uma marca com este nome.');
        voltar();
    }
    if ($edicao) {
        $antes = buscar_linha('marcas', $id);
        db()->prepare('UPDATE marcas SET nome = ? WHERE id = ?')->execute([$nome, $id]);
        registrar_log('marcas', 'Marca editada #' . $id, $id, $antes, ['nome' => $nome]);
        flash('success', 'Marca editada com sucesso!');
    } else {
        db()->prepare('INSERT INTO marcas (nome, criado_em) VALUES (?, NOW())')->execute([$nome]);
        registrar_log('marcas', 'Marca criada', (int)db()->lastInsertId(), null, ['nome' => $nome]);
        flash('success', 'Marca salva com sucesso!');
    }
} catch (Throwable $e) {
    erro_banco($e, 'salvar_marca');
    flash('danger', 'Não foi possível salvar a marca.');
}
redirecionar('marcas/index.php');