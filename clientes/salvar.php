<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('clientes/index.php');
}
exigir_csrf();

$acao = $_POST['acao'] ?? '';
$pdo = db();

switch ($acao) {

    case 'salvar_cliente':
        if (empty($_POST['id'])) {
            exigir_permissao('clientes_criar');
        } else {
            exigir_permissao('clientes_editar');
        }

        $id     = (int)($_POST['id'] ?? 0);
        $tipo   = ($_POST['tipo'] ?? 'FISICA') === 'JURIDICA' ? 'JURIDICA' : 'FISICA';
        $nome   = maiusculas($_POST['nome'] ?? '');
        $nomeFantasia = maiusculas($_POST['nome_fantasia'] ?? '');
        $documento = sanear($_POST['documento'] ?? '');
        $ie     = maiusculas($_POST['inscricao_estadual'] ?? '');
        $nascimento = parse_data($_POST['nascimento'] ?? '');
        $email  = sanear($_POST['email'] ?? '');
        $telefone = sanear($_POST['telefone'] ?? '');
        $celular  = sanear($_POST['celular'] ?? '');
        $cep    = sanear($_POST['cep'] ?? '');
        $endereco = maiusculas($_POST['endereco'] ?? '');
        $numero = maiusculas($_POST['numero'] ?? '');
        $complemento = maiusculas($_POST['complemento'] ?? '');
        $bairro = maiusculas($_POST['bairro'] ?? '');
        $cidade = maiusculas($_POST['cidade'] ?? '');
        $estado = maiusculas($_POST['estado'] ?? '');
        $obs    = maiusculas($_POST['observacoes'] ?? '');
        $status = (int)($_POST['status'] ?? 1);

        if ($nome === '') {
            flash('danger', 'Informe o nome do cliente.');
            voltar();
        }
        if ($documento !== '') {
            if ($tipo === 'FISICA' && !validar_cpf($documento)) {
                flash('danger', 'CPF inválido.');
                voltar();
            }
            if ($tipo === 'JURIDICA' && !validar_cnpj($documento)) {
                flash('danger', 'CNPJ inválido.');
                voltar();
            }
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'E-mail inválido.');
            voltar();
        }

        try {
            if ($documento !== '') {
                $stmt = $pdo->prepare('SELECT id FROM clientes WHERE documento = ? AND id <> ? LIMIT 1');
                $stmt->execute([apenas_numeros($documento), $id]);
                if ($stmt->fetch()) {
                    flash('danger', 'Já existe um cliente com este CPF/CNPJ.');
                    voltar();
                }
            }

            if ($id > 0) {
                $antes = buscar_linha('clientes', $id);
                if (!$antes) {
                    flash('danger', 'Cliente não encontrado.');
                    voltar();
                }
                $stmt = $pdo->prepare(
                    'UPDATE clientes SET tipo=?, nome=?, nome_fantasia=?, documento=?, inscricao_estadual=?, nascimento=?,
                            email=?, telefone=?, celular=?, cep=?, endereco=?, numero=?, complemento=?, bairro=?,
                            cidade=?, estado=?, observacoes=?, status=?
                      WHERE id=?'
                );
                $stmt->execute([$tipo, $nome, $nomeFantasia, apenas_numeros($documento), $ie, $nascimento, $email,
                    $telefone, $celular, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $obs, $status, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO clientes (codigo, tipo, nome, nome_fantasia, documento, inscricao_estadual, nascimento,
                            email, telefone, celular, cep, endereco, numero, complemento, bairro, cidade, estado, observacoes, status, criado_em)
                     VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$tipo, $nome, $nomeFantasia, apenas_numeros($documento), $ie, $nascimento, $email,
                    $telefone, $celular, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $obs, $status]);
                $id = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE clientes SET codigo = CONCAT('C', LPAD(?, 5, '0')) WHERE id = ?")
                    ->execute([$id, $id]);
            }

            registrar_log('clientes', ($id > 0 ? 'Cliente editado' : 'Cliente criado') . ' #' . $id, $id, $antes ?? null, [
                'nome' => $nome, 'documento' => $documento, 'tipo' => $tipo,
            ]);
            flash('success', 'Cliente salvo com sucesso!');
            if (isset($_POST['e_venda']) && (int)$_POST['e_venda'] === 1) {
                redirecionar('vendas/nova.php?cliente_id=' . $id);
            }
        } catch (Throwable $e) {
            erro_banco($e, 'salvar_cliente');
            flash('danger', 'Não foi possível salvar o cliente. Verifique os dados informados.');
        }
        redirecionar('clientes/index.php');
        break;

    default:
        flash('danger', 'Ação inválida.');
        redirecionar('clientes/index.php');
}