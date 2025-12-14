-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 09/12/2025 às 01:46
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `candyplace`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadastro_cliente`
--

CREATE TABLE `cadastro_cliente` (
  `cd_cliente` int(11) NOT NULL,
  `nm_cliente` varchar(255) DEFAULT NULL,
  `cd_cpf` char(11) DEFAULT NULL,
  `ds_email` varchar(255) DEFAULT NULL,
  `nr_telefone` char(14) DEFAULT NULL,
  `ds_senha` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cadastro_cliente`
--

INSERT INTO `cadastro_cliente` (`cd_cliente`, `nm_cliente`, `cd_cpf`, `ds_email`, `nr_telefone`, `ds_senha`) VALUES
(2, 'renatin', '12412451515', 'renato@gmail.com', '18234719823791', '$2y$10$NwTVrWg377CvkJ4fylba/urhlwwuGvwHbytE2eMdHqbV16Ljye4ae'),
(3, 'kaua ', '12412414141', 'kaua@gmail.com', '12414123123123', '$2y$10$6qY6Lo4Adf04roUqRcM9h.Whrw0u2vTuJg8e0.TiegcoS.zERwW/K'),
(4, 'paulo da silva santos ribeiro maconheiro ypin 2', '91024819024', 'paulo@gmail.com', '13996983837', '$2y$10$0/h.EtMIoyqnoP41T7HMu.s3vhMd48vegPZ2bVvy.q8dTqtcLF59C'),
(5, 'juju', '14124125151', 'juju@gmail.com', '13 98868-5159', '$2y$10$GTXLafC6Dtg3J4NfPpqB0.7oZ34Sjr1GaPcBne3rtKmvsT/vRcsZm');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadastro_loja`
--

CREATE TABLE `cadastro_loja` (
  `cd_loja` int(11) NOT NULL,
  `cd_cnpj` varchar(11) DEFAULT NULL,
  `nm_razao_social` varchar(255) DEFAULT NULL,
  `nm_loja` varchar(255) DEFAULT NULL,
  `ds_email` varchar(255) DEFAULT NULL,
  `nr_telefone` char(14) DEFAULT NULL,
  `ds_senha` varchar(255) DEFAULT NULL,
  `sg_estado` char(2) DEFAULT NULL,
  `nm_cidade` varchar(255) DEFAULT NULL,
  `nm_bairro` varchar(255) DEFAULT NULL,
  `ds_endereco` varchar(255) DEFAULT NULL,
  `cd_cep` char(9) DEFAULT NULL,
  `franquia` enum('sim','não') DEFAULT NULL,
  `status_loja` enum('Ativa','Desativada','Suspensa') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cadastro_loja`
--

INSERT INTO `cadastro_loja` (`cd_loja`, `cd_cnpj`, `nm_razao_social`, `nm_loja`, `ds_email`, `nr_telefone`, `ds_senha`, `sg_estado`, `nm_cidade`, `nm_bairro`, `ds_endereco`, `cd_cep`, `franquia`, `status_loja`) VALUES
(9, '12315151515', 'lojadoviado', 'Loja do jorge', 'loja@gmail.com', '13 99806-3506', '$2y$10$aq3zZh4BX1pceNhD3A50Uu6N5HL7QCUI/210WZ.JfoZ38ErRteex2', 'AC', 'Itacity', 'rio de sperma', 'rua do amor123', '134134134', 'sim', 'Ativa'),
(10, '26112864851', 'loj DE CACHIORRO ABANDONADO ', 'PETS DE RUA', 'PETS.COM@GMAIL.COM', '13996973465', '$2y$10$5s.B1LZoWgAH/iSjomcUouQvvDC/dibGHZ7VUYUocpe/DWxEJBUF2', 'SP', 'ITANHAEM', 'OASIS', 'EMERSON Da silçva ', '11740000', '', 'Ativa'),
(11, '16445758676', 'valorantgames', 'loja do xarola', 'xarola@gmail.com', '13 98185-9199', '$2y$10$l3JtTusMSFflXpSEYTniPuIuNS0m7VFZkHYQlR5.4SlfcERpJ1yM.', 'SP', 'Itacity', 'rio de sperma', 'rua do amor123', '142355235', '', 'Ativa'),
(12, '13890457819', 'Mercado alimenticio do vini', 'Vini stores', 'vini@gmail.com', '13998056317', '$2y$10$RvoQYZ5IdPGQcnSseDFpGOQt3eKx.4yZHnKzikwrWjaEsQMJEx87i', 'AC', 'rio branco', 'rio de sperma', 'rua do amor123', '123904819', 'sim', 'Ativa');

