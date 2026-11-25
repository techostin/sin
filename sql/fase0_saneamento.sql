-- ============================================================================
-- SINPORG — FASE 0: SANEAMENTO DO BANCO `sisagro`
-- ----------------------------------------------------------------------------
-- Objetivo: deixar o schema introspectavel pelo Prisma 7 sem ambiguidade.
-- Alvo: MariaDB 10.4.32 -> 10.11/11.4 LTS (o upgrade e passo separado, manual)
--
-- PRE-REQUISITOS
--   1. Backup verificado:  sql/_backup/sisagro_pre_fase0_20260911.sql (12,23 MB)
--   2. Executar PRIMEIRO em staging.
--   3. Os PASSOS 1-4 sao nao-destrutivos. O PASSO 5 e DESTRUTIVO e esta gated.
--
-- DIAGNOSTICO QUE ORIGINOU ESTE SCRIPT (11/09/2026)
--   - 3 tabelas sem PRIMARY KEY: caderno_campo, cidades, produtores
--   - 1 tabela MyISAM: cidades (utf8_general_ci)
--   - 3 collations distintas: utf8mb4_general_ci (63), utf8mb4_unicode_ci (22),
--     utf8_general_ci (1)
--   - Residuos de uma reescrita Laravel abandonada: users, sessions, cache,
--     cache_locks, jobs, job_batches, failed_jobs, migrations, password_reset_tokens
--   - Coluna com typo: caderno_campo.cerated_at (0 referencias no codigo PHP)
--   - 69 colunas ENUM; acentos estao CORRETOS em UTF-8 (ex.: 'Media' = C3A9)
-- ============================================================================


-- ============================================================================
-- PASSO 0 — VALIDACOES (SOMENTE LEITURA) — rode antes de qualquer ALTER
-- ============================================================================

-- 0.1 Unicidade dos identificadores das tabelas sem PK.
--     Esperado: total = distintos  (senao, tratar duplicatas antes).
SELECT 'caderno_campo' AS tabela, COUNT(*) AS total, COUNT(DISTINCT id) AS distintos
  FROM caderno_campo
UNION ALL SELECT 'cidades',     COUNT(*), COUNT(DISTINCT id) FROM cidades
UNION ALL SELECT 'produtores',  COUNT(*), COUNT(DISTINCT id) FROM produtores;

-- 0.2 Inventario de engine / collation
SELECT ENGINE, TABLE_COLLATION, COUNT(*) AS tabelas
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'sisagro' AND TABLE_TYPE = 'BASE TABLE'
 GROUP BY ENGINE, TABLE_COLLATION
 ORDER BY tabelas DESC;

-- 0.3 Nenhuma FK aponta para as tabelas candidatas a remocao (esperado: 0 linhas)
SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
  FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = 'sisagro'
   AND REFERENCED_TABLE_NAME IN ('produtores','caderno_campo','associacoes_base','users');

-- 0.4 Guardar o volume antes (para comparar depois)
SELECT TABLE_NAME, TABLE_ROWS
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'sisagro' AND TABLE_TYPE = 'BASE TABLE'
 ORDER BY TABLE_NAME;


-- ============================================================================
-- PASSO 1 — PRIMARY KEYs  (nao-destrutivo)
-- Desbloqueia a introspeccao do Prisma: modelos sem @id sao inutilizaveis.
-- `produtores` nao entra aqui: sera removida no PASSO 5 (0 linhas, 0 usos).
-- ============================================================================

-- 1.1 cidades: 5.595 linhas, 5.595 ids distintos, 0 nulos -> seguro
ALTER TABLE `cidades`
  MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (`id`);

-- 1.2 caderno_campo: 0 linhas -> seguro
ALTER TABLE `caderno_campo`
  MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (`id`);


-- ============================================================================
-- PASSO 2 — cidades: MyISAM -> InnoDB, utf8 -> utf8mb4  (nao-destrutivo)
-- MyISAM nao suporta transacao nem FK; e a unica tabela fora do padrao.
-- ============================================================================

ALTER TABLE `cidades` ENGINE = InnoDB;
ALTER TABLE `cidades` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================================
-- PASSO 3 — PADRONIZAR COLLATION DAS DEMAIS TABELAS  (nao-destrutivo)
-- classes/Database.php ja forca utf8mb4_unicode_ci na conexao, mas as colunas
-- nao seguem. Padrao alvo: utf8mb4_unicode_ci.
--
-- ATENCAO: reescreve as 86 tabelas. Estime a janela de manutencao.
-- Gere a lista e revise antes de executar em bloco.
-- ============================================================================

