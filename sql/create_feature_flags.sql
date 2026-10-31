-- Feature flags da modularização (Fase 0 / F1)
-- Aditivo e retrocompatível: não altera tabelas existentes.
-- Aplicar em sisagro e sisagro_staging.

CREATE TABLE IF NOT EXISTS feature_flags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chave VARCHAR(100) NOT NULL,
  descricao VARCHAR(255) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 0,
  escopo VARCHAR(50) NOT NULL DEFAULT 'global',
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_feature_flags_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (chave, descricao, ativo, escopo) VALUES
  ('api.v1', 'API v1 (fundação)', 0, 'global'),
  ('modulos.territorio.v2', 'K2 Território v2', 0, 'global'),
  ('modulos.associados.v2', 'D1 Associados v2', 0, 'global'),
  ('relatorios.v2', 'L1 Relatórios v2', 0, 'global')
ON DUPLICATE KEY UPDATE
  descricao = VALUES(descricao),
  escopo = VALUES(escopo);
