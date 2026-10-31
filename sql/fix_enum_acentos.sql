-- ============================================================
-- Correção: ENUMs com valores acentuados importados em charset errado
-- (mojibake: 'N├úo Conforme' no lugar de 'Não Conforme')
-- Corrige as definições para os valores corretos em utf8mb4.
-- ============================================================

ALTER TABLE visitas_tecnicas
  MODIFY COLUMN conformidade ENUM('Conforme','Não Conforme','Parcialmente Conforme') NOT NULL DEFAULT 'Conforme';

ALTER TABLE visitas_tecnicas
  MODIFY COLUMN status ENUM('Agendada','Realizada','Cancelada','Reagendada') NOT NULL DEFAULT 'Agendada';

ALTER TABLE nao_conformidades
  MODIFY COLUMN gravidade ENUM('Baixa','Média','Alta','Crítica') NOT NULL;

ALTER TABLE nao_conformidades
  MODIFY COLUMN status ENUM('Aberta','Em Correção','Resolvida','Não Resolvida','Cancelada') NOT NULL DEFAULT 'Aberta';