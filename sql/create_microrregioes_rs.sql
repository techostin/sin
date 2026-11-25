-- sql/create_microrregioes_rs.sql
-- Tabela de microrregiões do Rio Grande do Sul (IBGE)

CREATE TABLE IF NOT EXISTS microrregioes_rs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  mesorregiao_id INT NOT NULL,
  nome VARCHAR(100) NOT NULL,
  codigo_ibge INT NOT NULL UNIQUE,
  FOREIGN KEY (mesorregiao_id) REFERENCES mesorregioes_rs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir as 35 microrregiões oficiais do IBGE para o RS

-- Mesorregião: Metropolitana de Porto Alegre (id=1)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(1, 'Porto Alegre', 4301),
(1, 'Canoas', 4302),
(1, 'Novo Hamburgo', 4303),
(1, 'São Jerônimo', 4304),
(1, 'Gramado-Canela', 4305),
(1, 'Osório', 4306),
(1, 'Tramandaí', 4307);

-- Mesorregião: Nordeste Rio-grandense (id=2)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(2, 'Vacaria', 4308),
(2, 'Caxias do Sul', 4309),
(2, 'Guaporé', 4310),
(2, 'Veranópolis', 4311),
(2, 'São Marcos', 4312),
(2, 'Soledade', 4313),
(2, 'Passo Fundo', 4314),
(2, 'Carazinho', 4315),
(2, 'Não-Me-Toque', 4316),
(2, 'Cerro Largo', 4317);

-- Mesorregião: Centro Ocidental Rio-grandense (id=3)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(3, 'Santa Maria', 4318),
(3, 'Santiago', 4319),
(3, 'Restinga Seca', 4320),
(3, 'Cachoeira do Sul', 4321);

-- Mesorregião: Centro Oriental Rio-grandense (id=4)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(4, 'Cruz Alta', 4322),
(4, 'Ijuí', 4323),
(4, 'Santo Ângelo', 4324),
(4, 'São Luiz Gonzaga', 4325);

-- Mesorregião: Sudoeste Rio-grandense (id=5)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(5, 'Campanha Ocidental', 4326),
(5, 'Campanha Central', 4327),
(5, 'Campanha Meridional', 4328),
(5, 'Serra do Sudeste', 4329);

-- Mesorregião: Sudeste Rio-grandense (id=6)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(6, 'Pelotas', 4330),
(6, 'Rio Grande', 4331);

-- Mesorregião: Noroeste Rio-grandense (id=7)
INSERT INTO microrregioes_rs (mesorregiao_id, nome, codigo_ibge) VALUES
(7, 'Três Passos', 4332),
(7, 'Santa Rosa', 4333),
(7, 'São Luiz Gonzaga', 4334),
(7, 'Ijuí', 4335);