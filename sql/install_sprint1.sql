-- =====================================================
-- SINPORG v2.0 - Sprint 1: Script Master de Instalação
-- Executa todos os scripts necessários na ordem correta
-- =====================================================
-- Data: 2026-02-17
-- IMPORTANTE: Execute este arquivo no phpMyAdmin ou MySQL Workbench
-- =====================================================

USE sisagro; -- Ajuste o nome do banco de dados se necessário

-- =====================================================
-- PASSO 1: Criar novas tabelas
-- =====================================================
SOURCE schema_organico_sprint1.sql;

-- Verificar se as tabelas foram criadas
SELECT 'Tabelas criadas:' AS Status;
SHOW TABLES LIKE '%certificacao%';
SHOW TABLES LIKE '%plano_manejo%';
SHOW TABLES LIKE '%roteiros%';
SHOW TABLES LIKE '%visitas%';
SHOW TABLES LIKE '%insumos_organicos%';

-- =====================================================
-- PASSO 2: Alterar tabela insumos existente
-- =====================================================
SOURCE alter_insumos_organico.sql;

-- Verificar alterações
SELECT 'Colunas adicionadas à tabela insumos:' AS Status;
DESCRIBE insumos;

-- =====================================================
-- PASSO 3: Popular lista de insumos orgânicos aprovados
-- =====================================================
SOURCE seed_insumos_aprovados.sql;

-- Verificar quantidade de registros
SELECT 'Total de insumos aprovados cadastrados:' AS Status,COUNT(*) AS Total FROM insumos_organicos_aprovados;
SELECT 'Insumos permitidos:' AS Status, COUNT(*) AS Total FROM insumos_organicos_aprovados WHERE permitido = TRUE;
SELECT 'Insumos não-permitidos (referência):' AS Status, COUNT(*) AS Total FROM insumos_organicos_aprovados WHERE permitido = FALSE;

-- =====================================================
-- PASSO 4: Verificação final de integridade
-- =====================================================

-- Verificar foreign keys
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    information_schema.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = 'sisagro'
    AND TABLE_NAME IN (
        'certificacao_participativa',
        'plano_manejo',
        'plano_manejo_cultivos',
        'roteiros_inspecao',
        'visitas_tecnicas',
        'nao_conformidades',
        'insumos'
    );

-- =====================================================
-- INSTALAÇÃO CONCLUÍDA!
-- =====================================================

SELECT '
╔════════════════════════════════════════════╗
║  SINPORG v2.0 - Sprint 1 Instalada!       ║
║  Database Schema para Produção Orgânica    ║
║  Base Legal: Portaria MAPA 52/2021        ║
╚════════════════════════════════════════════╝

✅ Tabelas criadas: 7
✅ Tabela alterada: 1 (insumos)
✅ Insumos cadastrados: 63 (Portaria 52/2021)
✅ Legislação: ATUALIZADA (substitui IN 46/2011)

📋 Observação Importante:
   Sementes orgânicas serão OBRIGATÓRIAS a partir de março/2026
   (prazo de 5 anos da Portaria 52/2021)

Próximos passos:
1. Criar APIs REST para os novos módulos
2. Implementar interfaces frontend
3. Testar fluxo completo

' AS 'Status da Instalação';
