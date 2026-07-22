CREATE TABLE IF NOT EXISTS ocorrencias_manejo (
  id INT PRIMARY KEY AUTO_INCREMENT,
  associado_id INT NOT NULL,
  propriedade_id INT NOT NULL,
  lavoura_id INT DEFAULT NULL,
  
  -- Tipo de ocorrência
  tipo ENUM('Praga', 'Doença', 'Fisiológica/Nutricional', 'Climatológica', 'Outro') NOT NULL,
  nome_comum VARCHAR(150) NOT NULL,
  
  -- Deteção e severidade
  data_deteccao DATE NOT NULL,
  severidade ENUM('Baixa', 'Média', 'Alta') NOT NULL DEFAULT 'Baixa',
  
  -- Descrição do problema
  descricao TEXT,
  
  -- Manejo adotado
  acao_manejo TEXT,
  insumo_utilizado_id INT DEFAULT NULL,
  
  -- Foto evidência
  foto_url VARCHAR(255) DEFAULT NULL,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Chaves estrangeiras
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
  FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  FOREIGN KEY (lavoura_id) REFERENCES lavouras(id) ON DELETE SET NULL,
  FOREIGN KEY (insumo_utilizado_id) REFERENCES insumos(id) ON DELETE SET NULL,
  
  -- Índices
  INDEX idx_associado (associado_id),
  INDEX idx_propriedade (propriedade_id),
  INDEX idx_lavoura (lavoura_id),
  INDEX idx_tipo (tipo),
  INDEX idx_data (data_deteccao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registros de ocorrências de pragas, doenças e manejo ecológico';
