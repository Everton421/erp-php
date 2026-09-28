<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('contas_pagar/index.php');
}
exigir_csrf();

$acao = $_POST['acao'] ?? '';
$pdo = db();

try {

    switch ($acao) {

        case 'criar':
            exigir_permissao('contas_pagar_criar');
            $descricao = sanear($_POST['descricao'] ?? '');
            $valor = parse_decimal($_POST['valor'] ?? '0');
            $vencimento = parse_data($_POST['vencimento'] ?? '');
            if ($descricao === '' || $valor <= 0 || !$vencimento) {
                flash('danger', 'Preencha descrição, valor e vencimento.');
                voltar();
            }
            $fornecedorId = (int)($_POST['fornecedor_id'] ?? 0);
            $categoriaId = (int)($_POST['categoria_id'] ?? 0);
            if ($fornecedorId > 0 && !buscar_linha('fornecedores', $fornecedorId)) {
                flash('danger', 'Fornecedor inválido.');
                voltar();
            }
            if ($categoriaId > 0) {
                $cat = buscar_linha('categorias_financeiras', $categoriaId);
                if (!$cat || $cat['tipo'] !== 'DESPESA') {
                    flash('danger', 'Categoria inválida.');
                    voltar();
                }
            }
            $stmt = $pdo->prepare(
                'INSERT INTO contas_pagar (fornecedor_id, categoria_id, documento, descricao, valor, vencimento, status, forma_pagamento_id, observacao)
                 VALUES (?, ?, ?, ?, ?, ?, \'PENDENTE\', ?, ?)'
            );
            $stmt->execute([
                $fornecedorId ?: null,
                $categoriaId ?: null,
                sanear($_POST['documento'] ?? '') ?: null,
                $descricao,
                round($valor, 2),
                $vencimento,
                (int)($_POST['forma_pagamento_id'] ?? 0) ?: null,
                sanear($_POST['observacao'] ?? '') ?: null,
            ]);
            $id = (int)$pdo->lastInsertId();
            registrar_log('contas_pagar', 'Conta a pagar criada #' . $id, $id, null, ['descricao' => $descricao, 'valor' => $valor]);
            flash('success', 'Conta a pagar criada com sucesso.');
            redirecionar('contas_pagar/index.php');
            break;

        case 'editar':
            exigir_permissao('contas_pagar_editar');
            $id = (int)($_POST['id'] ?? 0);
            $conta = buscar_linha('contas_pagar', $id);
            if (!$conta) {
                flash('danger', 'Conta não encontrada.');
                voltar();
            }
            if ($conta['valor_pago'] > 0) {
                flash('danger', 'Não é possível editar uma conta com pagamentos realizados.');
                voltar();
            }
            $descricao = sanear($_POST['descricao'] ?? '');
            $valor = parse_decimal($_POST['valor'] ?? '0');
            $vencimento = parse_data($_POST['vencimento'] ?? '');
            if ($descricao === '' || $valor <= 0 || !$vencimento) {
                flash('danger', 'Preencha descrição, valor e vencimento.');
                voltar();
            }
            $stmt = $pdo->prepare(
                'UPDATE contas_pagar SET fornecedor_id = ?, categoria_id = ?, documento = ?, descricao = ?, valor = ?, vencimento = ?, forma_pagamento_id = ?, observacao = ?
                  WHERE id = ?'
            );
            $stmt->execute([
                (int)($_POST['fornecedor_id'] ?? 0) ?: null,
                (int)($_POST['categoria_id'] ?? 0) ?: null,
                sanear($_POST['documento'] ?? '') ?: null,
                $descricao,
                round($valor, 2),
                $vencimento,
                (int)($_POST['forma_pagamento_id'] ?? 0) ?: null,
                sanear($_POST['observacao'] ?? '') ?: null,
                $id,
            ]);
            registrar_log('contas_pagar', 'Conta a pagar editada #' . $id, $id, $conta, ['valor' => $valor, 'vencimento' => $vencimento]);
            flash('success', 'Conta a pagar atualizada.');
            redirecionar('contas_pagar/index.php');
            break;

        case 'excluir':
            exigir_permissao('contas_pagar_excluir');
            $id = (int)($_POST['id'] ?? 0);
            $conta = buscar_linha('contas_pagar', $id);
            if (!$conta) {
                flash('danger', 'Conta não encontrada.');
                voltar();
            }
            if ($conta['valor_pago'] > 0) {
                flash('danger', 'Não é possível excluir uma conta com pagamentos realizados.');
                voltar();
            }
            $pdo->prepare('DELETE FROM contas_pagar WHERE id = ?')->execute([$id]);
            registrar_log('contas_pagar', 'Conta a pagar excluída #' . $id, $id, $conta, null);
            flash('success', 'Conta a pagar excluída.');
            redirecionar('contas_pagar/index.php');
            break;

        default:
            flash('danger', 'Operação inválida.');
            redirecionar('contas_pagar/index.php');
    }

} catch (Throwable $e) {
    erro_banco($e, 'contas_pagar_' . $acao);
    flash('danger', 'Não foi possível processar a operação.');
    redirecionar('contas_pagar/index.php');
}