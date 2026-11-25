-- sql/create_mesorregioes_rs.sql
-- Tabela de mesorregiões do Rio Grande do Sul (IBGE)

CREATE TABLE IF NOT EXISTS mesorregioes_rs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  codigo_ibge INT NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir as 7 mesorregiões oficiais do IBGE para o RS
INSERT INTO mesorregioes_rs (nome, codigo_ibge) VALUES
('Metropolitana de Porto Alegre', 4301),
('Nordeste Rio-grandense', 4302),
('Centro Ocidental Rio-grandense', 4303),
('Centro Oriental Rio-grandense', 4304),
('Sudoeste Rio-grandense', 4305),
('Sudeste Rio-grandense', 4306),
('Noroeste Rio-grandense', 4307);