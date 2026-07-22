-- Script para alterar o campo sigla da tabela associacoes
-- De VARCHAR(20) para VARCHAR(30)

ALTER TABLE `associacoes` MODIFY COLUMN `sigla` varchar(30) DEFAULT NULL;

-- Verificar se a alteração foi aplicada
DESCRIBE `associacoes`; 