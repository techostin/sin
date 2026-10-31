-- sql/add_regiao_columns.sql
-- Adicionar colunas de regionalização nas tabelas existentes

-- Adicionar microrregiao_id em cidades (FK para microrregioes_rs)
-- A coluna ibge já existe em cidades, usaremos para fazer o join
ALTER TABLE cidades 
  ADD COLUMN IF NOT EXISTS microrregiao_id INT NULL,
  ADD CONSTRAINT fk_cidades_microrregiao 
  FOREIGN KEY (microrregiao_id) REFERENCES microrregioes_rs(id);

-- Adicionar microrregiao_id em associados (FK para microrregioes_rs)
-- Nota: associados usa campos texto cidade/estado, não FK para cidades
ALTER TABLE associados 
  ADD COLUMN IF NOT EXISTS microrregiao_id INT NULL,
  ADD CONSTRAINT fk_associados_microrregiao 
  FOREIGN KEY (microrregiao_id) REFERENCES microrregioes_rs(id);

-- Adicionar cluster_comunicacao em associados
ALTER TABLE associados 
  ADD COLUMN IF NOT EXISTS cluster_comunicacao VARCHAR(50) NULL;

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_cidades_microrregiao ON cidades(microrregiao_id);
CREATE INDEX IF NOT EXISTS idx_associados_microrregiao ON associados(microrregiao_id);
CREATE INDEX IF NOT EXISTS idx_associados_cluster ON associados(cluster_comunicacao);