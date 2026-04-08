-- Script para criar a tabela de eventos
-- Execute este script no seu banco de dados MySQL

CREATE TABLE IF NOT EXISTS `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_evento` datetime NOT NULL,
  `local` varchar(255) DEFAULT NULL,
  `status` enum('Agendado','Realizado','Cancelado') DEFAULT 'Agendado',
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `data_evento` (`data_evento`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- Inserir alguns eventos de exemplo
INSERT INTO `eventos` (`nome`, `descricao`, `data_evento`, `local`, `status`, `criado_por`) VALUES
('Reunião Mensal', 'Reunião mensal da associação para discutir assuntos gerais', DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL '19:00' HOUR_MINUTE, 'Sede da Associação', 'Agendado', 13),
('Palestra sobre Agricultura Sustentável', 'Palestra com especialistas em práticas agrícolas sustentáveis', DATE_ADD(CURDATE(), INTERVAL 5 DAY) + INTERVAL '14:00' HOUR_MINUTE, 'Centro Comunitário', 'Agendado', 13),
('Dia de Campo', 'Visita técnica a propriedades rurais para troca de experiências', DATE_ADD(CURDATE(), INTERVAL 10 DAY) + INTERVAL '08:00' HOUR_MINUTE, 'Fazenda Modelo', 'Agendado', 13);

-- Verificar se a tabela foi criada
SELECT 'Tabela de eventos criada com sucesso!' as status;