-- --------------------------------------------------------

--
-- Estrutura para tabela `complemento`
--

CREATE TABLE `complemento` (
  `cd_complemento` int(11) NOT NULL,
  `nm_complemento` varchar(255) DEFAULT NULL,
  `vl_adicional` decimal(10,2) DEFAULT NULL,
  `status_complemento` enum('Estoque','Esgotado') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `complemento_produto`
--

CREATE TABLE `complemento_produto` (
  `cd_pedido` int(11) DEFAULT NULL,
  `cd_produto` int(11) DEFAULT NULL,
  `cd_complemento` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `endereco_cliente`
--

CREATE TABLE `endereco_cliente` (
  `cd_endereco` int(11) NOT NULL,
  `cd_cliente` int(11) DEFAULT NULL,
  `sg_estado` char(2) DEFAULT NULL,
  `nm_cidade` varchar(255) DEFAULT NULL,
  `nm_bairro` varchar(255) DEFAULT NULL,
  `ds_endereco` varchar(255) DEFAULT NULL,
  `cd_cep` char(9) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `endereco_cliente`
--

INSERT INTO `endereco_cliente` (`cd_endereco`, `cd_cliente`, `sg_estado`, `nm_cidade`, `nm_bairro`, `ds_endereco`, `cd_cep`) VALUES
(1, 3, NULL, 'itacity', 'rio branco de peruibe', 'rua das flores', '312341455'),
(2, 2, NULL, 'mongametropoles', 'rio branco de peruibe', 'rua das flores', '412461263'),
(3, 4, NULL, 'roma antiga', 'sete ovelhas', 'scambo', '139084129'),
(4, 5, NULL, 'roma antiga', 'rio branco de peruibe', 'rua das flores', '145194810');

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens`
--

CREATE TABLE `itens` (
  `cd_item` int(11) NOT NULL,
  `cd_pedido` int(11) DEFAULT NULL,
  `cd_produto` int(11) DEFAULT NULL,
  `qt_produto` int(11) DEFAULT NULL,
  `vl_preco_unitario` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedido`
--

CREATE TABLE `pedido` (
  `cd_pedido` int(11) NOT NULL,
  `cd_loja` int(11) DEFAULT NULL,
  `cd_cliente` int(11) DEFAULT NULL,
  `cd_produto` int(11) DEFAULT NULL,
  `cd_endereco` int(11) DEFAULT NULL,
  `vl_subtotal` decimal(10,2) DEFAULT NULL,
  `vl_frete` decimal(10,2) DEFAULT NULL,
  `vl_total` decimal(10,2) DEFAULT NULL,
  `dt_pedido` date DEFAULT NULL,
  `pagamento` enum('Pix','Débito','Crédito','Dinheiro') DEFAULT NULL,
  `ds_status_pedido` varchar(50) NOT NULL DEFAULT 'Carrinho',
  `cd_endereco_entrega` int(11) DEFAULT NULL,
  `status_pedido` varchar(50) NOT NULL DEFAULT 'Aguardando Pagamento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto`
--

CREATE TABLE `produto` (
  `cd_produto` int(11) NOT NULL,
  `cd_loja` int(11) DEFAULT NULL,
  `nm_produto` varchar(255) DEFAULT NULL,
  `ds_produto` varchar(255) DEFAULT NULL,
  `ds_imagem` varchar(1000) DEFAULT NULL,
  `ds_categoria` varchar(255) DEFAULT NULL,
  `vl_preco` decimal(10,2) DEFAULT NULL,
  `qt_estoque` int(11) DEFAULT NULL,
  `status_estoque` enum('Estoque','Esgotado') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produto`
--

INSERT INTO `produto` (`cd_produto`, `cd_loja`, `nm_produto`, `ds_produto`, `ds_imagem`, `ds_categoria`, `vl_preco`, `qt_estoque`, `status_estoque`) VALUES
(19, 11, 'Bombom Gourmet', '', 'uploads/produtos/prod_69376fde318b12.84327135.jpg', 'Bomboms', 20.00, 100, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `cadastro_cliente`
--
ALTER TABLE `cadastro_cliente`
  ADD PRIMARY KEY (`cd_cliente`);

--
-- Índices de tabela `cadastro_loja`
--
ALTER TABLE `cadastro_loja`
  ADD PRIMARY KEY (`cd_loja`),
  ADD UNIQUE KEY `cd_cnpj` (`cd_cnpj`);

--
-- Índices de tabela `complemento`
--
ALTER TABLE `complemento`
  ADD PRIMARY KEY (`cd_complemento`);

--
-- Índices de tabela `complemento_produto`
--
ALTER TABLE `complemento_produto`
  ADD KEY `cd_pedido` (`cd_pedido`),
  ADD KEY `cd_produto` (`cd_produto`),
  ADD KEY `cd_complemento` (`cd_complemento`);

--
-- Índices de tabela `endereco_cliente`
--
ALTER TABLE `endereco_cliente`
  ADD PRIMARY KEY (`cd_endereco`),
  ADD KEY `cd_cliente` (`cd_cliente`);

--
-- Índices de tabela `itens`
--
ALTER TABLE `itens`
  ADD PRIMARY KEY (`cd_item`),
  ADD KEY `cd_pedido` (`cd_pedido`),
  ADD KEY `cd_produto` (`cd_produto`);

--
-- Índices de tabela `pedido`
--
ALTER TABLE `pedido`
  ADD PRIMARY KEY (`cd_pedido`),
  ADD KEY `cd_loja` (`cd_loja`),
  ADD KEY `cd_cliente` (`cd_cliente`),
  ADD KEY `cd_produto` (`cd_produto`),
  ADD KEY `cd_endereco` (`cd_endereco`);

--
-- Índices de tabela `produto`
--
ALTER TABLE `produto`
  ADD PRIMARY KEY (`cd_produto`),
  ADD KEY `cd_loja` (`cd_loja`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `cadastro_cliente`
--
ALTER TABLE `cadastro_cliente`
  MODIFY `cd_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `cadastro_loja`
--
ALTER TABLE `cadastro_loja`
  MODIFY `cd_loja` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `endereco_cliente`
--
ALTER TABLE `endereco_cliente`
  MODIFY `cd_endereco` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `itens`
--
ALTER TABLE `itens`
  MODIFY `cd_item` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `pedido`
--
ALTER TABLE `pedido`
  MODIFY `cd_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `produto`
--
ALTER TABLE `produto`
  MODIFY `cd_produto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `complemento_produto`
--
ALTER TABLE `complemento_produto`
  ADD CONSTRAINT `complemento_produto_ibfk_1` FOREIGN KEY (`cd_pedido`) REFERENCES `pedido` (`cd_pedido`),
  ADD CONSTRAINT `complemento_produto_ibfk_2` FOREIGN KEY (`cd_produto`) REFERENCES `produto` (`cd_produto`),
  ADD CONSTRAINT `complemento_produto_ibfk_3` FOREIGN KEY (`cd_complemento`) REFERENCES `complemento` (`cd_complemento`);

--
-- Restrições para tabelas `endereco_cliente`
--
ALTER TABLE `endereco_cliente`
  ADD CONSTRAINT `endereco_cliente_ibfk_1` FOREIGN KEY (`cd_cliente`) REFERENCES `cadastro_cliente` (`cd_cliente`);

--
-- Restrições para tabelas `itens`
--
ALTER TABLE `itens`
  ADD CONSTRAINT `itens_ibfk_1` FOREIGN KEY (`cd_pedido`) REFERENCES `pedido` (`cd_pedido`),
  ADD CONSTRAINT `itens_ibfk_2` FOREIGN KEY (`cd_produto`) REFERENCES `produto` (`cd_produto`);

--
-- Restrições para tabelas `pedido`
--
ALTER TABLE `pedido`
  ADD CONSTRAINT `pedido_ibfk_1` FOREIGN KEY (`cd_loja`) REFERENCES `cadastro_loja` (`cd_loja`),
  ADD CONSTRAINT `pedido_ibfk_2` FOREIGN KEY (`cd_cliente`) REFERENCES `cadastro_cliente` (`cd_cliente`),
  ADD CONSTRAINT `pedido_ibfk_3` FOREIGN KEY (`cd_produto`) REFERENCES `produto` (`cd_produto`),
  ADD CONSTRAINT `pedido_ibfk_4` FOREIGN KEY (`cd_endereco`) REFERENCES `endereco_cliente` (`cd_endereco`);

--
-- Restrições para tabelas `produto`
--
ALTER TABLE `produto`
  ADD CONSTRAINT `produto_ibfk_1` FOREIGN KEY (`cd_loja`) REFERENCES `cadastro_loja` (`cd_loja`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
