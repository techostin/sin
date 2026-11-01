-- D3 Fiscalização & Conformidade (aditivo)

CREATE TABLE IF NOT EXISTS fiscalizacao_visitas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  associado_id INT NOT NULL,
  propriedade_id INT NULL,
  fiscal_id INT NOT NULL,
  data_agendada DATE NULL,
  status ENUM('agendada','em_andamento','concluida','aprovada','recusada') NOT NULL DEFAULT 'agendada',
  observacoes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fiscalizacao_visitas_uuid (uuid),
  KEY idx_fiscalizacao_visitas_associado (associado_id, status),
  KEY idx_fiscalizacao_visitas_fiscal (fiscal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscalizacao_nao_conformidades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  visita_id BIGINT UNSIGNED NOT NULL,
  descricao VARCHAR(500) NOT NULL,
  gravidade ENUM('baixa','media','alta') NOT NULL DEFAULT 'media',
  prazo DATE NULL,
  status ENUM('aberta','resolvida') NOT NULL DEFAULT 'aberta',
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fiscalizacao_nc_uuid (uuid),
  KEY idx_fiscalizacao_nc_visita (visita_id),
  CONSTRAINT fk_fiscalizacao_nc_visita FOREIGN KEY (visita_id) REFERENCES fiscalizacao_visitas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_emitidos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  numero VARCHAR(40) NOT NULL,
  associado_id INT NULL,
  visita_id BIGINT UNSIGNED NULL,
  lote_uuid CHAR(36) NULL,
  status ENUM('rascunho','emitido','revogado') NOT NULL DEFAULT 'emitido',
  payload_json LONGTEXT NULL,
  emitido_por INT NULL,
  emitido_em DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_documentos_numero (numero),
  UNIQUE KEY uk_documentos_uuid (uuid),
  KEY idx_documentos_associado (associado_id),
  CONSTRAINT fk_documentos_visita FOREIGN KEY (visita_id) REFERENCES fiscalizacao_visitas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (chave, descricao, ativo, escopo) VALUES
  ('d3.fiscalizacao', 'D3 Fiscalizacao e conformidade', 0, 'global'),
  ('d3.documentos', 'D3 Documentos e certificados', 0, 'global')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), escopo = VALUES(escopo);
