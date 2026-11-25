-- D5 movimentação animal e D6 higienização (extensões).

CREATE TABLE IF NOT EXISTS animal_movimentacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  animal_id INT NOT NULL,
  lote_id INT NULL,
  tipo ENUM('ENTRADA','SAIDA','TRANSFERENCIA','OBITO') NOT NULL,
  data_movimentacao DATE NOT NULL,
  destino VARCHAR(200) NULL,
  observacoes TEXT NULL,
  criado_por INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_animal_mov_uuid (uuid),
  KEY idx_animal_mov_animal (animal_id),
  CONSTRAINT fk_animal_mov_animal FOREIGN KEY (animal_id) REFERENCES animais (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
