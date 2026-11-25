-- =====================================================
-- SINPORG - Seed: Insumos autorizados para PRODUÇÃO ANIMAL ORGÂNICA
-- Base Legal: Portaria MAPA nº 52/2021
--   Anexo I  (item higienização de instalações/equipamentos/utensílios)
--   Anexo II (prevenção e tratamento de enfermidades de animais)
--   Anexo III (alimentação de animais em sistemas orgânicos)
--   Anexo IV (desinfestação, higienização e controle de pragas das colmeias)
-- Atualizações incorporadas: Portaria 811/2023 (ácidos cáprico, caprílico,
--   capróico, láurico) e Portaria 685/2024.
-- =====================================================
-- Este script ESTENDE a tabela insumos_organicos_aprovados com as categorias
-- voltadas à criação animal. Pode ser executado mais de uma vez (é idempotente:
-- remove previamente os registros destas categorias antes de reinserir).
-- =====================================================

-- 1) Estender o ENUM de categorias com as categorias de produção animal
ALTER TABLE insumos_organicos_aprovados
  MODIFY COLUMN categoria ENUM('Fertilizante','Defensivo','Adjuvante','Sementes','Mudas','Outros','Alimentação Animal','Suplemento Mineral','Aditivo Tecnológico','Sanidade Animal','Higienização','Apicultura') NOT NULL;

-- 2) Limpar as categorias de produção animal para permitir execução idempotente
DELETE FROM insumos_organicos_aprovados
WHERE categoria IN ('Alimentação Animal','Suplemento Mineral','Aditivo Tecnológico','Sanidade Animal','Higienização','Apicultura');

