-- K1 Identidade & Acesso + K3 Plataforma (aditivo e idempotente)
-- Aplicar em sisagro e sisagro_staging.

CREATE TABLE IF NOT EXISTS papeis (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  nome VARCHAR(100) NOT NULL,
  descricao VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_papeis_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(100) NOT NULL,
  descricao VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_permissoes_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS papel_permissao (
  papel_id INT UNSIGNED NOT NULL,
  permissao_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (papel_id, permissao_id),
  CONSTRAINT fk_papel_permissao_papel FOREIGN KEY (papel_id) REFERENCES papeis (id) ON DELETE CASCADE,
  CONSTRAINT fk_papel_permissao_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_papel (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  papel_id INT UNSIGNED NOT NULL,
  escopo_tipo ENUM('global','certificadora','associacao','municipio') NOT NULL DEFAULT 'global',
  escopo_id INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuario_papel (usuario_id, papel_id, escopo_tipo, escopo_id),
  KEY idx_usuario_papel_usuario (usuario_id, ativo),
  CONSTRAINT fk_usuario_papel_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_usuario_papel_papel FOREIGN KEY (papel_id) REFERENCES papeis (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_sequences (
  tipo VARCHAR(50) NOT NULL,
  ano SMALLINT UNSIGNED NOT NULL,
  ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tipo, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO papeis (codigo, nome, descricao) VALUES
  ('admin', 'Administrador', 'Acesso global e configuração'),
  ('gestor_associacao', 'Gestor de associação', 'Associados, financeiro e governança'),
  ('gestor_fiscalizacao', 'Gestor de fiscalização', 'Carteira, revisão e emissão'),
  ('fiscal_campo', 'Fiscal de campo', 'Executa visitas e não conformidades'),
  ('certificador', 'Certificador', 'Revisa e emite documentos'),
  ('produtor', 'Produtor/associado', 'Autoatendimento e caderno de campo'),
  ('diretoria', 'Diretoria', 'Leitura executiva e relatórios'),
  ('agroindustria', 'Agroindústria', 'Transformação e rastreabilidade')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

INSERT INTO permissoes (codigo, descricao) VALUES
  ('associados.read', 'Ler associados'),
  ('associados.write', 'Criar/editar associados'),
  ('associados.approve', 'Aprovar adesões'),
  ('fiscalizacao.read', 'Ler fiscalização'),
  ('fiscalizacao.write', 'Executar visita/NC'),
  ('fiscalizacao.review', 'Revisar e ajustar fiscalização'),
  ('fiscalizacao.certificate', 'Emitir/revogar documento'),
  ('caderno.read', 'Ler caderno de campo'),
  ('caderno.write', 'Registrar caderno de campo'),
  ('financeiro.read', 'Ler financeiro'),
  ('financeiro.write', 'Operar financeiro'),
  ('governanca.read', 'Ler governança'),
  ('governanca.write', 'Operar governança'),
  ('relatorios.read', 'Ler relatórios'),
  ('config.read', 'Ler configuração'),
  ('config.write', 'Alterar configuração'),
  ('portal.read', 'Acessar o portal'),
  ('agroindustria.read', 'Ler agroindústria'),
  ('agroindustria.write', 'Operar agroindústria')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p CROSS JOIN permissoes perm
WHERE p.codigo = 'admin'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('associados.read','associados.write','associados.approve','financeiro.read','financeiro.write','governanca.read','governanca.write','relatorios.read','portal.read')
WHERE p.codigo = 'gestor_associacao'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('associados.read','fiscalizacao.read','fiscalizacao.write','fiscalizacao.review','fiscalizacao.certificate','caderno.read','relatorios.read','portal.read')
WHERE p.codigo = 'gestor_fiscalizacao'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('associados.read','fiscalizacao.read','fiscalizacao.write','caderno.read','portal.read')
WHERE p.codigo = 'fiscal_campo'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('associados.read','fiscalizacao.read','fiscalizacao.review','fiscalizacao.certificate','relatorios.read','portal.read')
WHERE p.codigo = 'certificador'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('associados.read','fiscalizacao.read','caderno.read','caderno.write','relatorios.read','portal.read')
WHERE p.codigo = 'produtor'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('associados.read','fiscalizacao.read','financeiro.read','governanca.read','relatorios.read','portal.read')
WHERE p.codigo = 'diretoria'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, perm.id FROM papeis p JOIN permissoes perm
  ON perm.codigo IN ('agroindustria.read','agroindustria.write','relatorios.read','portal.read')
WHERE p.codigo = 'agroindustria'
ON DUPLICATE KEY UPDATE papel_id = papel_id;

-- Migração inicial: nível legado vira papel compatível, apenas se ainda não houver papel.
INSERT INTO usuario_papel (usuario_id, papel_id, escopo_tipo, escopo_id)
SELECT u.id, p.id, 'global', 0
FROM usuarios u
JOIN papeis p ON p.codigo = CASE
  WHEN u.nivel = 1 THEN 'admin'
  WHEN u.nivel = 2 THEN 'gestor_associacao'
  ELSE 'produtor'
END
WHERE u.status = 'ativo'
  AND NOT EXISTS (SELECT 1 FROM usuario_papel up WHERE up.usuario_id = u.id)
ON DUPLICATE KEY UPDATE usuario_id = usuario_id;

INSERT INTO feature_flags (chave, descricao, ativo, escopo) VALUES
  ('k1.identidade', 'K1 Identidade, papéis e escopos', 0, 'global'),
  ('k3.auditoria', 'K3 Auditoria e documentos', 0, 'global'),
  ('l2.portal', 'L2 Portal e composição por persona', 0, 'global')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), escopo = VALUES(escopo);
