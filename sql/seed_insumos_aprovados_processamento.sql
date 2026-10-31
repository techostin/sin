-- =====================================================
-- SINPORG - Seed: Insumos autorizados para PROCESSAMENTO ORGÂNICO
-- Base Legal: IN Conjunta MAPA/MS nº 18/2009 (Anexo III - Aditivos Alimentares
--   e Coadjuvantes de Tecnologia Permitidos no Processamento de Produtos de
--   Origem Vegetal e Animal Orgânicos), alterada pela IN Conjunta nº 24/2011.
--   O Anexo III da IN 18/2009 permanece vigente para processamento (art. 134
--   da Portaria MAPA nº 52/2021 manteve a IN 18/2009).
-- =====================================================
-- Este script ESTENDE a tabela insumos_organicos_aprovados com as categorias
-- de processamento (agroindústria de alimentos orgânicos). É idempotente:
-- remove previamente os registros destas categorias antes de reinserir.
-- =====================================================

-- 1) Estender o ENUM de categorias com as categorias de processamento
ALTER TABLE insumos_organicos_aprovados
  MODIFY COLUMN categoria ENUM('Fertilizante','Defensivo','Adjuvante','Sementes','Mudas','Outros','Alimentação Animal','Suplemento Mineral','Aditivo Tecnológico','Sanidade Animal','Higienização','Apicultura','Aditivo Alimentar','Coadjuvante de Tecnologia') NOT NULL;

-- 2) Limpar as categorias de processamento para permitir execução idempotente
DELETE FROM insumos_organicos_aprovados
WHERE categoria IN ('Aditivo Alimentar','Coadjuvante de Tecnologia');

