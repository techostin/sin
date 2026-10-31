-- sql/seed_meso_microrregioes_rs.sql
-- Script de povoamento: atualiza cidades do RS com microrregiao_id baseado no código IBGE
-- Executar APÓS criar as tabelas mesorregioes_rs, microrregioes_rs e rodar add_regiao_columns.sql

-- Tabela temporária de mapeamento IBGE -> microrregiao_id
-- Os códigos IBGE de microrregião (7 dígitos) começam com 43 (RS) + 5 dígitos
-- Exemplo: 4301001 = Porto Alegre (microrregião 4301)
-- A tabela microrregioes_rs já tem codigo_ibge (ex: 4301, 4302, etc.)

-- Atualizar cidades do RS com microrregiao_id
-- O código IBGE do município tem 7 dígitos; os 4 primeiros correspondem à microrregião
UPDATE cidades c
JOIN microrregioes_rs m ON FLOOR(c.ibge / 1000) = m.codigo_ibge
SET c.microrregiao_id = m.id
WHERE c.estado = (SELECT id FROM estados WHERE uf = 'RS');

-- Verificar resultado
-- SELECT COUNT(*) as total_rs FROM cidades WHERE estado = (SELECT id FROM estados WHERE uf = 'RS');
-- SELECT COUNT(*) as com_microrregiao FROM cidades WHERE uf = 'RS' AND microrregiao_id IS NOT NULL;
-- SELECT m.nome as mesorregiao, mi.nome as microrregiao, COUNT(c.id) as qtd_cidades
-- FROM cidades c
-- JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
-- JOIN mesorregioes_rs m ON mi.mesorregiao_id = m.id
-- WHERE c.estado = (SELECT id FROM estados WHERE uf = 'RS')
-- GROUP BY m.id, mi.id
-- ORDER BY m.nome, mi.nome;

-- Definir clusters de comunicação por microrregião (exemplos)
-- Isso pode ser ajustado conforme necessidade
UPDATE associados a
JOIN cidades c ON a.cidade = c.nome AND a.estado = (SELECT uf FROM estados WHERE id = c.estado)
JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
SET a.microrregiao_id = mi.id,
    a.cluster_comunicacao = CASE
        WHEN mi.mesorregiao_id = 1 THEN 'METROPOLE'
        WHEN mi.mesorregiao_id = 2 THEN 'SERRA_NORDESTE'
        WHEN mi.mesorregiao_id = 3 THEN 'CENTRO_OESTE'
        WHEN mi.mesorregiao_id = 4 THEN 'NOROESTE_CENTRAL'
        WHEN mi.mesorregiao_id = 5 THEN 'FRONTEIRA_OESTE'
        WHEN mi.mesorregiao_id = 6 THEN 'SUL_LITORAL'
        WHEN mi.mesorregiao_id = 7 THEN 'NOROESTE'
        ELSE 'OUTRO'
    END
WHERE a.estado = 'RS' AND a.microrregiao_id IS NULL;