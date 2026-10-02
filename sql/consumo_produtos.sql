-- =====================================================================
-- MIGRAÇÃO: catálogo do Consumo passa a usar os produtos do sistema
-- Para bancos JÁ importados. Idempotente: pode rodar mais de uma vez.
--
-- O que muda:
--   - comanda_itens.produto_id (novo) + índice ix_it_produto
--   - rótulos das permissões cardapio_ver / cardapio_editar
--
-- O que NÃO muda (de propósito):
--   - tabelas cardapio_itens / cardapio_categorias e seus 23 itens: ficam
--     no banco, sem vínculo com as comandas novas (cardapio_item_id = NULL)
--   - comanda_itens.cardapio_item_id: mantido para as comandas já abertas
--   - descricao, preco_unitario, subtotal e total continuam sendo snapshot
--
-- NOTA DE COLLATION: `produtos` e `categorias` são latin1_swedish_ci.
-- Toda comparação com literal utf8mb4 precisa de CONVERT(... USING utf8mb4).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. COLUNA produto_id
--    Sem FK de propósito: `produtos` é legada em latin1 e a restrição
--    misturaria engines/collações desnecessariamente. O item guarda o
--    snapshot de descrição e preço, então apagar o produto não afeta a
--    comanda.
-- ---------------------------------------------------------------------

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'comanda_itens'
     AND COLUMN_NAME = 'produto_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `comanda_itens` ADD COLUMN `produto_id` int(11) DEFAULT NULL AFTER `comanda_id`',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. ÍNDICE ix_it_produto
-- ---------------------------------------------------------------------

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'comanda_itens'
     AND INDEX_NAME = 'ix_it_produto'
);
SET @sql := IF(
  @idx = 0,
  'ALTER TABLE `comanda_itens` ADD KEY `ix_it_produto` (`produto_id`)',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3. RÓTULOS DAS PERMISSÕES
--    As chaves cardapio_* continuam (perfis e vínculos usam o id numérico),
--    só o texto exibido passa a dizer "Produtos".
-- ---------------------------------------------------------------------

UPDATE `permissoes` SET `descricao` = CONVERT('Produtos do consumo - Visualizar' USING utf8mb4)
 WHERE `chave` = 'cardapio_ver';
UPDATE `permissoes` SET `descricao` = CONVERT('Produtos do consumo - Manter' USING utf8mb4)
 WHERE `chave` = 'cardapio_editar';

-- ---------------------------------------------------------------------
-- 4. VERIFICAÇÃO
-- ---------------------------------------------------------------------

-- SHOW COLUMNS FROM comanda_itens LIKE 'produto_id';
-- SELECT chave, descricao FROM permissoes
--  WHERE modulo = 'consumo' AND chave IN ('cardapio_ver','cardapio_editar');
-- SELECT id, comanda_id, produto_id, cardapio_item_id, descricao, status
--   FROM comanda_itens ORDER BY id;