-- =====================================================
-- ADITIVOS ALIMENTARES (IN 18/2009 - Anexo III)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Ácido algínico', 'Aditivo Alimentar', 'Espessante, estabilizante e gelificante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 400', 'Anexo III (Processamento)'),
('Ácido ascórbico (L-)', 'Aditivo Alimentar', 'Antioxidante e regulador de acidez', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 300', 'Anexo III (Processamento)'),
('Ácido cítrico', 'Aditivo Alimentar', 'Acidulante e regulador de acidez', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 330; ação antioxidante sinérgica', 'Anexo III (Processamento)'),
('Ácido láctico (L-, D- e DL-)', 'Aditivo Alimentar', 'Acidulante e regulador de acidez', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 270', 'Anexo III (Processamento)'),
('Ácido tartárico (L(+)-)', 'Aditivo Alimentar', 'Acidulante', 'Aditivo', TRUE, 'Somente para vinhos, com limite máximo de 0,15 g/100 mL', 'IN 18/2009', 'INS 334', 'Anexo III (Processamento)'),
('Ágar', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 406', 'Anexo III (Processamento)'),
('Alginato de sódio', 'Aditivo Alimentar', 'Espessante, estabilizante e gelificante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 401', 'Anexo III (Processamento)'),
('Aromatizantes', 'Aditivo Alimentar', 'Aromatizante', 'Aditivo', TRUE, 'Somente os naturais', 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Carbonato de amônio', 'Aditivo Alimentar', 'Regulador de acidez e fermento', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 503i', 'Anexo III (Processamento)'),
('Carbonato de cálcio', 'Aditivo Alimentar', 'Regulador de acidez e antiaglomerante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 170i', 'Anexo III (Processamento)'),
('Carbonato de magnésio', 'Aditivo Alimentar', 'Antiaglomerante e regulador de acidez', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 504i; inclui carbonato básico de magnésio', 'Anexo III (Processamento)'),
('Carbonato de potássio', 'Aditivo Alimentar', 'Regulador de acidez', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 501i', 'Anexo III (Processamento)'),
('Carbonato de sódio', 'Aditivo Alimentar', 'Regulador de acidez', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 500i', 'Anexo III (Processamento)'),
('Carragena', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 407; inclui furcelarana, sais de Na e K e musgo irlandês', 'Anexo III (Processamento)'),
('Cera de abelha', 'Aditivo Alimentar', 'Agente de revestimento e glaceante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 901; branca e amarela', 'Anexo III (Processamento)'),
('Citrato trissódico', 'Aditivo Alimentar', 'Regulador de acidez, sequestrante e emulsificante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 331iii; citrato de sódio', 'Anexo III (Processamento)'),
('Cloreto de cálcio', 'Aditivo Alimentar', 'Firmador e sequestrante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 509', 'Anexo III (Processamento)'),
('Cloreto de magnésio', 'Aditivo Alimentar', 'Firmador', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 511', 'Anexo III (Processamento)'),
('Cloreto de potássio', 'Aditivo Alimentar', 'Gelificante e substituto de sal', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 508', 'Anexo III (Processamento)'),
('Corantes', 'Aditivo Alimentar', 'Corante', 'Aditivo', TRUE, 'Somente os naturais (não sintéticos)', 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Dióxido de carbono', 'Aditivo Alimentar', 'Gás de embalagem, acidulante e propulsor', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 290', 'Anexo III (Processamento)'),
('Dióxido de enxofre', 'Aditivo Alimentar', 'Conservante e antioxidante', 'Aditivo', TRUE, 'Somente para vinhos, com limite máximo de 0,01 g/100 g', 'IN 18/2009', 'INS 220; anidrido sulfuroso', 'Anexo III (Processamento)'),
('Dióxido de silício', 'Aditivo Alimentar', 'Antiumectante e antiaglomerante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 551; sílica', 'Anexo III (Processamento)'),
('Edulcorantes', 'Aditivo Alimentar', 'Edulcorante', 'Aditivo', TRUE, 'Somente os naturais (não sintéticos)', 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Gelatina', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 428', 'Anexo III (Processamento)'),
('Goma arábica', 'Aditivo Alimentar', 'Espessante, estabilizante e emulsificante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 414; goma acácia', 'Anexo III (Processamento)'),
('Goma guar', 'Aditivo Alimentar', 'Espessante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 412', 'Anexo III (Processamento)'),
('Goma caroba', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 410; garrofina, alfarroba, jataí', 'Anexo III (Processamento)'),
('Goma xantana', 'Aditivo Alimentar', 'Espessante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 415', 'Anexo III (Processamento)'),
('Hidróxido de cálcio', 'Aditivo Alimentar', 'Regulador de acidez e firmador', 'Aditivo', TRUE, NULL, 'IN 18/2009', 'INS 526', 'Anexo III (Processamento)');

-- =====================================================
-- ADITIVOS ALIMENTARES acrescidos pela IN Conjunta 24/2011
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Ácido málico (D-, L-)', 'Aditivo Alimentar', 'Regulador de acidez', 'Aditivo', TRUE, 'Regulador de acidez para produtos de origem vegetal, exceto vinagres e fermentados acéticos', 'IN 24/2011', 'INS 296', 'Anexo III (Processamento)'),
('Alginato de potássio', 'Aditivo Alimentar', 'Espessante e estabilizante', 'Aditivo', TRUE, NULL, 'IN 24/2011', 'INS 402', 'Anexo III (Processamento)'),
('Bicarbonato de sódio', 'Aditivo Alimentar', 'Fermento químico e regulador de acidez', 'Aditivo', TRUE, 'Para farinha de trigo com adição de aditivos, o limite máximo é 4,5 g/100 g', 'IN 24/2011', 'INS 500ii; carbonato ácido de sódio', 'Anexo III (Processamento)'),
('Bicarbonato de amônio', 'Aditivo Alimentar', 'Fermento químico', 'Aditivo', TRUE, NULL, 'IN 24/2011', 'INS 503ii; carbonato ácido de amônio', 'Anexo III (Processamento)');

-- =====================================================
-- COADJUVANTES DE TECNOLOGIA (IN 18/2009 - Anexo III)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Ácido tartárico', 'Coadjuvante de Tecnologia', 'Acidulante e regulador de acidez', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Albumina de ovo', 'Coadjuvante de Tecnologia', 'Clarificante e emulsificante', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Álcool etílico', 'Coadjuvante de Tecnologia', 'Solvente e extrator', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Bentonita', 'Coadjuvante de Tecnologia', 'Clarificante e agente de filtração', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Caolin', 'Coadjuvante de Tecnologia', 'Antiaglomerante e agente de filtração', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', 'Caulim', 'Anexo III (Processamento)'),
('Cera de carnaúba', 'Coadjuvante de Tecnologia', 'Agente de revestimento', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Culturas de micro-organismos', 'Coadjuvante de Tecnologia', 'Cultura fermentativa', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Ictiocola', 'Coadjuvante de Tecnologia', 'Clarificante e agente de colagem', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', 'Cola de peixe', 'Anexo III (Processamento)'),
('Nitrogênio', 'Coadjuvante de Tecnologia', 'Gás de embalagem', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Oxigênio', 'Coadjuvante de Tecnologia', 'Gás de embalagem', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Perlita', 'Coadjuvante de Tecnologia', 'Agente de filtração', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Terra diatomácea', 'Coadjuvante de Tecnologia', 'Agente de filtração', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', NULL, 'Anexo III (Processamento)'),
('Hidróxido de sódio', 'Coadjuvante de Tecnologia', 'Regulador de acidez e catalisador', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', 'INS 524', 'Anexo III (Processamento)'),
('Lecitinas', 'Coadjuvante de Tecnologia', 'Emulsificante e estabilizante', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', 'INS 322', 'Anexo III (Processamento)'),
('Pectina', 'Coadjuvante de Tecnologia', 'Gelificante, espessante e estabilizante', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', 'INS 440; pectina amidada', 'Anexo III (Processamento)'),
('Sulfato de cálcio', 'Coadjuvante de Tecnologia', 'Firmador e coagulante', 'Coadjuvante', TRUE, NULL, 'IN 18/2009', 'INS 516', 'Anexo III (Processamento)'),
('Tartarato dipotássico', 'Coadjuvante de Tecnologia', 'Estabilizante', 'Coadjuvante', TRUE, 'Somente para produtos de panificação, com limite máximo de 0,5 g/100 g (expresso como ácido tartárico)', 'IN 18/2009', 'INS 336ii; tartarato de potássio', 'Anexo III (Processamento)'),
('Enzimas', 'Coadjuvante de Tecnologia', 'Enzima (coadjuvante de tecnologia)', 'Coadjuvante', TRUE, 'Permitidas desde que atendam à legislação vigente aplicável (art. 10 da IN 18/2009)', 'IN 18/2009', 'Sem lista específica no anexo; uso conforme Boas Práticas de Fabricação', 'Anexo III (Processamento)');

-- =====================================================
-- COADJUVANTES acrescidos pela IN Conjunta 24/2011
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Taninos (ácido tânico)', 'Coadjuvante de Tecnologia', 'Agente de floculação', 'Coadjuvante', TRUE, 'Somente aqueles autorizados para uso em alimentos em regulamentos específicos publicados pelo órgão competente da Saúde', 'IN 24/2011', 'Tratamento de caldo de cana', 'Anexo III (Processamento)'),
('Ácido sulfúrico', 'Coadjuvante de Tecnologia', 'Controle de micro-organismos', 'Coadjuvante', TRUE, 'Usado na produção de bebida alcoólica (controle de leveduras)', 'IN 24/2011', NULL, 'Anexo III (Processamento)');

-- =====================================================
-- FIM - Total de insumos de processamento (agroindústria): 54
-- =====================================================
