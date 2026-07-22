<?php
// Arquivo para criar a view do caderno de campo
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // SQL para criar a view
    $sql = "
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
        
        -- Dados geográficos (usar apenas coordenadas da propriedade)
        p.latitude AS latitude_final,
        p.longitude AS longitude_final
        
    FROM atividades_diarias ad
    LEFT JOIN propriedades p ON ad.propriedade_id = p.id
    LEFT JOIN lavouras l ON ad.lavoura_id = l.id
    LEFT JOIN associados a ON ad.associado_id = a.id
    LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
    
    ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC;
    ";
    
    $db->exec($sql);
    echo "View 'view_caderno_campo_consolidado' criada com sucesso!<br>";
    echo "<a href='modulos/caderno_campo/relatorios/relatorio_geral.php'>Testar relatório</a>";
    
} catch (PDOException $e) {
    echo "Erro ao criar view: " . $e->getMessage();
}
?> 