-- View para Relatório Geral do Caderno de Campo
-- Esta view consolida todos os dados necessários para relatórios completos

CREATE OR REPLACE VIEW view_relatorio_caderno_campo AS
SELECT 
    -- Dados da Atividade Diária
    ad.id AS atividade_id,
    ad.nome AS atividade_nome,
    ad.descricao AS atividade_descricao,
    ad.data_atividade,
    ad.hora_inicio,
    ad.hora_fim,
    ad.status AS atividade_status,
    ad.tipo_atividade,
    ad.insumo_utilisado,
    ad.observações AS atividade_observacoes,
    ad.criado_em AS atividade_criado_em,
    
    -- Dados do Associado/Usuário
    a.id AS associado_id,
    CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome_completo,
    a.nome AS associado_nome,
    a.sobrenome AS associado_sobrenome,
    a.cpf AS associado_cpf,
    a.email AS associado_email,
    a.telefone AS associado_telefone,
    a.endereco AS associado_endereco,
    a.cidade AS associado_cidade,
    a.estado AS associado_estado,
    a.cep AS associado_cep,
    a.tipo AS associado_tipo,
    a.status AS associado_status,
    a.data_cadastro AS associado_data_cadastro,
    
    -- Dados da Propriedade
    p.id AS propriedade_id,
    p.propriedade AS propriedade_nome,
    p.area_total AS propriedade_area_total,
    p.endereco AS propriedade_endereco,
    p.cep AS propriedade_cep,
    p.bairro AS propriedade_bairro,
    p.cidade AS propriedade_cidade,
    p.estado AS propriedade_estado,
    p.latitude AS propriedade_latitude,
    p.longitude AS propriedade_longitude,
    p.observacoes AS propriedade_observacoes,
    p.created_at AS propriedade_criado_em,
    
    -- Dados da Lavoura
    l.id AS lavoura_id,
    l.nome AS lavoura_nome,
    l.cultura AS lavoura_cultura,
    l.area AS lavoura_area,
    l.data_plantio AS lavoura_data_plantio,
    
    -- Dados da Categoria de Atividade
    ca.id AS categoria_id,
    ca.nome AS categoria_nome,
    ca.descricao AS categoria_descricao,
    ca.cor AS categoria_cor,
    ca.created_at AS categoria_criado_em,
    
    -- Dados do Insumo
    i.id AS insumo_id,
    i.nome AS insumo_nome,
    i.descricao AS insumo_descricao,
    i.estoq_anterior AS insumo_estoque_anterior,
    i.quant_recebida AS insumo_quantidade_recebida,
    i.quantidade_estoque AS insumo_quantidade_estoque,
    
    -- Dados do Fornecedor (se houver)
    f.id AS fornecedor_id,
    f.nome AS fornecedor_nome,
    f.representante AS fornecedor_representante,
    f.razao_social AS fornecedor_razao_social,
    f.cnpj AS fornecedor_cnpj,
    f.telefone AS fornecedor_telefone,
    f.email AS fornecedor_email,
    f.endereco AS fornecedor_endereco,
    f.cidade AS fornecedor_cidade,
    f.estado AS fornecedor_estado,
    f.produto_fornecido AS fornecedor_produto_fornecido,
    
    -- Dados do Produto
    prod.id AS produto_id,
    prod.nome AS produto_nome,
    prod.tipo AS produto_tipo,
    prod.unidade_medida AS produto_unidade_medida,
    prod.quantidade_produzida AS produto_quantidade_produzida,
    prod.data_colheita AS produto_data_colheita,
    prod.observacoes AS produto_observacoes,
    prod.criado_em AS produto_criado_em,
    
    -- Dados do Lote de Produção
    lp.id AS lote_id,
    lp.nome_lote AS lote_nome,
    lp.data_producao AS lote_data_producao,
    lp.quantidade AS lote_quantidade,
    lp.unidade_medida AS lote_unidade_medida,
    lp.observacoes AS lote_observacoes,
    lp.criado_em AS lote_criado_em,
    
    -- Cálculos e Informações Adicionais
    TIMESTAMPDIFF(MINUTE, ad.hora_inicio, ad.hora_fim) AS duracao_minutos,
    TIMESTAMPDIFF(HOUR, ad.hora_inicio, ad.hora_fim) AS duracao_horas,
    DATEDIFF(CURDATE(), ad.data_atividade) AS dias_desde_atividade,
    DATEDIFF(CURDATE(), l.data_plantio) AS dias_desde_plantio,
    DATEDIFF(CURDATE(), lp.data_producao) AS dias_desde_producao,
    
    -- Informações de Localização Geográfica
    CONCAT(p.latitude, ', ', p.longitude) AS coordenadas_propriedade,
    
    -- Informações de Endereço Completo
    CONCAT(p.endereco, ', ', p.bairro, ', ', p.cidade, ' - ', p.estado, ' CEP: ', p.cep) AS endereco_completo_propriedade,
    CONCAT(a.endereco, ', ', a.bairro, ', ', a.cidade, ' - ', a.estado, ' CEP: ', a.cep) AS endereco_completo_associado,
    
    -- Informações de Rastreabilidade
    CONCAT('LOTE-', LPAD(lp.id, 6, '0')) AS codigo_rastreabilidade_lote,
    CONCAT('ATIV-', LPAD(ad.id, 6, '0')) AS codigo_rastreabilidade_atividade,
    CONCAT('PROP-', LPAD(p.id, 6, '0')) AS codigo_rastreabilidade_propriedade,
    
    -- Status e Classificações
    CASE 
        WHEN ad.status = 'Concluído' THEN 'Finalizada'
        WHEN ad.status = 'Em andamento' THEN 'Em Execução'
        WHEN ad.status = 'Pendente' THEN 'Aguardando'
        ELSE ad.status
    END AS status_atividade_formatado,
    
    CASE 
        WHEN ad.tipo_atividade = 'adubação' THEN 'Adubação'
        WHEN ad.tipo_atividade = 'capina' THEN 'Capina'
        WHEN ad.tipo_atividade = 'irrigação' THEN 'Irrigação'
        WHEN ad.tipo_atividade = 'encanteramento' THEN 'Encanteramento'
        WHEN ad.tipo_atividade = 'aplicação de bio insumo' THEN 'Aplicação de Bio Insumo'
        WHEN ad.tipo_atividade = 'colheita' THEN 'Colheita'
        WHEN ad.tipo_atividade = 'plantio' THEN 'Plantio'
        WHEN ad.tipo_atividade = 'poda' THEN 'Poda'
        ELSE ad.tipo_atividade
    END AS tipo_atividade_formatado

FROM atividades_diarias ad

-- JOIN com Associados
LEFT JOIN associados a ON ad.associado_id = a.id

-- JOIN com Propriedades
LEFT JOIN propriedades p ON ad.propriedade_id = p.id

-- JOIN com Lavouras
LEFT JOIN lavouras l ON ad.lavoura_id = l.id

-- JOIN com Categorias de Atividades
LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id

-- JOIN com Insumos (baseado no nome do insumo utilizado)
LEFT JOIN insumos i ON ad.insumo_utilisado = i.nome AND i.associado_id = ad.associado_id

-- JOIN com Fornecedores (através do insumo)
LEFT JOIN fornecedores f ON i.fornecedor_id = f.id

-- JOIN com Produtos (através da lavoura)
LEFT JOIN produtos prod ON l.id = prod.lavoura_id

-- JOIN com Lotes de Produção (através do produto)
LEFT JOIN lotes_producao lp ON prod.id = lp.produto_id

-- Filtros para garantir dados válidos
WHERE ad.id IS NOT NULL
  AND a.id IS NOT NULL
  AND p.id IS NOT NULL

-- Ordenação padrão
ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC, ad.id DESC; 