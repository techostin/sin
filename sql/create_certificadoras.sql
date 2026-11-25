-- Criação da tabela certificadoras
CREATE TABLE IF NOT EXISTS certificadoras (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(255) NOT NULL,
  cnpj VARCHAR(20),
  endereco VARCHAR(255),
  cidade VARCHAR(120),
  uf VARCHAR(2),
  telefone VARCHAR(50),
  email VARCHAR(150),
  site VARCHAR(255),
  contato VARCHAR(200),
  tipo VARCHAR(100) COMMENT 'Ex: Certificadora SPG/OCS/Outro',
  logo_filename VARCHAR(255) DEFAULT NULL,
  logo_url VARCHAR(255) DEFAULT NULL,
  gestor_id INT NULL COMMENT 'FK para usuarios.id (gestor vinculado)',
  dados_adicionais JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cidade (cidade),
  INDEX idx_uf (uf),
  INDEX idx_gestor (gestor_id),
  FOREIGN KEY (gestor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Lista de certificadoras e metadados' ;