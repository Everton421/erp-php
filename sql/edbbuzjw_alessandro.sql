-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 25/09/2026 às 16:40
-- Versão do servidor: 10.11.10-MariaDB-cll-lve
-- Versão do PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `edbbuzjw_alessandro`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `criado_em`) VALUES
(1, 'VIDROS', '2026-09-10 16:16:32'),
(2, 'GERAL', '2026-09-10 16:26:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias_financeiras`
--

CREATE TABLE `categorias_financeiras` (
  `id` int(11) NOT NULL,
  `tipo` enum('RECEITA','DESPESA') NOT NULL,
  `nome` varchar(80) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `categorias_financeiras`
--

INSERT INTO `categorias_financeiras` (`id`, `tipo`, `nome`, `criado_em`) VALUES
(1, 'RECEITA', 'Vendas', '2026-09-10 15:15:33'),
(2, 'RECEITA', 'Serviços', '2026-09-10 15:15:33'),
(3, 'RECEITA', 'Outros', '2026-09-10 15:15:33'),
(4, 'DESPESA', 'Aluguel', '2026-09-10 15:15:33'),
(5, 'DESPESA', 'Energia', '2026-09-10 15:15:33'),
(6, 'DESPESA', 'Internet', '2026-09-10 15:15:33'),
(7, 'DESPESA', 'Salários', '2026-09-10 15:15:33'),
(8, 'DESPESA', 'Impostos', '2026-09-10 15:15:33'),
(9, 'DESPESA', 'Fornecedores', '2026-09-10 15:15:33'),
(10, 'DESPESA', 'Combustível', '2026-09-10 15:15:33'),
(11, 'DESPESA', 'Manutenção', '2026-09-10 15:15:33'),
(12, 'DESPESA', 'Outros', '2026-09-10 15:15:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `tipo` enum('FISICA','JURIDICA') NOT NULL DEFAULT 'FISICA',
  `nome` varchar(150) NOT NULL,
  `nome_fantasia` varchar(150) DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `nascimento` date DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `endereco` varchar(150) DEFAULT NULL,
  `numero` varchar(15) DEFAULT NULL,
  `complemento` varchar(80) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `codigo`, `tipo`, `nome`, `nome_fantasia`, `documento`, `inscricao_estadual`, `nascimento`, `email`, `telefone`, `celular`, `cep`, `endereco`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `observacoes`, `status`, `criado_em`) VALUES
(1, 'C00001', 'FISICA', 'TESTE', '', '', '', NULL, '', '', '', '', '', '', '', '', '', '', '', 1, '2026-09-13 12:16:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `compras`
--

CREATE TABLE `compras` (
  `id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `tipo_pedido_id` int(11) DEFAULT NULL,
  `data_compra` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `acrescimo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('FINALIZADA','CANCELADA') NOT NULL DEFAULT 'FINALIZADA',
  `observacao` text DEFAULT NULL,
  `criado_por` int(11) NOT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `cancelado_em` datetime DEFAULT NULL,
  `motivo_cancelamento` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compra_itens`
--

CREATE TABLE `compra_itens` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `custo_unitario` decimal(15,2) NOT NULL,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compra_pagamentos`
--

CREATE TABLE `compra_pagamentos` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `forma_pagamento_id` int(11) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `qtde_parcelas` int(11) NOT NULL DEFAULT 1,
  `data_pagamento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compra_parcelas`
--

CREATE TABLE `compra_parcelas` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `compra_pagamento_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `vencimento` date NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `status` enum('PENDENTE','PAGO','VENCIDO','CANCELADO','PARCIAL') NOT NULL DEFAULT 'PENDENTE'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configs`
--

CREATE TABLE `configs` (
  `chave` varchar(60) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizada_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `configs`
--

INSERT INTO `configs` (`chave`, `valor`, `atualizada_em`) VALUES
('atualizar_custo_compra', '1', '2026-09-10 16:18:02'),
('cadastro_clientes_sem_cpf', '1', NULL),
('casas_decimais', '2', '2026-09-10 16:18:02'),
('compra_forma_pagamento_padrao', '5', '2026-09-10 16:18:02'),
('compra_tipo_pedido_padrao', '5', '2026-09-10 16:18:02'),
('dias_vencimento', '30', '2026-09-10 16:18:02'),
('empresa_cnpj', '', '2026-09-10 16:18:02'),
('empresa_email', '', '2026-09-10 16:18:02'),
('empresa_endereco', '', '2026-09-10 16:18:02'),
('empresa_logo', '', NULL),
('empresa_nome', 'Minha Empresa LTDA', '2026-09-10 16:18:02'),
('empresa_telefone', '', '2026-09-10 16:18:02'),
('estoque_negativo', '0', '2026-09-10 16:18:02'),
('juros_padrao', '1.000', '2026-09-10 16:18:02'),
('licenca_chave', '', NULL),
('licenca_instalacao', 'b7627bce-399b-9d36-64c3-6b270385c830', '2026-09-10 16:15:48'),
('licenca_instalado_em', '2026-09-10 16:15:48', '2026-09-10 16:15:48'),
('moeda_simbolo', 'R$', '2026-09-10 16:18:02'),
('multa_padrao', '2.000', '2026-09-10 16:18:02'),
('nota_rodape_venda', '', '2026-09-10 16:18:02'),
('venda_exige_cliente', '0', '2026-09-10 16:18:02'),
('venda_forma_pagamento_padrao', '1', '2026-09-10 16:18:02'),
('venda_tipo_pedido_padrao', '1', '2026-09-10 16:18:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_pagar`
--

CREATE TABLE `contas_pagar` (
  `id` int(11) NOT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `compra_id` int(11) DEFAULT NULL,
  `compra_parcela_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `documento` varchar(40) DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `parcela_numero` varchar(10) DEFAULT NULL,
  `valor` decimal(15,2) NOT NULL,
  `vencimento` date NOT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `valor_pago` decimal(15,2) NOT NULL DEFAULT 0.00,
  `juros` decimal(15,2) NOT NULL DEFAULT 0.00,
  `multa` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('PENDENTE','PAGO','VENCIDO','CANCELADO','PARCIAL') NOT NULL DEFAULT 'PENDENTE',
  `forma_pagamento_id` int(11) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_receber`
--

CREATE TABLE `contas_receber` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `venda_parcela_id` int(11) DEFAULT NULL,
  `documento` varchar(40) DEFAULT NULL,
  `parcela_numero` varchar(10) DEFAULT NULL,
  `valor` decimal(15,2) NOT NULL,
  `vencimento` date NOT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `valor_pago` decimal(15,2) NOT NULL DEFAULT 0.00,
  `juros` decimal(15,2) NOT NULL DEFAULT 0.00,
  `multa` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('PENDENTE','PAGO','VENCIDO','CANCELADO','PARCIAL') NOT NULL DEFAULT 'PENDENTE',
  `forma_pagamento_id` int(11) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_movimentos`
--

CREATE TABLE `estoque_movimentos` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `compra_id` int(11) DEFAULT NULL,
  `tipo` enum('ENTRADA','SAIDA','AJUSTE') NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `estoque_anterior` decimal(12,3) NOT NULL,
  `estoque_posterior` decimal(12,3) NOT NULL,
  `custo` decimal(15,2) DEFAULT NULL,
  `motivo` varchar(40) DEFAULT NULL,
  `documento` varchar(40) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `data` datetime NOT NULL DEFAULT current_timestamp(),
  `observacao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fluxo_caixa`
--

CREATE TABLE `fluxo_caixa` (
  `id` int(11) NOT NULL,
  `data_movimento` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` enum('ENTRADA','SAIDA') NOT NULL,
  `categoria` enum('VENDA','RECEBIMENTO','COMPRA','CONTA','DESPESA','OUTRO') NOT NULL DEFAULT 'OUTRO',
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `referencia` varchar(60) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `estorno` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `formas_pagamento`
--

CREATE TABLE `formas_pagamento` (
  `id` int(11) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `formas_pagamento`
--

INSERT INTO `formas_pagamento` (`id`, `nome`, `ativo`) VALUES
(1, 'Dinheiro', 1),
(2, 'PIX', 1),
(3, 'Cartão de crédito', 1),
(4, 'Cartão de débito', 1),
(5, 'Boleto', 1),
(6, 'Transferência', 1),
(7, 'Crediário', 1),
(8, 'Outros', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `fornecedores`
--

CREATE TABLE `fornecedores` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `razao_social` varchar(150) NOT NULL,
  `nome_fantasia` varchar(150) DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `endereco` varchar(150) DEFAULT NULL,
  `numero` varchar(15) DEFAULT NULL,
  `complemento` varchar(80) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `fornecedores`
--

INSERT INTO `fornecedores` (`id`, `codigo`, `razao_social`, `nome_fantasia`, `documento`, `inscricao_estadual`, `email`, `telefone`, `celular`, `cep`, `endereco`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `observacoes`, `status`, `criado_em`) VALUES
(1, 'F00001', 'FORNECEDOR EXEMPLO LTDA', 'FORN EX TESTE', '11222333000181', 'IE 123 TESTE', 'Contato@FornecedorTeste.COM.BR', '(11) 3333-0000', '', '01310100', 'AVENIDA PAULISTA', '1000', 'SALA 5', 'BELA VISTA', 'SÃO PAULO', 'SP', 'FORNECEDOR DE TESTE', 1, '2026-09-05 18:39:35');

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `data` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `modulo` varchar(40) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_anteriores`)),
  `dados_novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_novos`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `logs`
--

INSERT INTO `logs` (`id`, `usuario_id`, `data`, `ip`, `modulo`, `acao`, `registro_id`, `dados_anteriores`, `dados_novos`) VALUES
(1, 1, '2026-09-10 16:15:52', '177.92.50.139', 'login', 'Login efetuado', 1, NULL, NULL),
(2, 1, '2026-09-10 16:16:32', '177.92.50.139', 'categorias', 'Categoria criada', 1, NULL, '{\"nome\":\"VIDROS\"}'),
(3, 1, '2026-09-10 16:16:39', '177.92.50.139', 'marcas', 'Marca criada', 1, NULL, '{\"nome\":\"GERAL\"}'),
(4, 1, '2026-09-10 16:18:02', '177.92.50.139', 'config', 'Configurações atualizadas', NULL, NULL, NULL),
(5, 1, '2026-09-10 16:26:38', '177.92.50.139', 'categorias', 'Categoria criada', 2, NULL, '{\"nome\":\"GERAL\"}'),
(6, 1, '2026-09-11 17:08:22', '177.220.132.206', 'login', 'Login efetuado', 1, NULL, NULL),
(7, NULL, '2026-09-13 12:12:03', '187.109.201.132', 'login', 'Senha incorreta #1', 1, NULL, '{\"tentativas\":1}'),
(8, 1, '2026-09-13 12:12:18', '187.109.201.132', 'login', 'Login efetuado', 1, NULL, NULL),
(9, 1, '2026-09-13 12:13:23', '187.109.201.132', 'fornecedores', 'Fornecedor excluído #2', 2, '{\"id\":\"2\",\"codigo\":\"F00002\",\"razao_social\":\"MILLENIUM UTILIDADES DOMESTICAS LTDA\",\"nome_fantasia\":\"DISTRIBUIDORA MILLENIUM\",\"documento\":\"04150555000170\",\"inscricao_estadual\":\"\",\"email\":\"\",\"telefone\":\"4421036000\",\"celular\":\"\",\"cep\":\"13098-321\",\"endereco\":\"AGUACU\",\"numero\":\"171\",\"complemento\":\"BLOCO C SALA 203\",\"bairro\":\"LOTEAMENTO ALPHAVILLE CAMPINAS\",\"cidade\":\"CAMPINAS\",\"estado\":\"SP\",\"observacoes\":\"\",\"status\":\"1\",\"criado_em\":\"2026-09-09 09:46:00\"}', NULL),
(10, 1, '2026-09-13 12:16:33', '187.109.201.132', 'clientes', 'Cliente editado #1', 1, NULL, '{\"nome\":\"TESTE\",\"documento\":\"\",\"tipo\":\"FISICA\"}'),
(11, 1, '2026-09-21 08:53:06', '177.92.48.132', 'login', 'Login efetuado', 1, NULL, NULL),
(12, 1, '2026-09-21 08:53:47', '177.92.48.132', 'login', 'Login efetuado', 1, NULL, NULL),
(13, 1, '2026-09-21 15:37:21', '177.92.48.132', 'login', 'Logout', 1, NULL, NULL),
(14, 1, '2026-09-21 15:37:28', '177.92.48.132', 'login', 'Login efetuado', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `marcas`
--

CREATE TABLE `marcas` (
  `id` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `marcas`
--

INSERT INTO `marcas` (`id`, `nome`, `criado_em`) VALUES
(1, 'GERAL', '2026-09-10 16:16:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `perfil_permissoes`
--

CREATE TABLE `perfil_permissoes` (
  `perfil_id` int(11) NOT NULL,
  `permissao_id` int(11) NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `perfil_permissoes`
--

INSERT INTO `perfil_permissoes` (`perfil_id`, `permissao_id`, `permitido`) VALUES
(1, 1, 1),
(1, 2, 1),
(1, 3, 1),
(1, 4, 1),
(1, 5, 1),
(1, 6, 1),
(1, 7, 1),
(1, 8, 1),
(1, 9, 1),
(1, 10, 1),
(1, 11, 1),
(1, 12, 1),
(1, 13, 1),
(1, 14, 1),
(1, 15, 1),
(1, 16, 1),
(1, 17, 1),
(1, 18, 1),
(1, 19, 1),
(1, 20, 1),
(1, 21, 1),
(1, 22, 1),
(1, 23, 1),
(1, 24, 1),
(1, 25, 1),
(1, 26, 1),
(1, 27, 1),
(1, 28, 1),
(1, 29, 1),
(1, 30, 1),
(1, 31, 1),
(1, 32, 1),
(1, 33, 1),
(1, 34, 1),
(1, 35, 1),
(1, 36, 1),
(1, 37, 1),
(1, 38, 1),
(1, 39, 1),
(1, 40, 1),
(1, 41, 1),
(1, 42, 1),
(1, 43, 1),
(1, 44, 1),
(1, 45, 1),
(1, 46, 1),
(1, 47, 1),
(1, 48, 1),
(1, 49, 1),
(1, 50, 1),
(1, 51, 1),
(1, 52, 1),
(1, 53, 1),
(1, 54, 1),
(1, 55, 1),
(1, 56, 1),
(2, 1, 1),
(2, 2, 1),
(2, 3, 1),
(2, 4, 1),
(2, 5, 1),
(2, 6, 1),
(2, 7, 1),
(2, 8, 1),
(2, 9, 1),
(2, 10, 1),
(2, 11, 1),
(2, 12, 1),
(2, 13, 1),
(2, 14, 1),
(2, 15, 1),
(2, 16, 1),
(2, 17, 1),
(2, 18, 1),
(2, 19, 1),
(2, 20, 1),
(2, 21, 1),
(2, 22, 1),
(2, 23, 1),
(2, 24, 1),
(2, 25, 1),
(2, 26, 1),
(2, 27, 1),
(2, 28, 1),
(2, 29, 1),
(2, 30, 1),
(2, 31, 1),
(2, 32, 1),
(2, 33, 1),
(2, 34, 1),
(2, 35, 1),
(2, 36, 1),
(2, 37, 1),
(2, 38, 1),
(2, 39, 1),
(2, 40, 1),
(2, 41, 1),
(2, 42, 1),
(2, 43, 1),
(2, 44, 1),
(2, 45, 1),
(2, 46, 1),
(2, 47, 1),
(2, 48, 1),
(2, 49, 1),
(3, 1, 1),
(3, 2, 1),
(3, 3, 1),
(3, 4, 1),
(3, 10, 1),
(3, 26, 1),
(3, 30, 1),
(3, 31, 1),
(3, 34, 1),
(3, 35, 1),
(4, 1, 1),
(4, 2, 1),
(4, 6, 1),
(4, 30, 1),
(4, 39, 1),
(4, 40, 1),
(4, 41, 1),
(4, 43, 1),
(4, 44, 1),
(4, 45, 1),
(4, 46, 1),
(4, 48, 1),
(4, 49, 1),
(5, 1, 1),
(5, 10, 1),
(5, 11, 1),
(5, 12, 1),
(5, 14, 1),
(5, 18, 1),
(5, 22, 1),
(5, 26, 1),
(5, 27, 1),
(5, 28, 1),
(5, 29, 1),
(6, 1, 1),
(6, 2, 1),
(6, 6, 1),
(6, 10, 1),
(6, 26, 1),
(6, 30, 1),
(6, 49, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `perfis`
--

CREATE TABLE `perfis` (
  `id` int(11) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `perfis`
--

INSERT INTO `perfis` (`id`, `nome`, `descricao`, `criado_em`) VALUES
(1, 'Administrador', 'Acesso total ao sistema', '2026-09-10 15:15:33'),
(2, 'Gerente', 'Acesso operacional e gerencial sem usuários/config', '2026-09-10 15:15:33'),
(3, 'Vendedor', 'Acesso às vendas, clientes e produtos', '2026-09-10 15:15:33'),
(4, 'Financeiro', 'Acesso ao financeiro, contas e caixa', '2026-09-10 15:15:33'),
(5, 'Estoque', 'Acesso ao estoque, produtos e entradas', '2026-09-10 15:15:33'),
(6, 'Usuário comum', 'Acesso básico de consulta', '2026-09-10 15:15:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissoes`
--

CREATE TABLE `permissoes` (
  `id` int(11) NOT NULL,
  `modulo` varchar(40) NOT NULL,
  `chave` varchar(60) NOT NULL,
  `descricao` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `permissoes`
--

INSERT INTO `permissoes` (`id`, `modulo`, `chave`, `descricao`) VALUES
(1, 'dashboard', 'dashboard_ver', 'Visualizar dashboard'),
(2, 'clientes', 'clientes_ver', 'Clientes - Visualizar'),
(3, 'clientes', 'clientes_criar', 'Clientes - Inserir'),
(4, 'clientes', 'clientes_editar', 'Clientes - Editar'),
(5, 'clientes', 'clientes_excluir', 'Clientes - Excluir'),
(6, 'fornecedores', 'fornecedores_ver', 'Fornecedores - Visualizar'),
(7, 'fornecedores', 'fornecedores_criar', 'Fornecedores - Inserir'),
(8, 'fornecedores', 'fornecedores_editar', 'Fornecedores - Editar'),
(9, 'fornecedores', 'fornecedores_excluir', 'Fornecedores - Excluir'),
(10, 'produtos', 'produtos_ver', 'Produtos - Visualizar'),
(11, 'produtos', 'produtos_criar', 'Produtos - Inserir'),
(12, 'produtos', 'produtos_editar', 'Produtos - Editar'),
(13, 'produtos', 'produtos_excluir', 'Produtos - Excluir'),
(14, 'cadastros', 'categorias_ver', 'Categorias - Visualizar'),
(15, 'cadastros', 'categorias_criar', 'Categorias - Inserir'),
(16, 'cadastros', 'categorias_editar', 'Categorias - Editar'),
(17, 'cadastros', 'categorias_excluir', 'Categorias - Excluir'),
(18, 'cadastros', 'marcas_ver', 'Marcas - Visualizar'),
(19, 'cadastros', 'marcas_criar', 'Marcas - Inserir'),
(20, 'cadastros', 'marcas_editar', 'Marcas - Editar'),
(21, 'cadastros', 'marcas_excluir', 'Marcas - Excluir'),
(22, 'cadastros', 'unidades_ver', 'Unidades - Visualizar'),
(23, 'cadastros', 'unidades_criar', 'Unidades - Inserir'),
(24, 'cadastros', 'unidades_editar', 'Unidades - Editar'),
(25, 'cadastros', 'unidades_excluir', 'Unidades - Excluir'),
(26, 'estoque', 'estoque_ver', 'Estoque - Visualizar'),
(27, 'estoque', 'estoque_entrada', 'Estoque - Entrada'),
(28, 'estoque', 'estoque_saida', 'Estoque - Saída'),
(29, 'estoque', 'estoque_ajuste', 'Estoque - Ajuste'),
(30, 'vendas', 'vendas_ver', 'Vendas - Visualizar'),
(31, 'vendas', 'vendas_criar', 'Vendas - Criar'),
(32, 'vendas', 'vendas_editar', 'Vendas - Editar'),
(33, 'vendas', 'vendas_cancelar', 'Vendas - Cancelar'),
(34, 'vendas', 'vendas_alterar_preco', 'Vendas - Alterar preço'),
(35, 'vendas', 'vendas_desconto', 'Vendas - Aplicar desconto'),
(36, 'compras', 'compras_ver', 'Compras - Visualizar'),
(37, 'compras', 'compras_criar', 'Compras - Criar'),
(38, 'compras', 'compras_cancelar', 'Compras - Cancelar'),
(39, 'contas', 'contas_receber_ver', 'Contas a receber - Visualizar'),
(40, 'contas', 'contas_receber_baixar', 'Contas a receber - Baixar'),
(41, 'contas', 'contas_receber_editar', 'Contas a receber - Editar'),
(42, 'contas', 'contas_receber_excluir', 'Contas a receber - Excluir'),
(43, 'contas', 'contas_pagar_ver', 'Contas a pagar - Visualizar'),
(44, 'contas', 'contas_pagar_criar', 'Contas a pagar - Inserir'),
(45, 'contas', 'contas_pagar_baixar', 'Contas a pagar - Baixar'),
(46, 'contas', 'contas_pagar_editar', 'Contas a pagar - Editar'),
(47, 'contas', 'contas_pagar_excluir', 'Contas a pagar - Excluir'),
(48, 'caixa', 'caixa_ver', 'Fluxo de caixa - Visualizar'),
(49, 'relatorios', 'relatorios_ver', 'Relatórios - Visualizar'),
(50, 'usuarios', 'usuarios_ver', 'Usuários - Visualizar'),
(51, 'usuarios', 'usuarios_criar', 'Usuários - Inserir'),
(52, 'usuarios', 'usuarios_editar', 'Usuários - Editar'),
(53, 'usuarios', 'usuarios_excluir', 'Usuários - Excluir'),
(54, 'config', 'config_ver', 'Configurações - Visualizar'),
(55, 'config', 'config_editar', 'Configurações - Editar'),
(56, 'logs', 'logs_ver', 'Logs/Auditoria - Visualizar');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `codigo_barras` varchar(30) DEFAULT NULL,
  `descricao` varchar(150) NOT NULL,
  `descricao_complementar` varchar(255) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `marca_id` int(11) DEFAULT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `ncm` varchar(10) DEFAULT NULL,
  `cest` varchar(10) DEFAULT NULL,
  `cfop` varchar(10) DEFAULT NULL,
  `preco_custo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `preco_venda` decimal(15,2) NOT NULL DEFAULT 0.00,
  `preco_promocional` decimal(15,2) DEFAULT NULL,
  `margem_lucro` decimal(8,2) DEFAULT NULL,
  `estoque_atual` decimal(12,3) NOT NULL DEFAULT 0.000,
  `estoque_minimo` decimal(12,3) NOT NULL DEFAULT 0.000,
  `estoque_maximo` decimal(12,3) NOT NULL DEFAULT 0.000,
  `localizacao` varchar(30) DEFAULT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `codigo`, `codigo_barras`, `descricao`, `descricao_complementar`, `categoria_id`, `subcategoria_id`, `marca_id`, `unidade_id`, `ncm`, `cest`, `cfop`, `preco_custo`, `preco_venda`, `preco_promocional`, `margem_lucro`, `estoque_atual`, `estoque_minimo`, `estoque_maximo`, `localizacao`, `fornecedor_id`, `foto`, `status`, `criado_em`, `atualizado_em`) VALUES
(2, 'TEST-UPPER-001', '', 'PRODUTO EXEMPLO UPPER TEST', 'DESC COMPLEMENTAR TEST', 1, NULL, 1, 1, '3.220.45', '17.010.00', '5.101', 39.40, 25.99, NULL, 60.00, 3.000, 2.000, 50.000, 'PRATELEIRA A 01', NULL, NULL, 1, '2026-09-05 18:39:35', '2026-09-09 11:07:32'),
(3, 'P00003', '433', 'BALDE', '', 1, NULL, 1, 5, '', '', '', 5.00, 2.50, 0.00, 60.00, 0.000, 0.000, 0.000, '', 1, 'assets/img/produtos/prod_3_9a14d6c2.png', 1, '2026-09-05 20:44:09', '2026-09-09 14:22:58'),
(4, 'P00004', '7891155059585', 'COPO SM CHOPP LAGER 300ML', '', 2, NULL, NULL, 1, '', '', '', 1.35, 2.70, 0.00, 50.00, 100.000, 100.000, 1000.000, '', NULL, 'assets/img/produtos/prod_4_7194b976.jpg', 1, '2026-09-09 14:24:17', '2026-09-10 15:23:54'),
(5, 'P00005', '7891155033950', 'PRATO DURALEX ACQUA SOBREMESA 19CM', '', 1, NULL, 1, NULL, '', '', '', 2.50, 3.99, 0.00, 37.34, 93.000, 0.000, 0.000, '', NULL, 'assets/img/produtos/prod_5_a5c3b14a.jpg', 1, '2026-09-09 14:27:51', '2026-09-09 14:32:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_codigos`
--

CREATE TABLE `produto_codigos` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `tipo` enum('BARRAS','INTERNO') NOT NULL DEFAULT 'BARRAS'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `recuperacao_tokens`
--

CREATE TABLE `recuperacao_tokens` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `validade` datetime NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `subcategorias`
--

CREATE TABLE `subcategorias` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_pedido`
--

CREATE TABLE `tipos_pedido` (
  `id` int(11) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `modulo` enum('VENDA','COMPRA') NOT NULL,
  `movimenta_estoque` tinyint(1) NOT NULL DEFAULT 1,
  `gera_financeiro` tinyint(1) NOT NULL DEFAULT 1,
  `estoque_entrada` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `tipos_pedido`
--

INSERT INTO `tipos_pedido` (`id`, `nome`, `modulo`, `movimenta_estoque`, `gera_financeiro`, `estoque_entrada`, `ativo`) VALUES
(1, 'Venda', 'VENDA', 1, 1, 0, 1),
(2, 'Troca', 'VENDA', 1, 0, 0, 1),
(3, 'Bonificação', 'VENDA', 1, 0, 0, 1),
(4, 'Devolução', 'VENDA', 1, 0, 1, 1),
(5, 'Compra', 'COMPRA', 1, 1, 1, 1),
(6, 'Troca', 'COMPRA', 1, 0, 1, 1),
(7, 'Bonificação', 'COMPRA', 1, 0, 1, 1),
(8, 'Devolução', 'COMPRA', 1, 0, 0, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `unidades`
--

CREATE TABLE `unidades` (
  `id` int(11) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `sigla` varchar(10) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `unidades`
--

INSERT INTO `unidades` (`id`, `nome`, `sigla`, `criado_em`) VALUES
(1, 'Unidade', 'UN', '2026-09-10 15:15:34'),
(2, 'Metro', 'M', '2026-09-10 15:15:34'),
(3, 'Quilograma', 'KG', '2026-09-10 15:15:34'),
(4, 'Litro', 'L', '2026-09-10 15:15:34'),
(5, 'Caixa', 'CX', '2026-09-10 15:15:34'),
(6, 'Pacote', 'PC', '2026-09-10 15:15:34'),
(7, 'Par', 'PAR', '2026-09-10 15:15:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `perfil_id` int(11) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `tentativas_falhas` tinyint(4) NOT NULL DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `usuario`, `email`, `senha`, `telefone`, `perfil_id`, `is_admin`, `status`, `criado_em`, `ultimo_acesso`, `tentativas_falhas`, `bloqueado_ate`) VALUES
(1, 'Administrador', 'admin', 'admin@sistema.com', '$2y$10$DjE.v4ZaBWk.MIMnUA84Tu/1R3XghDws1YBbk3Wzy6QsskZB.zQXq', NULL, 1, 1, 1, '2026-09-10 15:15:34', '2026-09-21 15:37:28', 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_permissoes`
--

CREATE TABLE `usuario_permissoes` (
  `usuario_id` int(11) NOT NULL,
  `permissao_id` int(11) NOT NULL,
  `permitido` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `tipo_pedido_id` int(11) DEFAULT NULL,
  `data_venda` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `acrescimo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('FINALIZADA','CANCELADA','ORCAMENTO') NOT NULL DEFAULT 'FINALIZADA',
  `observacao` text DEFAULT NULL,
  `criado_por` int(11) NOT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `cancelado_em` datetime DEFAULT NULL,
  `motivo_cancelamento` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_itens`
--

CREATE TABLE `venda_itens` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `preco_unitario` decimal(15,2) NOT NULL,
  `desconto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_pagamentos`
--

CREATE TABLE `venda_pagamentos` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `forma_pagamento_id` int(11) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `qtde_parcelas` int(11) NOT NULL DEFAULT 1,
  `data_pagamento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_parcelas`
--

CREATE TABLE `venda_parcelas` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `venda_pagamento_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `vencimento` date NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `status` enum('PENDENTE','PAGO','VENCIDO','CANCELADO','PARCIAL') NOT NULL DEFAULT 'PENDENTE'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categoria_nome` (`nome`);

--
-- Índices de tabela `categorias_financeiras`
--
ALTER TABLE `categorias_financeiras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_catfin` (`tipo`,`nome`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cliente_documento` (`documento`),
  ADD KEY `idx_cliente_nome` (`nome`),
  ADD KEY `idx_cliente_cidade` (`cidade`);

--
-- Índices de tabela `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_compra_numero` (`numero`),
  ADD KEY `idx_compra_data` (`data_compra`),
  ADD KEY `idx_compra_fornecedor` (`fornecedor_id`),
  ADD KEY `fk_compra_criado` (`criado_por`);

--
-- Índices de tabela `compra_itens`
--
ALTER TABLE `compra_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ci_compra` (`compra_id`),
  ADD KEY `fk_ci_produto` (`produto_id`);

--
-- Índices de tabela `compra_pagamentos`
--
ALTER TABLE `compra_pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cpg_compra` (`compra_id`),
  ADD KEY `fk_cpg_forma` (`forma_pagamento_id`);

--
-- Índices de tabela `compra_parcelas`
--
ALTER TABLE `compra_parcelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cpar_compra` (`compra_id`),
  ADD KEY `fk_cpar_pagamento` (`compra_pagamento_id`);

--
-- Índices de tabela `configs`
--
ALTER TABLE `configs`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `contas_pagar`
--
ALTER TABLE `contas_pagar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cp_vencimento` (`vencimento`),
  ADD KEY `idx_cp_status` (`status`),
  ADD KEY `fk_cp_fornecedor` (`fornecedor_id`),
  ADD KEY `fk_cp_compra` (`compra_id`),
  ADD KEY `fk_cp_parcela` (`compra_parcela_id`),
  ADD KEY `fk_cp_categoria` (`categoria_id`),
  ADD KEY `fk_cp_forma` (`forma_pagamento_id`);

--
-- Índices de tabela `contas_receber`
--
ALTER TABLE `contas_receber`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cr_vencimento` (`vencimento`),
  ADD KEY `idx_cr_status` (`status`),
  ADD KEY `idx_cr_cliente` (`cliente_id`),
  ADD KEY `fk_cr_venda` (`venda_id`),
  ADD KEY `fk_cr_parcela` (`venda_parcela_id`),
  ADD KEY `fk_cr_forma` (`forma_pagamento_id`);

--
-- Índices de tabela `estoque_movimentos`
--
ALTER TABLE `estoque_movimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mov_produto` (`produto_id`,`data`),
  ADD KEY `fk_mov_usuario` (`usuario_id`);

--
-- Índices de tabela `fluxo_caixa`
--
ALTER TABLE `fluxo_caixa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fluxo_data` (`data_movimento`),
  ADD KEY `idx_fluxo_ref` (`referencia`),
  ADD KEY `fk_fluxo_usuario` (`usuario_id`);

--
-- Índices de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_forma_nome` (`nome`);

--
-- Índices de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fornecedor_documento` (`documento`),
  ADD KEY `idx_fornecedor_nome` (`razao_social`);

--
-- Índices de tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_data` (`data`),
  ADD KEY `idx_logs_modulo` (`modulo`),
  ADD KEY `fk_logs_usuario` (`usuario_id`);

--
-- Índices de tabela `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_marca_nome` (`nome`);

--
-- Índices de tabela `perfil_permissoes`
--
ALTER TABLE `perfil_permissoes`
  ADD PRIMARY KEY (`perfil_id`,`permissao_id`),
  ADD KEY `fk_pp_permissao` (`permissao_id`);

--
-- Índices de tabela `perfis`
--
ALTER TABLE `perfis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_perfil_nome` (`nome`);

--
-- Índices de tabela `permissoes`
--
ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_perm_chave` (`chave`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto_codigo` (`codigo`),
  ADD KEY `idx_produto_barras` (`codigo_barras`),
  ADD KEY `idx_produto_descricao` (`descricao`),
  ADD KEY `fk_prod_categoria` (`categoria_id`),
  ADD KEY `fk_prod_subcategoria` (`subcategoria_id`),
  ADD KEY `fk_prod_marca` (`marca_id`),
  ADD KEY `fk_prod_unidade` (`unidade_id`),
  ADD KEY `fk_prod_fornecedor` (`fornecedor_id`);

--
-- Índices de tabela `produto_codigos`
--
ALTER TABLE `produto_codigos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_produto_codigo` (`codigo`),
  ADD KEY `fk_pc_produto` (`produto_id`);

--
-- Índices de tabela `recuperacao_tokens`
--
ALTER TABLE `recuperacao_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rec_token` (`token`),
  ADD KEY `fk_rec_usuario` (`usuario_id`);

--
-- Índices de tabela `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subcategoria` (`categoria_id`,`nome`);

--
-- Índices de tabela `tipos_pedido`
--
ALTER TABLE `tipos_pedido`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_pedido` (`nome`,`modulo`);

--
-- Índices de tabela `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_unidade_nome` (`nome`),
  ADD UNIQUE KEY `uq_unidade_sigla` (`sigla`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_login` (`usuario`),
  ADD UNIQUE KEY `uq_usuario_email` (`email`),
  ADD KEY `fk_usuario_perfil` (`perfil_id`);

--
-- Índices de tabela `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD PRIMARY KEY (`usuario_id`,`permissao_id`),
  ADD KEY `fk_up_permissao` (`permissao_id`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_venda_numero` (`numero`),
  ADD KEY `idx_venda_data` (`data_venda`),
  ADD KEY `idx_venda_cliente` (`cliente_id`),
  ADD KEY `fk_venda_vendedor` (`vendedor_id`),
  ADD KEY `fk_venda_criado` (`criado_por`);

--
-- Índices de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vi_venda` (`venda_id`),
  ADD KEY `fk_vi_produto` (`produto_id`);

--
-- Índices de tabela `venda_pagamentos`
--
ALTER TABLE `venda_pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vpg_venda` (`venda_id`),
  ADD KEY `fk_vpg_forma` (`forma_pagamento_id`);

--
-- Índices de tabela `venda_parcelas`
--
ALTER TABLE `venda_parcelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vpar_venda` (`venda_id`),
  ADD KEY `fk_vpar_pagamento` (`venda_pagamento_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `categorias_financeiras`
--
ALTER TABLE `categorias_financeiras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compra_itens`
--
ALTER TABLE `compra_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compra_pagamentos`
--
ALTER TABLE `compra_pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compra_parcelas`
--
ALTER TABLE `compra_parcelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `contas_pagar`
--
ALTER TABLE `contas_pagar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `contas_receber`
--
ALTER TABLE `contas_receber`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estoque_movimentos`
--
ALTER TABLE `estoque_movimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fluxo_caixa`
--
ALTER TABLE `fluxo_caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `marcas`
--
ALTER TABLE `marcas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `perfis`
--
ALTER TABLE `perfis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `permissoes`
--
ALTER TABLE `permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `produto_codigos`
--
ALTER TABLE `produto_codigos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `recuperacao_tokens`
--
ALTER TABLE `recuperacao_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subcategorias`
--
ALTER TABLE `subcategorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tipos_pedido`
--
ALTER TABLE `tipos_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `venda_itens`
--
ALTER TABLE `venda_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `venda_pagamentos`
--
ALTER TABLE `venda_pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `venda_parcelas`
--
ALTER TABLE `venda_parcelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compra_criado` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_compra_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `compra_itens`
--
ALTER TABLE `compra_itens`
  ADD CONSTRAINT `fk_ci_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ci_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `compra_pagamentos`
--
ALTER TABLE `compra_pagamentos`
  ADD CONSTRAINT `fk_cpg_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpg_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`);

--
-- Restrições para tabelas `compra_parcelas`
--
ALTER TABLE `compra_parcelas`
  ADD CONSTRAINT `fk_cpar_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpar_pagamento` FOREIGN KEY (`compra_pagamento_id`) REFERENCES `compra_pagamentos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `contas_pagar`
--
ALTER TABLE `contas_pagar`
  ADD CONSTRAINT `fk_cp_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_financeiras` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cp_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cp_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cp_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cp_parcela` FOREIGN KEY (`compra_parcela_id`) REFERENCES `compra_parcelas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `contas_receber`
--
ALTER TABLE `contas_receber`
  ADD CONSTRAINT `fk_cr_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cr_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cr_parcela` FOREIGN KEY (`venda_parcela_id`) REFERENCES `venda_parcelas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cr_venda` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `estoque_movimentos`
--
ALTER TABLE `estoque_movimentos`
  ADD CONSTRAINT `fk_mov_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `fluxo_caixa`
--
ALTER TABLE `fluxo_caixa`
  ADD CONSTRAINT `fk_fluxo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `perfil_permissoes`
--
ALTER TABLE `perfil_permissoes`
  ADD CONSTRAINT `fk_pp_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pp_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_prod_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prod_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prod_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prod_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prod_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `produto_codigos`
--
ALTER TABLE `produto_codigos`
  ADD CONSTRAINT `fk_pc_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `recuperacao_tokens`
--
ALTER TABLE `recuperacao_tokens`
  ADD CONSTRAINT `fk_rec_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD CONSTRAINT `fk_subcat_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD CONSTRAINT `fk_up_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_venda_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_venda_criado` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_venda_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `venda_itens`
--
ALTER TABLE `venda_itens`
  ADD CONSTRAINT `fk_vi_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `fk_vi_venda` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `venda_pagamentos`
--
ALTER TABLE `venda_pagamentos`
  ADD CONSTRAINT `fk_vpg_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`),
  ADD CONSTRAINT `fk_vpg_venda` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `venda_parcelas`
--
ALTER TABLE `venda_parcelas`
  ADD CONSTRAINT `fk_vpar_pagamento` FOREIGN KEY (`venda_pagamento_id`) REFERENCES `venda_pagamentos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vpar_venda` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE;
--
-- Migracao do modulo de Consumo (cardapio, mesas, comandas, producao, caixa)
-- Inclusao automatica gerada de sql/consumo.sql
-- Idempotente: seguro para executar mais de uma vez.
--

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `cardapio_categorias`;
DROP TABLE IF EXISTS `cardapio_itens`;
DROP TABLE IF EXISTS `mesas`;
DROP TABLE IF EXISTS `comandas`;
DROP TABLE IF EXISTS `comanda_itens`;
DROP TABLE IF EXISTS `comanda_pagamentos`;

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

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
