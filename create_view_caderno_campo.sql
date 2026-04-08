-- View consolidada para relatórios do Caderno de Campo
-- Baseada na estrutura real das tabelas existentes
CREATE OR REPLACE VIEW view_caderno_campo_consolidado AS
SELECT 
    ad.id,
    ad.nome,
    ad.descricao,
    ad.data_atividade,
    ad.hora_inicio,
    ad.hora_fim,
    ad.tipo_atividade,
    ad.status,
    ad.insumo_utilisado,
    ad.observações AS observacoes,
    ad.criado_em AS data_criacao,
    
    -- Propriedade
    p.propriedade,
    p.endereco AS endereco_propriedade,
    p.area_total AS area_propriedade,
    p.latitude,
    p.longitude,
    
    -- Lavoura
    l.nome AS lavoura,
    l.cultura AS tipo_cultura,
    l.area AS area_plantada,
    l.data_plantio,
    
    -- Categoria de Atividade
    ca.nome AS categoria_atividade,
    ca.descricao AS descricao_categoria,
    ca.cor AS cor_categoria,
    
    -- Usuário/Associado
    a.nome AS nome_usuario,
    a.email AS email_usuario,
    a.telefone AS telefone_usuario,
    a.cpf AS cpf_usuario,
    
    -- Dados geográficos (priorizar coordenadas da lavoura, senão da propriedade)
    COALESCE(l.latitude, p.latitude) AS latitude_final,
    COALESCE(l.longitude, p.longitude) AS longitude_final
    
FROM atividades_diarias ad
LEFT JOIN propriedades p ON ad.propriedade_id = p.id
LEFT JOIN lavouras l ON ad.lavoura_id = l.id
LEFT JOIN associados a ON ad.associado_id = a.id
LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id

ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC; 