SELECT CONCAT('ALTER TABLE `', TABLE_NAME,
              '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;') AS comando
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'sisagro'
   AND TABLE_TYPE = 'BASE TABLE'
   AND TABLE_COLLATION <> 'utf8mb4_unicode_ci'
 ORDER BY TABLE_NAME;


-- ============================================================================
-- PASSO 4 — CORRIGIR TYPO: caderno_campo.cerated_at -> created_at
-- 0 referencias no codigo PHP. Corrigir agora evita propagar o nome errado
-- para o schema Prisma e para o novo codigo.
-- ============================================================================

ALTER TABLE `caderno_campo`
  CHANGE `cerated_at` `created_at` TIMESTAMP NULL DEFAULT NULL;


-- ============================================================================
-- PASSO 5 — REMOCAO DE TABELAS LEGADAS  *** DESTRUTIVO ***
-- STATUS: APLICADO em 11/09/2026 — 11 tabelas removidas (86 -> 75 tabelas)
-- ----------------------------------------------------------------------------
-- Historico do bloqueio que precedeu este passo:
--   users.id=1  Edmilson  techostin@gmail.com            -> ja existia em usuarios.id=7
--   users.id=2  Joselaine Salete Cibulski  joselainesc@  -> NAO existia em `usuarios`
-- A conta de Joselaine foi migrada ANTES do DROP (ver nota abaixo).
-- ============================================================================

-- ATENCAO — CORRECAO APLICADA DURANTE A EXECUCAO:
--   `password_resets` NAO pode ser removida. A verificacao previa encontrou
--   8 referencias ATIVAS no codigo:
--     classes/PasswordReset.php   -> (token_hash, expires_at, used)
--     modulos/portal/login.php    -> (token, usado, created_at)
--   Remover essa tabela quebraria a recuperacao de senha do sistema.
--
--   ALERTA LATENTE (fora do escopo da Fase 0): as duas telas usam COLUNAS
--   DIFERENTES da mesma tabela. Confirmar se ambas existem.
--
-- Desbloqueio de `users`: a conta orfa de Joselaine Salete Cibulski
-- (joselainesc@gmail.com) foi migrada para `usuarios` (id 58, nivel 3), com a
-- senha bcrypt original preservada. So entao `users` pudo ser removida.
--
-- Verificacao previa: 0 referencias no codigo PHP para as 11 tabelas abaixo.

-- 5.1 Residuo de uma reescrita Laravel abandonada (9 tabelas)
DROP TABLE `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
           `password_reset_tokens`, `migrations`, `sessions`, `users`;

-- 5.2 Tabelas vazias e sem uso no codigo
DROP TABLE `associacoes_base`, `produtores`;


-- ============================================================================
-- PASSO 6 — VERIFICACAO FINAL
-- ============================================================================

-- 6.1 Nenhuma tabela sem PK (esperado: 0 linhas; `produtores` sai no PASSO 5)
SELECT t.TABLE_NAME
  FROM information_schema.TABLES t
  LEFT JOIN information_schema.TABLE_CONSTRAINTS c
         ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
        AND c.TABLE_NAME   = t.TABLE_NAME
        AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'
 WHERE t.TABLE_SCHEMA = 'sisagro' AND t.TABLE_TYPE = 'BASE TABLE'
   AND c.CONSTRAINT_NAME IS NULL;

-- 6.2 Nenhuma tabela MyISAM (esperado: 0 linhas)
SELECT TABLE_NAME, ENGINE
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'sisagro' AND ENGINE <> 'InnoDB';

-- 6.3 Apenas 1 collation (esperado: 1 linha apos PASSO 3)
SELECT TABLE_COLLATION, COUNT(*) AS tabelas
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'sisagro' AND TABLE_TYPE = 'BASE TABLE'
 GROUP BY TABLE_COLLATION;

-- 6.4 Integridade dos dados preservada
SELECT 'associados' AS tabela, COUNT(*) AS linhas FROM associados
UNION ALL SELECT 'cidades',    COUNT(*) FROM cidades
UNION ALL SELECT 'usuarios',   COUNT(*) FROM usuarios;
