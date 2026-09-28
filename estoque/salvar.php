<?php
require_once __DIR__ . '/../config/config.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('estoque/index.php');
}
exigir_csrf();

$acao = $_POST['acao'] ?? '';
$usuario = usuario_atual();
$usuarioId = (int)$usuario['id'];
$pdo = db();
$permiteNegativo = (int)obter_config('estoque_negativo', '0') === 1;

try {

    $produtoId = (int)($_POST['produto_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
    $stmt->execute([$produtoId]);
    $produto = $stmt->fetch();
    if (!$produto) {
        flash('danger', 'Produto não encontrado.');
        voltar();
    }
    if ((int)$produto['status'] !== 1) {
        flash('danger', 'Não é possível movimentar um produto inativo.');
        voltar();
    }

    $data = parse_data($_POST['data'] ?? date('d/m/Y')) ?: hoje();
    $observacao = trim((string)($_POST['observacao'] ?? ''));
    $estoqueAnterior = (float)$produto['estoque_atual'];

    switch ($acao) {

        case 'entrada':
            exigir_permissao('estoque_entrada');
            $qtd = parse_decimal($_POST['quantidade'] ?? '0');
            $custo = parse_decimal($_POST['custo'] ?? '0');
            $documento = sanear($_POST['documento'] ?? '');
            $motivo = sanear($_POST['motivo'] ?? 'COMPRA');

            if ($qtd <= 0) {
                flash('danger', 'A quantidade deve ser maior que zero.');
                voltar();
            }
            if ($custo < 0) {
                flash('danger', 'Custo inválido.');
                voltar();
            }

            $estoquePosterior = $estoqueAnterior + $qtd;
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?');
            $stmt->execute([$estoquePosterior, $produtoId]);
            $stmt = $pdo->prepare(
                'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$produtoId, 'ENTRADA', $qtd, $estoqueAnterior, $estoquePosterior, $custo, $motivo, $documento, $usuarioId, $data . ' ' . date('H:i:s'), $observacao]);

            // Se não possui custo, aproveita o custo informado
            if ((float)$produto['preco_custo'] <= 0 && $custo > 0) {
                $pdo->prepare('UPDATE produtos SET preco_custo = ? WHERE id = ?')->execute([$custo, $produtoId]);
            }
            $pdo->commit();
            registrar_log('estoque', 'Entrada de estoque #' . $produtoId, $produtoId, null, [
                'quantidade' => $qtd, 'custo' => $custo, 'documento' => $documento,
            ]);
            flash('success', 'Entrada de estoque registrada com sucesso!');
            break;

        case 'saida':
            exigir_permissao('estoque_saida');
            $qtd = parse_decimal($_POST['quantidade'] ?? '0');
            $motivo = sanear($_POST['motivo'] ?? 'OUTROS');

            if ($qtd <= 0) {
                flash('danger', 'A quantidade deve ser maior que zero.');
                voltar();
            }
            if (!$permiteNegativo && $estoqueAnterior - $qtd < 0) {
                flash('danger', 'Estoque insuficiente. Saldo atual: ' . formatar_qtde($estoqueAnterior) . '.');
                voltar();
            }

            $estoquePosterior = $estoqueAnterior - $qtd;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?')
                ->execute([$estoquePosterior, $produtoId]);
            $pdo->prepare(
                'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$produtoId, 'SAIDA', $qtd, $estoqueAnterior, $estoquePosterior, null, $motivo, '', $usuarioId, $data . ' ' . date('H:i:s'), $observacao]);
            $pdo->commit();
            registrar_log('estoque', 'Saída de estoque #' . $produtoId, $produtoId, null, [
                'quantidade' => $qtd, 'motivo' => $motivo,
            ]);
            flash('success', 'Saída de estoque registrada com sucesso!');
            break;

        case 'ajuste':
            exigir_permissao('estoque_ajuste');
            $tipoAjuste = strtoupper(sanear($_POST['tipo_ajuste'] ?? 'AJUSTE'));
            $quantidade = parse_decimal($_POST['nova_quantidade'] ?? '0');
            $motivo = sanear($_POST['motivo'] ?? 'OUTROS');

            if (!in_array($tipoAjuste, ['ENTRADA', 'SAIDA', 'AJUSTE'])) {
                $tipoAjuste = 'AJUSTE';
            }

            if ($tipoAjuste === 'ENTRADA') {
                if ($quantidade <= 0) {
                    flash('danger', 'A quantidade a adicionar deve ser maior que zero.');
                    voltar();
                }
                $estoquePosterior = $estoqueAnterior + $quantidade;
                $motivoGravado = 'AJUSTE POSITIVO';
            } elseif ($tipoAjuste === 'SAIDA') {
                if ($quantidade <= 0) {
                    flash('danger', 'A quantidade a retirar deve ser maior que zero.');
                    voltar();
                }
                if (!$permiteNegativo && $estoqueAnterior - $quantidade < 0) {
                    flash('danger', 'Estoque insuficiente. Saldo atual: ' . formatar_qtde($estoqueAnterior) . '.');
                    voltar();
                }
                $estoquePosterior = $estoqueAnterior - $quantidade;
                $motivoGravado = 'AJUSTE NEGATIVO';
            } else {
                $novaQtd = parse_decimal($_POST['nova_quantidade'] ?? '0');
                if ($novaQtd < 0) {
                    flash('danger', 'A nova quantidade não pode ser negativa.');
                    voltar();
                }
                $diferenca = $novaQtd - $estoqueAnterior;
                if (abs($diferenca) < 0.0001) {
                    flash('info', 'A quantidade informada é igual ao estoque atual. Nenhum ajuste necessário.');
                    voltar();
                }
                $estoquePosterior = $novaQtd;
                $quantidade = $diferenca;
                $motivoGravado = $motivo;
            }

            $qtdMov = $estoquePosterior - $estoqueAnterior;

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?')
                ->execute([$estoquePosterior, $produtoId]);
            $pdo->prepare(
                'INSERT INTO estoque_movimentos (produto_id, tipo, quantidade, estoque_anterior, estoque_posterior, custo, motivo, documento, usuario_id, data, observacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$produtoId, $tipoAjuste, abs($qtdMov), $estoqueAnterior, $estoquePosterior, null, $motivoGravado, '', $usuarioId, $data . ' ' . date('H:i:s'), $observacao]);
            $pdo->commit();
            registrar_log('estoque', 'Ajuste de estoque #' . $produtoId, $produtoId, null, [
                'anterior' => $estoqueAnterior, 'novo' => $estoquePosterior,
                'diferenca' => $qtdMov, 'tipo' => $tipoAjuste, 'motivo' => $motivoGravado,
            ]);
            flash('success', 'Ajuste de estoque registrado com sucesso!');
            break;

        default:
            flash('danger', 'Operação de estoque inválida.');
            redirecionar('estoque/index.php');
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erro_banco($e, 'estoque_' . $acao);
    flash('danger', 'Não foi possível registrar a movimentação. A operação foi cancelada.');
}

redirecionar('estoque/index.php');