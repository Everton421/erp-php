<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('usuarios/index.php');
}

exigir_csrf();

$acao = $_POST['acao'] ?? '';
$meuId = (int)usuario_atual()['id'];
$pdo = db();

switch ($acao) {

    case 'salvar_user':
        exigir_permissao('usuarios_editar');

        $id       = (int)($_POST['id'] ?? 0);
        $nome     = maiusculas($_POST['nome'] ?? '');
        $usuarioN = sanear($_POST['usuario'] ?? '');
        $email    = sanear($_POST['email'] ?? '');
        $telefone = sanear($_POST['telefone'] ?? '');
        $perfilId = (int)($_POST['perfil_id'] ?? 0);
        $status   = (int)($_POST['status'] ?? 0);
        $isAdmin  = isset($_POST['is_admin']) ? 1 : 0;
        $senha    = (string)($_POST['senha'] ?? '');
        $senha2   = (string)($_POST['senha2'] ?? '');
        $overrides = [];

        $thisEhAdmin = $id === $meuId;

        // Validações
        if ($nome === '' || $usuarioN === '' || $email === '' || $perfilId <= 0) {
            flash('danger', 'Preencha os campos obrigatórios.');
            voltar();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Informe um e-mail válido.');
            voltar();
        }

        $edicao = $id > 0;
        if ($edicao && !buscar_linha('usuarios', $id)) {
            flash('danger', 'Usuário não encontrado.');
            redirecionar('usuarios/index.php');
        }

        if (!$edicao && $senha === '') {
            flash('danger', 'Informe uma senha para o novo usuário.');
            voltar();
        }
        if ($senha !== '') {
            if (strlen($senha) < 6) {
                flash('danger', 'A senha deve ter no mínimo 6 caracteres.');
                voltar();
            }
            if ($senha !== $senha2) {
                flash('danger', 'As senhas não conferem.');
                voltar();
            }
        }

        // Remover a própria flag de admin nunca é permitido
        if ($thisEhAdmin && (int)$isAdmin === 0) {
            flash('danger', 'Você não pode remover o acesso de administrador do próprio usuário.');
            voltar();
        }

        try {
            // Dupes
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1');
            $stmt->execute([$usuarioN, $id]);
            if ($stmt->fetch()) {
                flash('danger', 'Já existe um usuário com este nome de usuário.');
                voltar();
            }
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                flash('danger', 'Já existe um usuário com este e-mail.');
                voltar();
            }

            // Overrides
            $raw = $_POST['overrides'] ?? '[]';
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $o) {
                    if (isset($o['chave']) && permissao_existe($o['chave'])) {
                        $overrides[$o['chave']] = !empty($o['permitido']) ? 1 : 0;
                    }
                }
            }

            $antes = null;
            if ($edicao) {
                $antes = buscar_linha('usuarios', $id);
                $antes['senha'] = '(hash)';
            }

            if (!$edicao) {
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (nome, usuario, email, senha, telefone, perfil_id, is_admin, status, criado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$nome, $usuarioN, $email, password_hash($senha, PASSWORD_DEFAULT), $telefone, $perfilId, $isAdmin, $status]);
                $id = (int)$pdo->lastInsertId();
            } else {
                $sql = 'UPDATE usuarios SET nome = ?, usuario = ?, email = ?, telefone = ?, perfil_id = ?, is_admin = ?, status = ?';
                $params = [$nome, $usuarioN, $email, $telefone, $perfilId, $isAdmin, $status];
                if ($senha !== '') {
                    $sql .= ', senha = ?';
                    $params[] = password_hash($senha, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
            }

            // Permissões individuais (apenas para não-admins)
            if ($isAdmin === 0) {
                $stmt = $pdo->prepare('SELECT id, chave FROM permissoes');
                $todas = $stmt->fetchAll();
                $pdo->prepare('DELETE FROM usuario_permissoes WHERE usuario_id = ?')->execute([$id]);
                $stmtIns = $pdo->prepare('INSERT INTO usuario_permissoes (usuario_id, permissao_id, permitido) VALUES (?, ?, ?)');
                foreach ($todas as $p) {
                    if (array_key_exists($p['chave'], $overrides)) {
                        $stmtIns->execute([$id, (int)$p['id'], $overrides[$p['chave']]]);
                    }
                }
            }

            registrar_log('usuarios', ($edicao ? 'Usuário editado' : 'Usuário criado') . ' #' . $id, $id, $antes, [
                'nome' => $nome, 'usuario' => $usuarioN, 'perfil_id' => $perfilId, 'is_admin' => $isAdmin,
            ]);

            flash('success', 'Usuário salvo com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_user');
            flash('danger', 'Não foi possível salvar o usuário. Verifique os dados informados.');
        }
        redirecionar('usuarios/index.php');
        break;

    case 'excluir_user':
        exigir_permissao('usuarios_excluir');

        $id = (int)($_POST['id'] ?? 0);
        if ($id === $meuId) {
            flash('danger', 'Você não pode excluir o próprio usuário.');
            voltar();
        }
        $alvo = buscar_linha('usuarios', $id);
        if (!$alvo) {
            flash('danger', 'Usuário não encontrado.');
            voltar();
        }
        if ((int)$alvo['is_admin'] === 1) {
            $admins = $pdo->query('SELECT COUNT(*) FROM usuarios WHERE is_admin = 1')->fetchColumn();
            if ((int)$admins <= 1) {
                flash('danger', 'Não é possível excluir o último usuário administrador.');
                voltar();
            }
        }
        try {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            registrar_log('usuarios', 'Usuário excluído #' . $id, $id, $alvo, null);
            flash('success', 'Usuário excluído com sucesso.');
        } catch (Throwable $e) {
            erro_banco($e, 'excluir_user');
            flash('danger', 'Não foi possível excluir o usuário.');
        }
        redirecionar('usuarios/index.php');
        break;

    case 'salvar_perfil':
        exigir_permissao('usuarios_editar');

        $id = (int)($_POST['id'] ?? 0);
        $nome = maiusculas($_POST['nome'] ?? '');
        if ($nome === '') {
            flash('danger', 'Informe o nome do perfil.');
            voltar();
        }
        $permIds = [];
        foreach ((array)($_POST['perm'] ?? []) as $chave) {
            if (permissao_existe($chave)) {
                $permIds[$chave] = 1;
            }
        }
        try {
            $stmt = $pdo->prepare('SELECT id FROM perfis WHERE nome = ? AND id <> ? LIMIT 1');
            $stmt->execute([$nome, $id]);
            if ($stmt->fetch()) {
                flash('danger', 'Já existe um perfil com este nome.');
                voltar();
            }

            if ($id > 0) {
                $perfil = buscar_linha('perfis', $id);
                if (!$perfil) {
                    flash('danger', 'Perfil não encontrado.');
                    voltar();
                }
                $pdo->prepare('UPDATE perfis SET nome = ? WHERE id = ?')->execute([$nome, $id]);
            } else {
                $pdo->prepare('INSERT INTO perfis (nome, criado_em) VALUES (?, NOW())')->execute([$nome]);
                $id = (int)$pdo->lastInsertId();
            }

            // Repopular permissões do perfil (apenas as listadas estáticas)
            $pdo->prepare('DELETE FROM perfil_permissoes WHERE perfil_id = ?')->execute([$id]);
            $todosPerms = db()->query('SELECT id, chave FROM permissoes')->fetchAll();
            $stmtIns = $pdo->prepare('INSERT INTO perfil_permissoes (perfil_id, permissao_id, permitido) VALUES (?, ?, 1)');
            foreach ($todosPerms as $p) {
                if (isset($permIds[$p['chave']])) {
                    $stmtIns->execute([$id, (int)$p['id']]);
                }
            }

            registrar_log('usuarios', 'Perfil salvo #' . $id, $id, null, ['nome' => $nome]);
            flash('success', 'Perfil salvo com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_perfil');
            flash('danger', 'Não foi possível salvar o perfil.');
        }
        redirecionar('usuarios/perfis.php');
        break;

    case 'excluir_perfil':
        exigir_permissao('usuarios_editar');

        $id = (int)($_POST['id'] ?? 0);
        try {
            $perfil = buscar_linha('perfis', $id);
            if (!$perfil) {
                flash('danger', 'Perfil não encontrado.');
                voltar();
            }
            $emUso = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE perfil_id = ?');
            $emUso->execute([$id]);
            if ((int)$emUso->fetchColumn() > 0) {
                flash('danger', 'Não é possível excluir um perfil em uso por usuários.');
                voltar();
            }
            $pdo->prepare('DELETE FROM perfis WHERE id = ?')->execute([$id]);
            registrar_log('usuarios', 'Perfil excluído #' . $id, $id, $perfil, null);
            flash('success', 'Perfil excluído.');
        } catch (Throwable $e) {
            erro_banco($e, 'excluir_perfil');
            flash('danger', 'Não foi possível excluir o perfil.');
        }
        redirecionar('usuarios/perfis.php');
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('usuarios/index.php');
}