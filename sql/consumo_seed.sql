-- =====================================================================
-- Módulo de Consumo — dados de exemplo (OPCIONAL)
-- Rode APÓS sql/consumo.sql
--
--   6 categorias de cardápio, 22 itens, 10 mesas e 3 usuários
--   (atendente / producao / caixa), todos com a senha: senha123
--
-- Idempotente: cada bloco só insere o que ainda não existe.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Categorias do cardápio
-- ---------------------------------------------------------------------

INSERT INTO `cardapio_categorias` (`nome`, `cor`, `ordem`)
SELECT novos.nome, novos.cor, novos.ordem
  FROM (
              SELECT 'Entradas'         AS nome, '#f7a541' AS cor, 10 AS ordem
    UNION ALL SELECT 'Pratos principais', '#4f6ef7'     , 20
    UNION ALL SELECT 'Massas'           , '#00c9a7'     , 30
    UNION ALL SELECT 'Pizzas'           , '#ff6b81'     , 40
    UNION ALL SELECT 'Sobremesas'       , '#a855f7'     , 50
    UNION ALL SELECT 'Bebidas'          , '#0ea5e9'     , 60
  ) novos
 WHERE NOT EXISTS (
           SELECT 1 FROM `cardapio_categorias` c WHERE c.nome = novos.nome
       );

-- ---------------------------------------------------------------------
-- Itens do cardápio
--   tempo_preparo em minutos — usado pela fila da cozinha
-- ---------------------------------------------------------------------

INSERT INTO `cardapio_itens`
  (`categoria_id`, `codigo`, `descricao`, `descricao_complementar`, `preco`, `tempo_preparo`, `observacoes`)
SELECT cat.id, novos.codigo, novos.descricao, novos.complementar,
       novos.preco, novos.tempo, novos.observacao
  FROM (
              SELECT 'E01' AS codigo, 'Entradas' AS categoria, 'Pão de Garlic' AS descricao,
                     'Pão francês com pasta de alho e ervas' AS complementar, 12.90 AS preco,
                     8 AS tempo, NULL AS observacao
    UNION ALL SELECT 'E02', 'Entradas', 'Bruschetta', 'Tomate, manjericão e azeite', 16.90, 10, 'Sem cebola a pedido'
    UNION ALL SELECT 'E03', 'Entradas', 'Batata frita rústica', 'Com páprica e alecrim', 22.90, 12, NULL
    UNION ALL SELECT 'E04', 'Entradas', 'Isca de peixe', 'Filé de tilápia empanado, molho tartaro', 39.90, 18, NULL
    UNION ALL SELECT 'E05', 'Entradas', 'Salmão grelhado', 'Molho de limão siciliano', 49.90, 20, NULL
    UNION ALL SELECT 'P01', 'Pratos principais', 'Frango grelhado', 'File, arroz, feijão e salada', 42.90, 22, NULL
    UNION ALL SELECT 'P02', 'Pratos principais', 'Costela bovina', 'Meio kg, molho barbecue', 89.90, 30, 'Serve 1 pessoa'
    UNION ALL SELECT 'P03', 'Pratos principais', 'Filé à parisiana', 'Filé empanado com molho branco e batata', 46.90, 25, NULL
    UNION ALL SELECT 'P04', 'Pratos principais', 'Peixe grelhado', 'Tilápia, arroz, farofa e salada', 48.90, 22, NULL
    UNION ALL SELECT 'M01', 'Massas', 'Espaguete ao sugo', 'Molho de tomate com manjericão', 32.90, 18, NULL
    UNION ALL SELECT 'M02', 'Massas', 'Fettuccine ao creme', 'Frango, creme de leite e parmesão', 36.90, 18, 'Parmesão ou mussarela'
    UNION ALL SELECT 'M03', 'Massas', 'Lasanha da casa', 'Carne, molho branco e queijo', 44.90, 25, NULL
    UNION ALL SELECT 'M04', 'Massas', 'Penne ao alho e óleo', 'Alho, azeite, salsinha e parmesão', 34.90, 16, NULL
    UNION ALL SELECT 'Z01', 'Pizzas', 'Marguerita', 'Molho, mussarela e manjericão', 42.90, 20, NULL
    UNION ALL SELECT 'Z02', 'Pizzas', 'Calabresa', 'Molho, mussarela e calabresa acebolada', 48.90, 20, NULL
    UNION ALL SELECT 'Z03', 'Pizzas', 'Quatro queijos', 'Molho, mussarela, gorgonzola, catupiry e parmesão', 52.90, 22, NULL
    UNION ALL SELECT 'Z04', 'Pizzas', 'Portuguesa', 'Molho, mussarela, ham, ovos, cebola e azeitona', 54.90, 22, NULL
    UNION ALL SELECT 'S01', 'Sobremesas', 'Pudim', 'Calda de caramelo', 14.90, 5, NULL
    UNION ALL SELECT 'S02', 'Sobremesas', 'Pão de mel', 'Com sorvete de creme', 12.90, 5, 'Servir com sorvete'
    UNION ALL SELECT 'S03', 'Sobremesas', 'Mousse de maracujá', NULL, 13.90, 5, NULL
    UNION ALL SELECT 'B01', 'Bebidas', 'Refrigerante lata', 'Coca-Cola, Guaraná ou Fanta', 7.00, 2, NULL
    UNION ALL SELECT 'B02', 'Bebidas', 'Suco natural', 'Laranja, limão, maracujá ou mamão', 10.90, 5, '300 ml'
    UNION ALL SELECT 'B03', 'Bebidas', 'Cerveja long neck', 'Budweiser, Original ou Heineken', 14.90, 2, 'Gelada'
  ) novos
  LEFT JOIN `cardapio_categorias` cat ON cat.nome = novos.categoria
 WHERE cat.id IS NOT NULL
   AND NOT EXISTS (
           SELECT 1 FROM `cardapio_itens` i WHERE i.codigo = novos.codigo
       );

