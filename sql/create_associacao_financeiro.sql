-- Migração Fase 0 (MVP Associações): financeiro e apoio
-- Recurso MariaDB: CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS (idempotente)

-- =========================================================
-- Planos de mensalidade / anuidade / jóia por associação
-- =========================================================
CREATE TABLE IF NOT EXISTS `planos_mensalidades` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `associacao_id` INT(11) DEFAULT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `tipo` ENUM('mensalidade','anuidade','joia') NOT NULL DEFAULT 'mensalidade',
  `valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `dia_vencimento` TINYINT(4) NOT NULL DEFAULT 10,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plano_associacao` (`associacao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Caixa (movimentações diárias com comprovante)
-- =========================================================
CREATE TABLE IF NOT EXISTS `caixa` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `associacao_id` INT(11) DEFAULT NULL,
  `tipo` ENUM('Entrada','Saida') NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `categoria` VARCHAR(100) DEFAULT NULL,
  `valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `data` DATE NOT NULL,
  `comprovante_url` VARCHAR(255) DEFAULT NULL,
  `referencia_type` VARCHAR(50) DEFAULT NULL,
  `referencia_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `criado_por` INT(11) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caixa_associacao` (`associacao_id`),
  KEY `idx_caixa_referencia` (`referencia_type`,`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Carteirinhas digitais (token único + validade para QR Code)
-- =========================================================
CREATE TABLE IF NOT EXISTS `carteirinhas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `associado_id` INT(11) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `validade` DATE NOT NULL,
  `gerada_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carteirinha_token` (`token`),
  KEY `idx_carteirinha_associado` (`associado_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Evolução da tabela mensalidades (já existente)
-- =========================================================
ALTER TABLE `mensalidades` ADD COLUMN IF NOT EXISTS `associacao_id` INT(11) DEFAULT NULL AFTER `associado_id`;
ALTER TABLE `mensalidades` ADD COLUMN IF NOT EXISTS `plano_id` INT(11) DEFAULT NULL AFTER `associacao_id`;
ALTER TABLE `mensalidades` ADD COLUMN IF NOT EXISTS `fonte` ENUM('manual','automatica') NOT NULL DEFAULT 'manual' AFTER `plano_id`;

-- =========================================================
-- Evolução da tabela pagamentos (já existente)
-- =========================================================
ALTER TABLE `pagamentos` ADD COLUMN IF NOT EXISTS `associacao_id` INT(11) DEFAULT NULL AFTER `associado_id`;
ALTER TABLE `pagamentos` ADD COLUMN IF NOT EXISTS `comprovante_url` VARCHAR(255) DEFAULT NULL AFTER `referencia_id`;

-- =========================================================
-- Vínculo caixa ↔ mensalidade/pagamento (estorno de baixa)
-- =========================================================
ALTER TABLE `caixa` ADD COLUMN IF NOT EXISTS `referencia_type` VARCHAR(50) DEFAULT NULL AFTER `comprovante_url`;
ALTER TABLE `caixa` ADD COLUMN IF NOT EXISTS `referencia_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `referencia_type`;