-- =====================================================
-- SINPORG v2.0 - Sprint 1: Alterações na Tabela Insumos
-- Adiciona campos para validação de insumos orgânicos
-- =====================================================
-- Data: 2026-02-17
-- =====================================================

-- Verificar se a tabela 'insumos' existe
-- (adaptado para estrutura existente do SINPORG)

-- Adicionar novos campos à tabela insumos existente
ALTER TABLE insumos 
ADD COLUMN IF NOT EXISTS tipo_insumo ENUM('Orgânico', 'Em Conversão', 'Convencional', 'Não Permitido') DEFAULT 'Orgânico' COMMENT 'Tipo de certificação do insumo',
ADD COLUMN IF NOT EXISTS aprovado_organico BOOLEAN DEFAULT FALSE COMMENT 'Se o insumo está na lista de aprovados (IN 46/2011)',
ADD COLUMN IF NOT EXISTS certificacao_insumo VARCHAR(200) COMMENT 'Certificação do insumo (se houver)',
ADD COLUMN IF NOT EXISTS insumo_aprovado_id INT COMMENT 'Referência ao insumo aprovado na lista oficial',
ADD COLUMN IF NOT EXISTS restricoes TEXT COMMENT 'Restrições de uso do insumo',
ADD COLUMN IF NOT EXISTS composicao TEXT COMMENT 'Composição do insumo',
ADD COLUMN IF NOT EXISTS fabricante VARCHAR(200) COMMENT 'Fabricante do insumo',
ADD COLUMN IF NOT EXISTS lote VARCHAR(100) COMMENT 'Lote do insumo',
ADD COLUMN IF NOT EXISTS data_validade DATE COMMENT 'Data de validade do insumo',
ADD INDEX IF NOT EXISTS idx_tipo_insumo (tipo_insumo),
ADD INDEX IF NOT EXISTS idx_aprovado (aprovado_organico);

-- Adicionar chave estrangeira para insumos_organicos_aprovados
ALTER TABLE insumos 
ADD CONSTRAINT fk_insumo_aprovado 
FOREIGN KEY (insumo_aprovado_id) 
REFERENCES insumos_organicos_aprovados(id) 
ON DELETE SET NULL;

-- =====================================================
-- FIM DO SCRIPT DE ALTERAÇÃO
-- =====================================================
