<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('fornecedores/index.php');
}
exigir_csrf();

$acao = $_POST['acao'] ?? '';

switch ($acao) {

    case 'salvar_fornecedor':
        if (empty($_POST['id'])) {
            exigir_permissao('fornecedores_criar');
        } else {
            exigir_permissao('fornecedores_editar');
        }

        $id = (int)($_POST['id'] ?? 0);
        $razao = maiusculas($_POST['razao_social'] ?? '');
        $fantasia = maiusculas($_POST['nome_fantasia'] ?? '');
        $documento = sanear($_POST['documento'] ?? '');
        $ie = maiusculas($_POST['inscricao_estadual'] ?? '');
        $email = sanear($_POST['email'] ?? '');
        $telefone = sanear($_POST['telefone'] ?? '');
        $celular = sanear($_POST['celular'] ?? '');
        $cep = sanear($_POST['cep'] ?? '');
        $endereco = maiusculas($_POST['endereco'] ?? '');
        $numero = maiusculas($_POST['numero'] ?? '');
        $complemento = maiusculas($_POST['complemento'] ?? '');
        $bairro = maiusculas($_POST['bairro'] ?? '');
        $cidade = maiusculas($_POST['cidade'] ?? '');
        $estado = maiusculas($_POST['estado'] ?? '');
        $obs = maiusculas($_POST['observacoes'] ?? '');
        $status = (int)($_POST['status'] ?? 1);

        if ($razao === '') {
            flash('danger', 'Informe a razão social.');
            voltar();
        }
        if ($documento !== '' && !validar_cnpj($documento)) {
            flash('danger', 'CNPJ inválido.');
            voltar();
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'E-mail inválido.');
            voltar();
        }

        try {
            if ($documento !== '') {
                $stmt = db()->prepare('SELECT id FROM fornecedores WHERE documento = ? AND id <> ? LIMIT 1');
                $stmt->execute([apenas_numeros($documento), $id]);
                if ($stmt->fetch()) {
                    flash('danger', 'Já existe um fornecedor com este CNPJ.');
                    voltar();
                }
            }

            if ($id > 0) {
                $antes = buscar_linha('fornecedores', $id);
                if (!$antes) {
                    flash('danger', 'Fornecedor não encontrado.');
                    voltar();
                }
                $stmt = db()->prepare(
                    'UPDATE fornecedores SET razao_social=?, nome_fantasia=?, documento=?, inscricao_estadual=?,
                            email=?, telefone=?, celular=?, cep=?, endereco=?, numero=?, complemento=?, bairro=?,
                            cidade=?, estado=?, observacoes=?, status=?
                      WHERE id=?'
                );
                $stmt->execute([$razao, $fantasia, apenas_numeros($documento), $ie, $email, $telefone, $celular,
                    $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $obs, $status, $id]);
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO fornecedores (codigo, razao_social, nome_fantasia, documento, inscricao_estadual,
                            email, telefone, celular, cep, endereco, numero, complemento, bairro, cidade, estado, observacoes, status, criado_em)
                     VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$razao, $fantasia, apenas_numeros($documento), $ie, $email, $telefone, $celular,
                    $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $obs, $status]);
                $id = (int)db()->lastInsertId();
                db()->prepare("UPDATE fornecedores SET codigo = CONCAT('F', LPAD(?, 5, '0')) WHERE id = ?")
                    ->execute([$id, $id]);
            }

            registrar_log('fornecedores', ($id > 0 ? 'Fornecedor editado' : 'Fornecedor criado') . ' #' . $id, $id, $antes ?? null, [
                'razao_social' => $razao, 'documento' => $documento,
            ]);
            flash('success', 'Fornecedor salvo com sucesso!');
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_fornecedor');
            flash('danger', 'Não foi possível salvar o fornecedor. Verifique os dados informados.');
        }
        redirecionar('fornecedores/index.php');
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('fornecedores/index.php');
}