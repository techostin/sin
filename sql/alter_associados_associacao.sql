-- Migração Fase 0 (MVP Associações): evolução da tabela `associados`
-- Recurso MariaDB: ADD COLUMN IF NOT EXISTS (não destrutivo, idempotente)
-- Mantém o login intacto (colunas existentes não são alteradas).

-- Tipo do associado dentro da associação (CRM)
ALTER TABLE `associados` ADD COLUMN IF NOT EXISTS `tipo_associado` ENUM('Efetivo','Honorario','Benemerito','Dependente','Aspirante') DEFAULT 'Efetivo' AFTER `tipo_certificacao`;

-- Status ampliado do associado (o status_atual `status` continua sendo o de login/ativação)
ALTER TABLE `associados` ADD COLUMN IF NOT EXISTS `status_associado` ENUM('Ativo','Inadimplente','Afastado','Desligado','Falecido') DEFAULT 'Ativo' AFTER `tipo_associado`;

-- Dados complementares do CRM
ALTER TABLE `associados` ADD COLUMN IF NOT EXISTS `profissao` VARCHAR(100) DEFAULT NULL AFTER `status_associado`;
ALTER TABLE `associados` ADD COLUMN IF NOT EXISTS `data_admissao` DATE DEFAULT NULL AFTER `profissao`;
ALTER TABLE `associados` ADD COLUMN IF NOT EXISTS `data_desligamento` DATE DEFAULT NULL AFTER `data_admissao`;