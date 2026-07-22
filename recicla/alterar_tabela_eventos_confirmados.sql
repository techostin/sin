-- Script para adicionar coluna confirmados na tabela eventos
-- e criar tabela evento_presenca se não existir

-- Adicionar coluna confirmados na tabela eventos (JSON para armazenar array de confirmados)
ALTER TABLE `eventos` 
ADD COLUMN IF NOT EXISTS `confirmados` JSON DEFAULT NULL COMMENT 'Array JSON com IDs dos usuários que confirmaram presença';

-- Criar tabela evento_presenca para registro detalhado de presenças
CREATE TABLE IF NOT EXISTS `evento_presenca` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evento_id` int(11) NOT NULL,
  `associado_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `status_presenca` enum('Confirmado','Cancelado') DEFAULT 'Confirmado',
  `confirmado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evento_id` (`evento_id`),
  KEY `associado_id` (`associado_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `evento_presenca_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evento_presenca_ibfk_2` FOREIGN KEY (`associado_id`) REFERENCES `associados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evento_presenca_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- Verificar se as alterações foram aplicadas
SELECT 'Coluna confirmados adicionada à tabela eventos!' as status;
SELECT 'Tabela evento_presenca criada/verificada!' as status;
