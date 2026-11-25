-- D3 documentos: arquivo, hash/assinatura e vínculo certificadora × associação.

ALTER TABLE documentos_emitidos
  ADD COLUMN IF NOT EXISTS arquivo VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS hash_arquivo CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS assinatura_hash CHAR(64) NULL;

CREATE TABLE IF NOT EXISTS certificadora_associacoes (
  certificadora_id INT NOT NULL,
  associacao_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (certificadora_id, associacao_id),
  KEY idx_cert_assoc_associacao (associacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscalizacao_evidencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  visita_id BIGINT UNSIGNED NOT NULL,
  nome_arquivo VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  tamanho INT UNSIGNED NOT NULL,
  hash_arquivo CHAR(64) NOT NULL,
  uploaded_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fiscalizacao_evidencias_uuid (uuid),
  KEY idx_fiscalizacao_evidencias_visita (visita_id),
  CONSTRAINT fk_fiscalizacao_evidencias_visita FOREIGN KEY (visita_id) REFERENCES fiscalizacao_visitas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
