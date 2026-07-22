-- Script para criar tabelas faltantes no banco de dados
-- Execute este script no seu banco de dados MySQL

-- Criar tabela produtos
CREATE TABLE IF NOT EXISTS `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `unidade_medida` varchar(50) DEFAULT NULL,
  `quantidade_produzida` decimal(10,2) DEFAULT NULL,
  `data_colheita` date DEFAULT NULL,
  `lavoura_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lavoura_id` (`lavoura_id`),
  CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`lavoura_id`) REFERENCES `lavouras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- Criar tabela lotes_producao
CREATE TABLE IF NOT EXISTS `lotes_producao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_lote` varchar(255) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `data_producao` date NOT NULL,
  `quantidade` decimal(10,2) NOT NULL,
  `unidade_medida` varchar(50) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `lotes_producao_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- Inserir alguns dados de exemplo para produtos
INSERT INTO `produtos` (`nome`, `tipo`, `unidade_medida`, `quantidade_produzida`, `data_colheita`, `lavoura_id`, `observacoes`) VALUES
('Milho Verde', 'Grão', 'kg', 1500.00, '2024-03-15', 1, 'Milho de alta qualidade'),
('Feijão Carioca', 'Grão', 'kg', 800.00, '2024-02-20', 2, 'Feijão orgânico'),
('Tomate', 'Hortaliça', 'kg', 2000.00, '2024-01-10', 3, 'Tomate para mesa'),
('Alface', 'Hortaliça', 'un', 500.00, '2024-01-05', 4, 'Alface crespa');

-- Inserir alguns dados de exemplo para lotes de produção
INSERT INTO `lotes_producao` (`nome_lote`, `produto_id`, `data_producao`, `quantidade`, `unidade_medida`, `observacoes`) VALUES
('LOTE-001-2024', 1, '2024-03-15', 1500.00, 'kg', 'Lote de milho da safra 2024'),
('LOTE-002-2024', 2, '2024-02-20', 800.00, 'kg', 'Lote de feijão orgânico'),
('LOTE-003-2024', 3, '2024-01-10', 2000.00, 'kg', 'Lote de tomate para mesa'),
('LOTE-004-2024', 4, '2024-01-05', 500.00, 'un', 'Lote de alface crespa');

-- Verificar se as tabelas foram criadas
SELECT 'Tabelas criadas com sucesso!' as status; 