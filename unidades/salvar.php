<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('unidades/index.php');
}
exigir_csrf();

$id = (int)($_POST['id'] ?? 0);
$nome = maiusculas($_POST['nome'] ?? '');
$sigla = maiusculas($_POST['sigla'] ?? '');
$edicao = $id > 0;

if ($edicao) {
    exigir_permissao('unidades_editar');
} else {
    exigir_permissao('unidades_criar');
}

if ($nome === '' || $sigla === '') {
    flash('danger', 'Preencha os campos obrigatórios.');
    voltar();
}

try {
    $stmt = db()->prepare('SELECT id FROM unidades WHERE (nome = ? OR sigla = ?) AND id <> ? LIMIT 1');
    $stmt->execute([$nome, $sigla, $id]);
    if ($stmt->fetch()) {
        flash('danger', 'Já existe uma unidade com esta descrição ou sigla.');
        voltar();
    }
    if ($edicao) {
        $antes = buscar_linha('unidades', $id);
        db()->prepare('UPDATE unidades SET nome = ?, sigla = ? WHERE id = ?')->execute([$nome, $sigla, $id]);
        registrar_log('unidades', 'Unidade editada #' . $id, $id, $antes, ['nome' => $nome, 'sigla' => $sigla]);
        flash('success', 'Unidade editada com sucesso!');
    } else {
        db()->prepare('INSERT INTO unidades (nome, sigla, criado_em) VALUES (?, ?, NOW())')->execute([$nome, $sigla]);
        registrar_log('unidades', 'Unidade criada', (int)db()->lastInsertId(), null, ['nome' => $nome, 'sigla' => $sigla]);
        flash('success', 'Unidade salva com sucesso!');
    }
} catch (Throwable $e) {
    erro_banco($e, 'salvar_unidade');
    flash('danger', 'Não foi possível salvar a unidade.');
}
redirecionar('unidades/index.php');