-- T1 Rastreabilidade & Cadeia de Custódia (aditivo)
-- Referencia os lotes legados (lotes_producao, animal_lotes, ordens_producao)
-- e cria o registro canônico de eventos/elos/QR.

CREATE TABLE IF NOT EXISTS rastreabilidade_lotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  tipo ENUM('vegetal','animal','insumo','agroindustria') NOT NULL,
  referencia_tabela VARCHAR(50) NOT NULL,
  referencia_id INT NOT NULL DEFAULT 0,
  codigo_publico VARCHAR(40) NOT NULL,
  associado_id INT NULL,
  propriedade_id INT NULL,
  status ENUM('ativo','bloqueado','encerrado') NOT NULL DEFAULT 'ativo',
  payload_json LONGTEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_rastreabilidade_lotes_uuid (uuid),
  UNIQUE KEY uk_rastreabilidade_lotes_codigo (codigo_publico),
  KEY idx_rastreabilidade_lotes_ref (tipo, referencia_tabela, referencia_id),
  KEY idx_rastreabilidade_lotes_associado (associado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rastreabilidade_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lote_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  data_evento DATETIME NOT NULL,
  local VARCHAR(255) NULL,
  descricao VARCHAR(500) NULL,
  payload_json LONGTEXT NULL,
  usuario_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rastreabilidade_eventos_lote (lote_id, data_evento),
  CONSTRAINT fk_rastreabilidade_eventos_lote FOREIGN KEY (lote_id) REFERENCES rastreabilidade_lotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rastreabilidade_elos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  origem_lote_id BIGINT UNSIGNED NOT NULL,
  destino_lote_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('insumo','transformacao','movimentacao') NOT NULL,
  quantidade DECIMAL(12,3) NULL,
  unidade VARCHAR(20) NULL,
  payload_json LONGTEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rastreabilidade_elos_origem (origem_lote_id),
  KEY idx_rastreabilidade_elos_destino (destino_lote_id),
  CONSTRAINT fk_rastreabilidade_elos_origem FOREIGN KEY (origem_lote_id) REFERENCES rastreabilidade_lotes (id) ON DELETE CASCADE,
  CONSTRAINT fk_rastreabilidade_elos_destino FOREIGN KEY (destino_lote_id) REFERENCES rastreabilidade_lotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (chave, descricao, ativo, escopo) VALUES
  ('t1.rastreabilidade', 'T1 Rastreabilidade e cadeia de custodia', 0, 'global'),
  ('d2.caderno_lotes', 'D2 lotes vegetais rastreaveis', 0, 'global'),
  ('d7.insumos_lotes', 'D7 lotes de insumos', 0, 'global')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), escopo = VALUES(escopo);
