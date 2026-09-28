-- =====================================================================
-- Módulo de Consumo — schema (tabelas, permissões e perfis)
-- Serve a restaurantes, bares, lanchonetes, salões e qualquer outro
-- estabelecimento que trabalhe com comandas.
-- Compatível com o banco configurado em config/config.php (DB_NAME).
-- Script idempotente: pode ser executado mais de uma vez sem erro.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. CARDÁPIO
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cardapio_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(60) NOT NULL,
  `cor` varchar(7) NOT NULL DEFAULT '#4f6ef7',
  `ordem` smallint(5) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cc_nome` (`nome`),
  KEY `ix_cc_ativo` (`ativo`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cardapio_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) DEFAULT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `descricao` varchar(150) NOT NULL,
  `descricao_complementar` varchar(255) DEFAULT NULL,
  `preco` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tempo_preparo` smallint(5) unsigned DEFAULT NULL,
  `observacoes` varchar(255) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ci_codigo` (`codigo`),
  KEY `ix_ci_categoria` (`categoria_id`),
  KEY `ix_ci_descricao` (`descricao`),
  KEY `ix_ci_ativo` (`ativo`),
  CONSTRAINT `fk_ci_cat` FOREIGN KEY (`categoria_id`)
    REFERENCES `cardapio_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. MESAS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `mesas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` smallint(5) unsigned NOT NULL,
  `nome` varchar(40) DEFAULT NULL,
  `capacidade` smallint(5) unsigned NOT NULL DEFAULT 4,
  `status` enum('LIVRE','OCUPADA','RESERVADA') NOT NULL DEFAULT 'LIVRE',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mesas_numero` (`numero`),
  KEY `ix_mesas_status` (`status`, `ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. COMANDAS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `comandas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `mesa_id` int(11) NOT NULL,
  `garcom_id` int(11) NOT NULL,
  `status` enum('ABERTA','FECHADA','CANCELADA') NOT NULL DEFAULT 'ABERTA',
  `data_abertura` datetime NOT NULL DEFAULT current_timestamp(),
  `data_fechamento` datetime DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `acrescimo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacao` varchar(255) DEFAULT NULL,
  `status_pagamento` enum('PENDENTE','PARCIAL','PAGO') NOT NULL DEFAULT 'PENDENTE',
  `data_pagamento` datetime DEFAULT NULL,
  `forma_pagamento_id` int(11) DEFAULT NULL,
  `valor_pago` decimal(15,2) NOT NULL DEFAULT 0.00,
  `troco` decimal(15,2) NOT NULL DEFAULT 0.00,
  `fechado_por` int(11) DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `cancelado_em` datetime DEFAULT NULL,
  `motivo_cancelamento` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_comandas_numero` (`numero`),
  KEY `ix_cm_mesa` (`mesa_id`),
  KEY `ix_cm_garcom` (`garcom_id`),
  KEY `ix_cm_status` (`status`, `data_abertura`),
  KEY `ix_cm_pagamento` (`status_pagamento`, `data_fechamento`),
  KEY `ix_cm_fechamento` (`data_fechamento`),
  CONSTRAINT `fk_cm_mesa` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`),
  CONSTRAINT `fk_cm_garcom` FOREIGN KEY (`garcom_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cm_fechado_por` FOREIGN KEY (`fechado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cm_cancelado_por` FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cm_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. ITENS DA COMANDA (pedidos)
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `comanda_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comanda_id` int(11) NOT NULL,
  `cardapio_item_id` int(11) DEFAULT NULL,
  `descricao` varchar(150) NOT NULL,
  `quantidade` decimal(12,3) NOT NULL DEFAULT 1.000,
  `preco_unitario` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `acrescimo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('PENDENTE','PREPARANDO','PRONTO','ENTREGUE','CANCELADO') NOT NULL DEFAULT 'PENDENTE',
  `observacoes` varchar(255) DEFAULT NULL,
  `preparador_id` int(11) DEFAULT NULL,
  `entregue_por` int(11) DEFAULT NULL,
  `data_pedido` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_it_comanda` (`comanda_id`),
  KEY `ix_it_status` (`status`, `data_pedido`),
  KEY `ix_it_cardapio` (`cardapio_item_id`),
  KEY `ix_it_data` (`data_pedido`),
  CONSTRAINT `fk_it_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_it_cardapio` FOREIGN KEY (`cardapio_item_id`) REFERENCES `cardapio_itens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_it_preparador` FOREIGN KEY (`preparador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_it_entregue_por` FOREIGN KEY (`entregue_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. PAGAMENTOS DA COMANDA
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `comanda_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comanda_id` int(11) NOT NULL,
  `forma_pagamento_id` int(11) NOT NULL,
  `valor` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qtde_parcelas` smallint(5) unsigned NOT NULL DEFAULT 1,
  `valor_recebido` decimal(15,2) NOT NULL DEFAULT 0.00,
  `data_pagamento` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pg_comanda` (`comanda_id`),
  KEY `ix_pg_forma` (`forma_pagamento_id`),
  KEY `ix_pg_data` (`data_pagamento`),
  CONSTRAINT `fk_pg_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pg_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. PERMISSÕES DO MÓDULO
--    Inserção por `chave` (UNIQUE) sem fixar `id`, para não colidir com
--    permissões criadas fora deste script. Os vínculos abaixo resolvem os
--    ids por `chave`, portanto o script pode ser rodado novamente.
-- ---------------------------------------------------------------------

INSERT INTO `permissoes` (`modulo`, `chave`, `descricao`) VALUES
('consumo', 'consumo_ver',            'Consumo - Visualizar painel'),
('consumo', 'cardapio_ver',           'Cardápio - Visualizar'),
('consumo', 'cardapio_editar',        'Cardápio - Criar/editar'),
('consumo', 'mesas_ver',              'Mesas - Visualizar'),
('consumo', 'mesas_editar',           'Mesas - Criar/editar'),
('consumo', 'mesas_excluir',          'Mesas - Excluir'),
('consumo', 'comandas_ver',           'Comandas - Visualizar'),
('consumo', 'comandas_ver_todas',     'Comandas - Ver todas (não só as próprias)'),
('consumo', 'comandas_criar',         'Comandas - Abrir'),
('consumo', 'comandas_item',          'Comandas - Adicionar/remover itens'),
('consumo', 'comandas_fechar',        'Comandas - Fechar'),
('consumo', 'comandas_cancelar',      'Comandas - Cancelar'),
('consumo', 'comandas_desconto',      'Comandas - Aplicar desconto/acréscimo'),
('consumo', 'comandas_imprimir',      'Comandas - Imprimir cupom'),
('consumo', 'cozinha_ver',            'Produção - Visualizar pedidos'),
('consumo', 'cozinha_alterar_status', 'Produção - Alterar status'),
('consumo', 'caixa_consumo_ver',      'Caixa do consumo - Visualizar'),
('consumo', 'caixa_consumo_pagar',    'Caixa do consumo - Receber pagamento'),
('consumo', 'consumo_relatorios',     'Consumo - Relatórios')
ON DUPLICATE KEY UPDATE
  `modulo` = VALUES(`modulo`),
  `descricao` = VALUES(`descricao`);

-- ---------------------------------------------------------------------
-- 7. PERFIS DO MÓDULO
--    Inserção por `nome` (UNIQUE) sem fixar `id`. Os vínculos resolvem
--    os ids por `nome`.
-- ---------------------------------------------------------------------

INSERT INTO `perfis` (`nome`, `descricao`) VALUES
('Atendente', 'Operação do salão: comandas, itens e fechamento de mesa'),
('Produção',  'Produção: fila de pedidos e status de preparo'),
('Caixa',     'Recebimento de comandas e relatórios do módulo de consumo')
ON DUPLICATE KEY UPDATE
  `descricao` = VALUES(`descricao`);

-- Atendente: painel, cardápio, mesas, comandas (sem ver as alheias, sem cancelar)
INSERT INTO `perfil_permissoes` (`perfil_id`, `permissao_id`, `permitido`)
SELECT pf.id, pm.id, 1
  FROM `perfis` pf
  CROSS JOIN `permissoes` pm
 WHERE pf.nome = 'Atendente'
   AND pm.chave IN (
       'consumo_ver', 'cardapio_ver', 'mesas_ver', 'comandas_ver',
       'comandas_criar', 'comandas_item', 'comandas_fechar', 'comandas_imprimir'
   )
ON DUPLICATE KEY UPDATE `permitido` = 1;

-- Produção: painel, cardápio, mesas, fila de produção
INSERT INTO `perfil_permissoes` (`perfil_id`, `permissao_id`, `permitido`)
SELECT pf.id, pm.id, 1
  FROM `perfis` pf
  CROSS JOIN `permissoes` pm
 WHERE pf.nome = 'Produção'
   AND pm.chave IN (
       'consumo_ver', 'cardapio_ver', 'mesas_ver',
       'cozinha_ver', 'cozinha_alterar_status'
   )
ON DUPLICATE KEY UPDATE `permitido` = 1;

-- Caixa: painel, cardápio, mesas, todas as comandas, recebimento e relatórios
INSERT INTO `perfil_permissoes` (`perfil_id`, `permissao_id`, `permitido`)
SELECT pf.id, pm.id, 1
  FROM `perfis` pf
  CROSS JOIN `permissoes` pm
 WHERE pf.nome = 'Caixa'
   AND pm.chave IN (
       'consumo_ver', 'cardapio_ver', 'mesas_ver', 'comandas_ver',
       'comandas_ver_todas', 'comandas_imprimir', 'caixa_consumo_ver',
       'caixa_consumo_pagar', 'consumo_relatorios'
   )
ON DUPLICATE KEY UPDATE `permitido` = 1;

-- ---------------------------------------------------------------------
-- 8. NOME EXIBIDO DO MÓDULO
--    Editável em Configurações; o código usa "Consumo" quando vazio.
--    O ON DUPLICATE propositalmente não sobrescreve: quem já personalizou
--    o nome (ex.: "Bar", "Salão", "Lanchonete") mantém a escolha.
-- ---------------------------------------------------------------------

INSERT INTO `configs` (`chave`, `valor`, `atualizada_em`) VALUES
('consumo_nome', 'Consumo', NOW())
ON DUPLICATE KEY UPDATE `chave` = `chave`;
