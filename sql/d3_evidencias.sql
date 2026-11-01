-- D3 evidências: caminho físico do arquivo.
ALTER TABLE fiscalizacao_evidencias
  ADD COLUMN IF NOT EXISTS arquivo VARCHAR(255) NULL AFTER hash_arquivo;