-- ---------------------------------------------------------------------
-- Mesas
-- ---------------------------------------------------------------------

INSERT INTO `mesas` (`numero`, `nome`, `capacidade`)
SELECT novos.numero, novos.nome, novos.capacidade
  FROM (
              SELECT  1 AS numero, 'Salão principal' AS nome, 4 AS capacidade
    UNION ALL SELECT  2, 'Salão principal', 4
    UNION ALL SELECT  3, 'Salão principal', 2
    UNION ALL SELECT  4, 'Salão principal', 6
    UNION ALL SELECT  5, 'Salão principal', 4
    UNION ALL SELECT  6, 'Varanda'        , 4
    UNION ALL SELECT  7, 'Varanda'        , 2
    UNION ALL SELECT  8, 'Varanda'        , 6
    UNION ALL SELECT  9, 'Área interna'   , 8
    UNION ALL SELECT 10, 'Área interna'   , 4
  ) novos
 WHERE NOT EXISTS (
           SELECT 1 FROM `mesas` m WHERE m.numero = novos.numero
       );

-- ---------------------------------------------------------------------
-- Usuários de exemplo
--   senha de todos: senha123
-- ---------------------------------------------------------------------

-- Os CONVERT resolvem o convívio entre as colunas latin1 do banco legado e os
-- literais utf8mb4 deste arquivo (evita "Illegal mix of collations").
INSERT INTO `usuarios` (`nome`, `usuario`, `email`, `senha`, `perfil_id`, `is_admin`, `status`)
SELECT novos.nome, novos.usuario, novos.email, novos.senha, pf.id, 0, 1
  FROM (
              SELECT 'Ana Souza'     AS nome, 'atendente' AS usuario, 'atendente@empresa.com' AS email, 'Atendente' AS perfil,
                     '$2y$10$DjE.v4ZaBWk.MIMnUA84Tu/1R3XghDws1YBbk3Wzy6QsskZB.zQXq' AS senha
    UNION ALL SELECT 'Bruno Lima'   , 'caixa'    , 'caixa@empresa.com'      , 'Caixa'     , '$2y$10$DjE.v4ZaBWk.MIMnUA84Tu/1R3XghDws1YBbk3Wzy6QsskZB.zQXq'
    UNION ALL SELECT 'Carla Dias'   , 'producao' , 'producao@empresa.com'   , 'Produção'  , '$2y$10$DjE.v4ZaBWk.MIMnUA84Tu/1R3XghDws1YBbk3Wzy6QsskZB.zQXq'
  ) novos
  LEFT JOIN `perfis` pf ON CONVERT(pf.nome USING utf8mb4) = CONVERT(novos.perfil USING utf8mb4)
 WHERE pf.id IS NOT NULL
   AND NOT EXISTS (
           SELECT 1 FROM `usuarios` u WHERE CONVERT(u.usuario USING utf8mb4) = CONVERT(novos.usuario USING utf8mb4)
       );

-- ---------------------------------------------------------------------
-- Confirmação (opcional): lista quem ficou com acesso ao módulo de consumo
-- ---------------------------------------------------------------------

-- SELECT pf.nome AS perfil, COUNT(*) AS permissoes
--   FROM perfil_permissoes pp
--   JOIN perfis pf ON pf.id = pp.perfil_id
--   JOIN permissoes pm ON pm.id = pp.permissao_id
--  WHERE pm.chave LIKE '%consumo%' OR pm.chave LIKE 'cardapio%'
--     OR pm.chave LIKE 'mesas%' OR pm.chave LIKE 'comandas%'
--     OR pm.chave LIKE 'cozinha%' OR pm.chave LIKE 'caixa_consumo%'
--  GROUP BY pf.nome;
