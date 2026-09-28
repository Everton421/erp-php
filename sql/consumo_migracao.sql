-- =====================================================================
-- MIGRAÇÃO: módulo Restaurante -> módulo de Consumo
-- Para bancos JÁ importados com a versão anterior do módulo.
-- Idempotente: pode ser executado mais de uma vez sem efeito colateral.
--
-- O que muda:
--   - permissoes.modulo : 'restaurante' -> 'consumo'
--   - 4 chaves de permissão renomeadas
--   - logs.modulo      : registros antigos passam a exibir 'consumo'
--   - perfis           : Garçom -> Atendente, Cozinha -> Produção
--   - configs          : cria consumo_nome (default "Consumo")
--   - usuarios         : seed 'garcom' -> 'atendente'
--
-- O que NÃO muda (de propósito):
--   - tabela `comandas` e a coluna `garcom_id` (schema estável em produção)
--   - chaves cardapio_*, mesas_*, comandas_*, cozinha_*
--
-- NOTA DE COLLATION: permissoes/perfis/logs/usuarios/configs são latin1_swedish_ci.
-- Toda comparação com literal acentuado precisa de CONVERT(... USING utf8mb4),
-- senão o MariaDB devolve erro 1267 (Illegal mix of collations).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. CHAVES DE PERMISSÃO
--    `chave` tem índice UNIQUE e os vínculos (perfil_permissoes,
--    usuario_permissoes) referenciam `permissoes.id` numérico — trocar
--    a chave no lugar preserva todos os vínculos já configurados.
-- ---------------------------------------------------------------------

UPDATE `permissoes` SET `chave` = 'consumo_ver'         WHERE `chave` = 'restaurante_ver';
UPDATE `permissoes` SET `chave` = 'consumo_relatorios'  WHERE `chave` = 'restaurante_relatorios';
UPDATE `permissoes` SET `chave` = 'caixa_consumo_ver'   WHERE `chave` = 'caixa_restaurante_ver';
UPDATE `permissoes` SET `chave` = 'caixa_consumo_pagar' WHERE `chave` = 'caixa_restaurante_pagar';

UPDATE `permissoes` SET `modulo` = 'consumo' WHERE `modulo` = 'restaurante';
UPDATE `logs`      SET `modulo` = 'consumo' WHERE `modulo` = 'restaurante';

-- ---------------------------------------------------------------------
-- 2. DESCRIÇÕES DAS PERMISSÕES
--    'Consumo' é o padrão; quem já personalizou `consumo_nome` pode
--    ajustar os rótulos na tela de Usuários/Perfis depois.
-- ---------------------------------------------------------------------

UPDATE `permissoes` SET `descricao` = CONVERT('Consumo - Visualizar painel' USING utf8mb4)
 WHERE `chave` = 'consumo_ver';
UPDATE `permissoes` SET `descricao` = CONVERT('Produção - Visualizar pedidos' USING utf8mb4)
 WHERE `chave` = 'cozinha_ver';
UPDATE `permissoes` SET `descricao` = CONVERT('Produção - Alterar status' USING utf8mb4)
 WHERE `chave` = 'cozinha_alterar_status';
UPDATE `permissoes` SET `descricao` = CONVERT('Caixa do consumo - Visualizar' USING utf8mb4)
 WHERE `chave` = 'caixa_consumo_ver';
UPDATE `permissoes` SET `descricao` = CONVERT('Caixa do consumo - Receber pagamento' USING utf8mb4)
 WHERE `chave` = 'caixa_consumo_pagar';
UPDATE `permissoes` SET `descricao` = CONVERT('Consumo - Relatórios' USING utf8mb4)
 WHERE `chave` = 'consumo_relatorios';

-- ---------------------------------------------------------------------
-- 3. PERFIS
-- ---------------------------------------------------------------------

UPDATE `perfis` SET `nome` = 'Atendente'
 WHERE CONVERT(`nome` USING utf8mb4) = CONVERT('Garçom' USING utf8mb4);
UPDATE `perfis` SET `nome` = 'Produção'
 WHERE CONVERT(`nome` USING utf8mb4) = CONVERT('Cozinha' USING utf8mb4);

UPDATE `perfis` SET `descricao` = CONVERT('Operação do salão: comandas, itens e fechamento de mesa' USING utf8mb4)
 WHERE `nome` = 'Atendente';
UPDATE `perfis` SET `descricao` = CONVERT('Produção: fila de pedidos e status de preparo' USING utf8mb4)
 WHERE `nome` = 'Produção';
UPDATE `perfis` SET `descricao` = CONVERT('Recebimento de comandas e relatórios do módulo de consumo' USING utf8mb4)
 WHERE `nome` = 'Caixa';

-- ---------------------------------------------------------------------
-- 4. NOMES EXIBIDOS DO MÓDULO
--    O ON DUPLICATE não sobrescreve: um nome já personalizado é preservado.
-- ---------------------------------------------------------------------

INSERT INTO `configs` (`chave`, `valor`, `atualizada_em`) VALUES
('consumo_nome', 'Consumo', NOW()),
('consumo_producao_nome', 'Produção', NOW())
ON DUPLICATE KEY UPDATE `chave` = `chave`;

-- ---------------------------------------------------------------------
-- 5. USUÁRIOS DO SEED
--    Cada linha é filtrada pelo usuário ANTIGO, então um atendente real
--    já cadastrado nunca é tocado. Senha e is_admin não mudam.
--    Se algum destes usuários já existir com o nome novo, o UNIQUE de
--    `usuario` faz o UPDATE falhar — nesse caso renomeie antes à mão.
-- ---------------------------------------------------------------------

UPDATE `usuarios` SET `usuario` = 'atendente', `email` = 'atendente@empresa.com'
 WHERE `usuario` = 'garcom';
UPDATE `usuarios` SET `usuario` = 'producao',  `email` = 'producao@empresa.com'
 WHERE `usuario` = 'cozinha';

-- ---------------------------------------------------------------------
-- 6. VERIFICAÇÃO
-- ---------------------------------------------------------------------

-- SELECT modulo, chave, descricao FROM permissoes
--  WHERE modulo = 'consumo' ORDER BY chave;
-- SELECT DISTINCT modulo FROM logs WHERE modulo LIKE '%consumo%';
-- SELECT nome FROM perfis
--  WHERE CONVERT(nome USING utf8mb4) IN (CONVERT('Atendente' USING utf8mb4),
--                                       CONVERT('Produção' USING utf8mb4),
--                                       CONVERT('Caixa' USING utf8mb4));
-- SELECT usuario, email FROM usuarios WHERE usuario IN ('atendente','producao','caixa');
-- SELECT chave, valor FROM configs WHERE chave = 'consumo_nome';
