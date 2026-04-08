-- =====================================================
-- SINPORG v2.0 - Sprint 1: Database Schema
-- Módulos para Produção Orgânica e Certificação
-- =====================================================
-- Base Legal: Portaria MAPA 52/2021 e atualizações
-- (Substitui IN 46/2011 - revogada)
-- Atualizações: Portarias 404/2022, 811/2023, 685/2024, 1.201/2024
-- =====================================================

-- =====================================================
-- 1. CERTIFICAÇÃO PARTICIPATIVA (SPG/OCS)
-- =====================================================

CREATE TABLE IF NOT EXISTS certificacao_participativa (
  id INT PRIMARY KEY AUTO_INCREMENT,
  associado_id INT NOT NULL,
  
  -- Tipo de certificação (SPG prioritário, OCS suporte futuro)
  tipo_certificacao ENUM('SPG', 'OCS', 'Rede Ecovida', 'Outro') NOT NULL DEFAULT 'SPG',
  
  -- Dados da certificação
  numero_certificado VARCHAR(100),
  data_emissao DATE,
  data_validade DATE,
  status ENUM('Ativa', 'Vencida', 'Suspensa', 'Em Conversão', 'Cancelada') DEFAULT 'Em Conversão',
  
  -- Organismo certificador
  organismo_certificador VARCHAR(200),
  nome_grupo VARCHAR(200) COMMENT 'Nome do grupo SPG ou OCS',
  
  -- Documentação
  documento_url VARCHAR(255) COMMENT 'URL do certificado digitalizado',
  declaracao_compromisso_url VARCHAR(255) COMMENT 'Declaração de compromisso assinada',
  
  -- Campos adicionais flexíveis (JSON para extensibilidade)
  dados_adicionais JSON COMMENT 'Campos específicos por tipo de certificação',
  
  -- Observações
  observacoes TEXT,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Chaves estrangeiras
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
  
  -- Índices
  INDEX idx_associado (associado_id),
  INDEX idx_tipo (tipo_certificacao),
  INDEX idx_status (status),
  INDEX idx_validade (data_validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Certificações participativas (SPG/OCS) dos agricultores';

-- =====================================================
-- 2. PLANO DE MANEJO ORGÂNICO
-- =====================================================

CREATE TABLE IF NOT EXISTS plano_manejo (
  id INT PRIMARY KEY AUTO_INCREMENT,
  propriedade_id INT NOT NULL,
  associado_id INT NOT NULL,
  
  -- Identificação do plano
  ano_safra VARCHAR(9) NOT NULL COMMENT 'Ex: 2025/2026',
  titulo VARCHAR(200) NOT NULL,
  versao INT DEFAULT 1 COMMENT 'Versão do plano (incrementa a cada revisão)',
  
  -- Áreas (em hectares)
  area_total_ha DECIMAL(10,2),
  area_organica_ha DECIMAL(10,2),
  area_conversao_ha DECIMAL(10,2),
  area_convencional_ha DECIMAL(10,2) COMMENT 'Se houver produção paralela',
  
  -- Caracterização da propriedade
  descricao_solo TEXT,
  topografia ENUM('Plana', 'Ondulada', 'Montanhosa', 'Irregular') DEFAULT 'Plana',
  fonte_agua TEXT COMMENT 'Poços, rios, açudes, etc',
  clima TEXT,
  
  -- Práticas de manejo
  praticas_conservacao TEXT COMMENT 'Curvas de nível, terraços, etc',
  manejo_pragas TEXT,
  manejo_doencas TEXT,
  adubacao_verde TEXT,
  rotacao_culturas TEXT,
  compostagem TEXT,
  biofertilizantes TEXT,
  
  -- Documentação visual
  croqui_propriedade_url VARCHAR(255) COMMENT 'Mapa/croqui da propriedade',
  fotos_propriedade TEXT COMMENT 'URLs separadas por vírgula',
  
  -- Status de aprovação
  status ENUM('Rascunho', 'Em Análise', 'Aprovado', 'Reprovado', 'Revisão Necessária') DEFAULT 'Rascunho',
  aprovado_por INT COMMENT 'user_id do técnico que aprovou',
  data_aprovacao DATE,
  data_reprovacao DATE,
  motivo_reprovacao TEXT,
  
  -- Observações
  observacoes TEXT,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Chaves estrangeiras
  FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
  
  -- Índices
  INDEX idx_propriedade (propriedade_id),
  INDEX idx_associado (associado_id),
  INDEX idx_ano_safra (ano_safra),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Planos de manejo orgânico das propriedades';

-- =====================================================
-- 3. CULTIVOS PLANEJADOS (Parte do Plano de Manejo)
-- =====================================================

CREATE TABLE IF NOT EXISTS plano_manejo_cultivos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  plano_manejo_id INT NOT NULL,
  
  -- Cultivo
  cultura VARCHAR(100) NOT NULL,
  variedade VARCHAR(100),
  area_cultivada_ha DECIMAL(10,2),
  
  -- Planejamento
  data_plantio_prevista DATE,
  data_colheita_prevista DATE,
  producao_estimada_kg DECIMAL(10,2),
  
  -- Validação de Sementes (Portaria 52/2021 - Art. relacionado a sementes orgânicas)
  tipo_semente ENUM('Orgânica Certificada', 'Convencional Não Tratada', 'Crioula', 'Própria') DEFAULT 'Orgânica Certificada' COMMENT 'Tipo de semente utilizada',
  justificativa_semente TEXT COMMENT 'Justificativa se não usar semente orgânica certificada',
  
  -- Observações
  observacoes TEXT,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Chaves estrangeiras
  FOREIGN KEY (plano_manejo_id) REFERENCES plano_manejo(id) ON DELETE CASCADE,
  
  -- Índices
  INDEX idx_plano (plano_manejo_id),
  INDEX idx_cultura (cultura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Cultivos planejados no plano de manejo';

-- =====================================================
-- 4. ROTEIROS DE INSPEÇÃO (Templates)
-- =====================================================

CREATE TABLE IF NOT EXISTS roteiros_inspecao (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Identificação
  nome VARCHAR(200) NOT NULL,
  codigo VARCHAR(50) UNIQUE COMMENT 'Código identificador do roteiro',
  tipo ENUM('Visita Inicial', 'Visita Regular', 'Visita de Renovação', 'Visita Surpresa', 'Auditoria Interna') NOT NULL,
  
  -- Descrição
  descricao TEXT,
  objetivo TEXT,
  
  -- Checklist (JSON array de objetos)
  -- Estrutura: [{"id": 1, "categoria": "Documentação", "item": "Caderno de campo atualizado?", "obrigatorio": true, "peso": 10}, ...]
  checklist JSON NOT NULL,
  
  -- Critérios
  pontuacao_minima INT DEFAULT 70 COMMENT 'Pontuação mínima para aprovação (%)',
  itens_criticos JSON COMMENT 'IDs dos itens que reprovam automaticamente se não conformes',
  
  -- Status
  ativo BOOLEAN DEFAULT TRUE,
  versao INT DEFAULT 1,
  
  -- Metadados
  created_by INT COMMENT 'user_id de quem criou',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Índices
  INDEX idx_tipo (tipo),
  INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Templates de roteiros de inspeção';

-- =====================================================
-- 5. VISITAS TÉCNICAS
-- =====================================================

CREATE TABLE IF NOT EXISTS visitas_tecnicas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Quem e onde
  associado_id INT NOT NULL,
  propriedade_id INT NOT NULL,
  roteiro_id INT NOT NULL,
  
  -- Quando
  data_visita DATE NOT NULL,
  hora_inicio TIME,
  hora_fim TIME,
  
  -- Equipe
  tecnico_responsavel_id INT COMMENT 'associado_id do técnico responsável',
  acompanhantes TEXT COMMENT 'Nomes separados por vírgula',
  
  -- Observações gerais
  observacoes TEXT,
  condicoes_clima VARCHAR(100) COMMENT 'Ex: Ensolarado, Chuvoso',
  
  -- Resultados
  resultado JSON COMMENT 'Respostas do checklist {item_id: {conforme: true/false, observacao: "...", evidencia_url: "..."}}',
  pontuacao INT COMMENT 'Pontuação final calculada',
  pontuacao_maxima INT COMMENT 'Pontuação máxima possível',
  conformidade ENUM('Conforme', 'Não Conforme', 'Parcialmente Conforme') DEFAULT 'Conforme',
  
  -- Evidências
  evidencias_fotos TEXT COMMENT 'URLs separadas por vírgula',
  relatorio_url VARCHAR(255) COMMENT 'URL do relatório em PDF',
  
  -- Assinaturas
  assinatura_produtor_url VARCHAR(255),
  assinatura_tecnico_url VARCHAR(255),
  
  -- Status
  status ENUM('Agendada', 'Realizada', 'Cancelada', 'Reagendada') DEFAULT 'Agendada',
  motivo_cancelamento TEXT,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Chaves estrangeiras
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
  FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  FOREIGN KEY (roteiro_id) REFERENCES roteiros_inspecao(id),
  FOREIGN KEY (tecnico_responsavel_id) REFERENCES associados(id),
  
  -- Índices
  INDEX idx_associado (associado_id),
  INDEX idx_propriedade (propriedade_id),
  INDEX idx_data (data_visita),
  INDEX idx_status (status),
  INDEX idx_conformidade (conformidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de visitas técnicas realizadas';

-- =====================================================
-- 6. NÃO CONFORMIDADES
-- =====================================================

CREATE TABLE IF NOT EXISTS nao_conformidades (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Origem
  visita_tecnica_id INT NOT NULL,
  item_checklist_id INT COMMENT 'ID do item do checklist que gerou a NC',
  
  -- Descrição da não conformidade
  descricao TEXT NOT NULL,
  gravidade ENUM('Baixa', 'Média', 'Alta', 'Crítica') NOT NULL,
  categoria VARCHAR(100) COMMENT 'Ex: Documentação, Insumos, Práticas Culturais',
  
  -- Ação corretiva
  acao_corretiva TEXT,
  responsavel_correcao VARCHAR(200),
  prazo_correcao DATE,
  
  -- Status de resolução
  status ENUM('Aberta', 'Em Correção', 'Resolvida', 'Não Resolvida', 'Cancelada') DEFAULT 'Aberta',
  data_resolucao DATE,
  evidencia_correcao_url VARCHAR(255) COMMENT 'Foto ou documento comprovando correção',
  verificado_por INT COMMENT 'user_id de quem verificou a correção',
  data_verificacao DATE,
  
  -- Observações
  observacoes TEXT,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Chaves estrangeiras
  FOREIGN KEY (visita_tecnica_id) REFERENCES visitas_tecnicas(id) ON DELETE CASCADE,
  
  -- Índices
  INDEX idx_visita (visita_tecnica_id),
  INDEX idx_gravidade (gravidade),
  INDEX idx_status (status),
  INDEX idx_prazo (prazo_correcao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Não conformidades identificadas nas visitas';

-- =====================================================
-- 7. INSUMOS ORGÂNICOS APROVADOS (Base: IN 46/2011)
-- =====================================================

CREATE TABLE IF NOT EXISTS insumos_organicos_aprovados (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Identificação
  nome VARCHAR(200) NOT NULL,
  nome_comercial VARCHAR(200),
  fabricante VARCHAR(200),
  
  -- Categorização
  categoria ENUM('Fertilizante', 'Defensivo', 'Adjuvante', 'Sementes', 'Mudas', 'Outros') NOT NULL,
  tipo VARCHAR(100) COMMENT 'Ex: Biofertilizante, Óleo mineral, Bacillus thuringiensis',
  subcategoria VARCHAR(100),
  
  -- Aprovação
  permitido BOOLEAN DEFAULT TRUE,
  restricoes TEXT COMMENT 'Condições de uso, limitações',
  normativa VARCHAR(100) DEFAULT 'Portaria 52/2021' COMMENT 'Base legal vigente',
  data_inclusao_normativa DATE,
  
  -- Composição
  composicao TEXT,
  principio_ativo VARCHAR(200),
  
  -- Observações
  observacoes TEXT,
  fonte_referencia VARCHAR(255) COMMENT 'Link para normativa ou documento',
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Índices
  INDEX idx_nome (nome),
  INDEX idx_categoria (categoria),
  INDEX idx_permitido (permitido),
  INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Lista de insumos aprovados para produção orgânica (Portaria 52/2021 e atualizações)';

-- =====================================================
-- FIM DO SCRIPT DE CRIAÇÃO DE SCHEMAS
-- =====================================================
