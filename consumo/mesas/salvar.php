<?php
require_once __DIR__ . '/../../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('consumo/mesas/index.php');
}
exigir_csrf();

$acao = (string)($_POST['acao'] ?? '');
$pdo = db();

switch ($acao) {

    case 'salvar':
        exigir_permissao('mesas_editar');

        $id = (int)($_POST['id'] ?? 0);
        $edicao = $id > 0;
        $antes = $edicao ? mesa_buscar($id) : null;
        if ($edicao && !$antes) {
            flash('danger', 'Mesa não encontrada.');
            redirecionar('consumo/mesas/index.php');
        }

        $numero = (int)($_POST['numero'] ?? 0);
        $nome = (string)($_POST['nome'] ?? '');
        $capacidade = (int)($_POST['capacidade'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 1) === 1 ? 1 : 0;
        $status = (string)($_POST['status'] ?? 'LIVRE');

        if ($numero <= 0) {
            flash('danger', 'Informe um número válido para a mesa.');
            voltar();
        }
        if ($capacidade < 1 || $capacidade > 999) {
            flash('danger', 'Informe uma capacidade entre 1 e 999 lugares.');
            voltar();
        }
        if (!in_array($status, ['LIVRE', 'OCUPADA', 'RESERVADA'], true)) {
            $status = 'LIVRE';
        }

        try {
            $stmt = $pdo->prepare('SELECT id FROM mesas WHERE numero = ? AND id <> ? LIMIT 1');
            $stmt->execute([$numero, $id]);
            if ($stmt->fetch()) {
                flash('danger', 'Já existe uma mesa com este número.');
                voltar();
            }

            // Mesas com comanda aberta mantêm o status controlado pelo fluxo de comandas.
            if ($edicao) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM comandas WHERE mesa_id = ? AND status = 'ABERTA'");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $status = (string)$antes['status'];
                }
            } elseif ($status === 'OCUPADA') {
                flash('danger', 'Mesa nova não pode começar como ocupada. Abra uma comanda depois.');
                voltar();
            }

            if ($edicao) {
                $pdo->prepare(
                    'UPDATE mesas SET numero = ?, nome = ?, capacidade = ?, status = ?, ativo = ? WHERE id = ?'
                )->execute([$numero, $nome ?: null, $capacidade, $status, $ativo, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO mesas (numero, nome, capacidade, status, ativo, criado_em) VALUES (?, ?, ?, ?, ?, NOW())'
                )->execute([$numero, $nome ?: null, $capacidade, $status, $ativo]);
                $id = (int)$pdo->lastInsertId();
            }

            registrar_log('consumo', ($edicao ? 'Mesa editada' : 'Mesa criada') . ' #' . $id, $id, $antes, [
                'numero' => $numero, 'capacidade' => $capacidade, 'status' => $status, 'ativo' => $ativo,
            ]);
            flash('success', 'Mesa salva com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_mesa');
            flash('danger', 'Não foi possível salvar a mesa.');
        }
        redirecionar('consumo/mesas/index.php');
        break;

    case 'excluir':
        exigir_permissao('mesas_excluir');
        $id = (int)($_POST['id'] ?? 0);
        $antes = mesa_buscar($id);
        if (!$antes) {
            flash('danger', 'Mesa não encontrada.');
            redirecionar('consumo/mesas/index.php');
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM comandas WHERE mesa_id = ? AND status = 'ABERTA'");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                flash('danger', 'Não é possível excluir uma mesa com comanda aberta.');
                redirecionar('consumo/mesas/index.php');
            }

            $pdo->prepare('DELETE FROM mesas WHERE id = ?')->execute([$id]);
            registrar_log('consumo', 'Mesa excluída #' . $id, $id, $antes, null);
            flash('success', 'Mesa excluída. O histórico de comandas foi preservado.');
        } catch (Throwable $e) {
            erro_banco($e, 'excluir_mesa');
            flash('danger', 'Não foi possível excluir a mesa. Desative-a caso exista histórico vinculado.');
        }
        redirecionar('consumo/mesas/index.php');
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('consumo/mesas/index.php');
}