-- =====================================================
-- ALIMENTAÇÃO ANIMAL (Anexo III - alimentos)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Algas e seus derivados', 'Alimentação Animal', 'Alimento', 'Forragens', TRUE, 'Algas marinhas devem ser lavadas para reduzir o teor de iodo', 'Portaria 52/2021', 'Fonte de minerais e oligoelementos', 'Anexo III'),
('Aminoácidos', 'Alimentação Animal', 'Suplemento', 'Aminoácidos', TRUE, 'Atendidos os critérios do art. 60 (sem similar natural de fonte natural disponível, para prevenção de doenças carenciais)', 'Portaria 52/2021', 'Vedado uso para aumento de produtividade', 'Anexo III'),
('Enzimas', 'Alimentação Animal', 'Aditivo', 'Enzimas', TRUE, 'Desde que de origem natural', 'Portaria 52/2021', 'Aditivo digestivo', 'Anexo III'),
('Extratos proteicos vegetais', 'Alimentação Animal', 'Alimento', 'Proteicos', TRUE, NULL, 'Portaria 52/2021', 'Fonte de proteína vegetal', 'Anexo III'),
('Forragens e outros alimentos grosseiros e seus derivados', 'Alimentação Animal', 'Alimento', 'Forragens', TRUE, 'Deve compor ao menos 60% da matéria seca da dieta de herbívoros (50% em lactação por até 3 meses)', 'Portaria 52/2021', 'Pastagem, silagem, feno e derivados', 'Anexo III'),
('Frutas e seus derivados', 'Alimentação Animal', 'Alimento', 'Frutas', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Grãos de cereais, seus produtos e subprodutos', 'Alimentação Animal', 'Alimento', 'Cereais', TRUE, 'Proibidos compostos nitrogenados não proteicos e nitrogênio sintético (art. 35)', 'Portaria 52/2021', 'Milho, trigo, aveia, cevada, arroz e subprodutos', 'Anexo III'),
('Hortaliças e seus derivados', 'Alimentação Animal', 'Alimento', 'Hortaliças', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Leite, produtos e subprodutos lácteos', 'Alimentação Animal', 'Alimento', 'Lácteos', TRUE, 'Lactose em pó somente extraída por meio de tratamento físico', 'Portaria 52/2021', 'Inclusive para aleitamento artificial', 'Anexo III'),
('Melaço', 'Alimentação Animal', 'Alimento', 'Energéticos', TRUE, NULL, 'Portaria 52/2021', 'Fonte de energia', 'Anexo III'),
('Microrganismos', 'Alimentação Animal', 'Aditivo', 'Probióticos', TRUE, NULL, 'Portaria 52/2021', 'Probióticos e fermentos', 'Anexo III'),
('Óleos e gorduras', 'Alimentação Animal', 'Alimento', 'Energéticos', TRUE, 'De fontes naturais', 'Portaria 52/2021', 'Fonte de energia', 'Anexo III'),
('Peixes, outros animais aquáticos e derivados', 'Alimentação Animal', 'Alimento', 'Proteicos', TRUE, 'Atender legislação específica', 'Portaria 52/2021', 'Farinha de peixe e derivados', 'Anexo III'),
('Pós e extratos de plantas', 'Alimentação Animal', 'Alimento', 'Fitogênicos', TRUE, NULL, 'Portaria 52/2021', 'Fitoterápicos nutricionais', 'Anexo III'),
('Produtos de animais terrestres e seus derivados', 'Alimentação Animal', 'Alimento', 'Proteicos', TRUE, 'Atender legislação específica', 'Portaria 52/2021', 'Farinha de sangue, farinha de carne e ossos, entre outros', 'Anexo III'),
('Sal marinho', 'Alimentação Animal', 'Mineral', 'Sal', TRUE, 'O produto não pode ser refinado', 'Portaria 52/2021', NULL, 'Anexo III'),
('Sementes ou frutos de leguminosas, de oleaginosas e outras e seus derivados', 'Alimentação Animal', 'Alimento', 'Proteicos', TRUE, NULL, 'Portaria 52/2021', 'Soja, girassol, algodão e derivados', 'Anexo III'),
('Tubérculos, raízes e seus derivados', 'Alimentação Animal', 'Alimento', 'Energéticos', TRUE, NULL, 'Portaria 52/2021', 'Mandioca, batata, etc.', 'Anexo III');

-- =====================================================
-- ADITIVOS TECNOLÓGICOS (Anexo III - aglutinantes, antiaglomerantes,
-- coagulantes e conservantes de silagem)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Ácido acético', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 52/2021', 'Aditivo de silagem', 'Anexo III'),
('Ácido butírico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido cáprico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido caprílico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido capróico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido cítrico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido fórmico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, 'Uso permitido apenas para ensilagem', 'Portaria 811/2023', NULL, 'Anexo III'),
('Ácido láctico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 52/2021', 'Aditivo de silagem', 'Anexo III'),
('Ácido láurico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido oxálico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido peracético', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 811/2023', 'Aditivo de silagem', 'Anexo III'),
('Ácido propiônico', 'Aditivo Tecnológico', 'Conservante', 'Silagem', TRUE, NULL, 'Portaria 52/2021', 'Aditivo de silagem', 'Anexo III'),
('Argilas cauliníticas', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, 'Originadas de extração legal', 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III'),
('Bentonita', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, NULL, 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III'),
('Diatomita', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, NULL, 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III'),
('Perlita', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, NULL, 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III'),
('Sepiolita', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, NULL, 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III'),
('Sílica coloidal', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, NULL, 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III'),
('Vermiculita', 'Aditivo Tecnológico', 'Aglutinante', 'Adsorventes', TRUE, NULL, 'Portaria 52/2021', 'Aglutinante, antiaglomerante e coagulante', 'Anexo III');

-- =====================================================
-- SUPLEMENTOS MINERAIS E VITAMÍNICOS (Anexo III - minerais)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Bicarbonato de sódio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', 'Fonte de sódio/bicarbonato', 'Anexo III'),
('Calcário calcítico', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', 'Fonte de cálcio', 'Anexo III'),
('Carbonato de cálcio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato de sódio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Cloreto de sódio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', 'Sal comum', 'Anexo III'),
('Fosfato bicálcico desfluorado', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', 'Fonte de cálcio e fósforo', 'Anexo III'),
('Fosfatos bicálcicos de osso precipitados', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Fosfato monocálcico desfluorado', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Gluconato de cálcio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Lactato de cálcio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Magnésio anidro', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Sal não refinado', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato de magnésio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato de sódio', 'Suplemento Mineral', 'Mineral', 'Macromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato básico de cobalto monohidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato básico de cobre monohidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato de magnésio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato de zinco', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato ferroso', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Carbonato manganoso', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Cloreto de magnésio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo III'),
('Iodato de cálcio anidro', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Iodato de cálcio hexahidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Iodeto de potássio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Molibdato de amônio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Molibdato de sódio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Óxido cúprico', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Óxido de zinco', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Óxido férrico', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Óxido manganoso e óxido mangânico', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Selenato de sódio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Selenito de sódio', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato de cobalto mono ou heptahidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato de cobre penta-hidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato de zinco mono ou heptahidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato ferroso monohidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III'),
('Sulfato manganoso mono ou tetrahidratado', 'Suplemento Mineral', 'Mineral', 'Micromineral', TRUE, 'Permitido desde que não contenha resíduos contaminantes oriundos do processo de fabricação', 'Portaria 52/2021', NULL, 'Anexo III');

-- =====================================================
-- SANIDADE ANIMAL (Anexo II - prevenção e tratamento de enfermidades)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Aminoácidos', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, 'Atendidos os critérios do art. 60', 'Portaria 52/2021', 'Prevenção de doenças carenciais', 'Anexo II'),
('Enzimas', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, 'Desde que de origem natural', 'Portaria 52/2021', NULL, 'Anexo II'),
('Florais', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Iodo e seus derivados', 'Sanidade Animal', 'Sanitário', 'Antisséptico', TRUE, 'A tintura de iodo não deve ter uso frequente como antisséptico (ex.: pré e pós-dipping)', 'Portaria 52/2021', NULL, 'Anexo II'),
('Ácido acético', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido cítrico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido butírico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido fórmico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido láctico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido peracético', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido propiônico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido oxálico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido cáprico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido caprílico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido capróico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Ácido láurico', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo II'),
('Microrganismos', 'Sanidade Animal', 'Sanitário', 'Biológico', TRUE, NULL, 'Portaria 52/2021', 'Probióticos/terapêuticos biológicos', 'Anexo II'),
('Minerais', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Permanganato de potássio', 'Sanidade Animal', 'Sanitário', 'Terapêutico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Peróxido de hidrogênio', 'Sanidade Animal', 'Sanitário', 'Antisséptico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Plantas medicinais, drogas vegetais e seus derivados', 'Sanidade Animal', 'Sanitário', 'Fitoterápico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Preparados homeopáticos e biodinâmicos', 'Sanidade Animal', 'Sanitário', 'Homeopático', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Própolis', 'Sanidade Animal', 'Sanitário', 'Fitoterápico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo II'),
('Sabões e detergentes biodegradáveis', 'Sanidade Animal', 'Higienizante', 'Limpeza', TRUE, 'Devem ser biodegradáveis', 'Portaria 52/2021', NULL, 'Anexo II'),
('Veículos inertes', 'Sanidade Animal', 'Sanitário', 'Veículo', TRUE, NULL, 'Portaria 52/2021', 'Excipientes de formulações', 'Anexo II'),
('Vitaminas e pró-vitaminas', 'Sanidade Animal', 'Sanitário', 'Vitamínico', TRUE, 'Atendidos os critérios do art. 60; vedado uso para aumento de produtividade', 'Portaria 52/2021', 'Prevenção de doenças carenciais', 'Anexo II');

-- =====================================================
-- HIGIENIZAÇÃO (Anexo I - instalações, equipamentos e utensílios)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Ácido acético', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Ácido butírico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido cáprico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido caprílico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido capróico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido cítrico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Ácido fórmico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido fosfórico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, 'Desde que como parte da composição de produtos comerciais', 'Portaria 52/2021', NULL, 'Anexo I'),
('Ácido láctico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Ácido láurico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido nítrico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, 'Desde que como parte da composição de detergentes comerciais', 'Portaria 52/2021', NULL, 'Anexo I'),
('Ácido oxálico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Ácido peracético', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Ácido propiônico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo I'),
('Água e vapor', 'Higienização', 'Higienizante', 'Limpeza', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Álcool etílico', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Cal hidratada e cal virgem', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Carbonato de sódio', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Dióxido de cloro', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Extratos vegetais ou essências naturais de plantas', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Hidróxido de sódio (soda cáustica)', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, 'Uso conforme plano de manejo', 'Portaria 52/2021', NULL, 'Anexo I'),
('Hipoclorito de sódio', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Iodóforo e soluções à base de iodo', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Microrganismos (biorremediadores)', 'Higienização', 'Higienizante', 'Biológico', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Oxidantes minerais', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Permanganato de potássio', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Peróxido de hidrogênio', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Sabões e detergentes biodegradáveis', 'Higienização', 'Higienizante', 'Limpeza', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I'),
('Sais minerais solúveis', 'Higienização', 'Higienizante', 'Desinfetante', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo I');

-- =====================================================
-- APICULTURA (Anexo IV - desinfestação, higienização e controle de pragas
-- das colmeias)
-- =====================================================
INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia) VALUES
('Ácidos acético, cítrico, butírico, fórmico, láctico, oxálico, peracético e propiônico', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 811/2023', 'Controle de varroa e higienização', 'Anexo IV'),
('Ácidos cáprico, caprílico, capróico e láurico', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 811/2023', NULL, 'Anexo IV'),
('Agentes de controle biológico', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Álcool', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Cal (óxido de cálcio) e cal virgem', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Detergentes biodegradáveis', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Enxofre', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Eucaliptol, mentol e timol', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', 'Controle de varroose', 'Anexo IV'),
('Extratos vegetais', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Hipoclorito de sódio', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Peróxido de hidrogênio', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Potassa cáustica (óxido ou hidróxido de potássio)', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Sabões potássicos e sódicos', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV'),
('Soda cáustica', 'Apicultura', 'Higienizante', 'Colmeias', TRUE, NULL, 'Portaria 52/2021', NULL, 'Anexo IV');

-- =====================================================
-- FIM - Total de insumos de produção animal: 143
-- =====================================================