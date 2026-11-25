-- Histórico de pareceres e sugestões de ajuste dos Planos de Manejo.
-- Execute como usuário com permissão de CREATE (ex.: root do MySQL/XAMPP):
--   mysql -u root sisagro < scripts/migrar-plano-pareceres.sql

CREATE TABLE IF NOT EXISTS plano_manejo_pareceres (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_manejo_id INT NOT NULL,
    usuario_id INT NULL,
    usuario_tabela VARCHAR(20) NULL,
    status VARCHAR(40) NULL,
    parecer TEXT NULL,
    sugestoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_plano_pareceres (plano_manejo_id, created_at),
    CONSTRAINT fk_plano_pareceres_plano
        FOREIGN KEY (plano_manejo_id) REFERENCES plano_manejo (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
