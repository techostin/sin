-- =====================================================
-- SINPORG v2.0 - Sprint 1: Seed Data
-- Lista de Insumos Orgânicos Aprovados
-- =====================================================
-- Base Legal: Portaria MAPA nº 52/2021 (15/03/2021)
-- Atualizações: Portarias 404/2022, 811/2023, 685/2024, 1.201/2024
-- Substitui: IN 46/2011 (revogada)
-- Última atualização legal: Portaria SDA 1.201/2024 (22/11/2024)
-- Data deste script: 2026-02-17
-- =====================================================
-- NOTA: Esta lista contém os principais insumos aprovados.
-- Para lista completa e atualizada, consulte:
-- https://www.gov.br/agricultura/pt-br/assuntos/sustentabilidade/organicos
-- =====================================================

-- Limpar tabela antes de popular (apenas em desenvolvimento)
-- TRUNCATE TABLE insumos_organicos_aprovados;

-- =====================================================
-- FERTILIZANTES E CONDICIONADORES DE SOLO
-- =====================================================

INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, permitido, restricoes, normativa, observacoes) VALUES
('Esterco animal curtido', 'Fertilizante', 'Orgânico', TRUE, 'Deve estar completamente curtido/compostado', 'Portaria 52/2021', 'De animais criados em sistema orgânico ou em conversão'),
('Composto orgânico', 'Fertilizante', 'Orgânico', TRUE, 'Processo de compostagem completo (mínimo 90 dias)', 'Portaria 52/2021', 'Matéria orgânica decomposta aerobicamente'),
('Biofertilizante', 'Fertilizante', 'Biológico', TRUE, 'Produção própria ou certificado', 'Portaria 52/2021', 'Fermentação anaeróbica de matéria orgânica'),
('Bokashi', 'Fertilizante', 'Orgânico', TRUE, 'Fermentação controlada', 'Portaria 52/2021', 'Compostagem acelerada com microrganismos'),
('Húmus de minhoca', 'Fertilizante', 'Orgânico', TRUE, NULL, 'Portaria 52/2021', 'Vermicomposto'),
('Farinha de ossos', 'Fertilizante', 'Mineral', TRUE, 'De origem animal orgânica', 'Portaria 52/2021', 'Rico em fósforo e cálcio'),
('Farinha de sangue', 'Fertilizante', 'Orgânico', TRUE, 'De origem animal orgânica', 'Portaria 52/2021', 'Rico em nitrogênio'),
('Farinha de peixe', 'Fertilizante', 'Orgânico', TRUE, 'De origem orgânica ou selvagem', 'Portaria 52/2021', 'Rico em nitrogênio e fósforo'),
('Torta de mamona', 'Fertilizante', 'Vegetal', TRUE, 'Subproduto da extração de óleo', 'Portaria 52/2021', 'Rico em nitrogênio'),
('Torta de neem', 'Fertilizante', 'Vegetal', TRUE, 'Subproduto da extração de óleo', 'Portaria 52/2021', 'Também tem ação repelente'),
('Pó de rocha (calcário)', 'Fertilizante', 'Mineral', TRUE, 'Moagem de rochas', 'Portaria 52/2021', 'Remineralizador de solo'),
('Pó de rocha (basalto)', 'Fertilizante', 'Mineral', TRUE, 'Moagem de rochas', 'Portaria 52/2021', 'Remineralizador de solo'),
('Cinzas de madeira', 'Fertilizante', 'Mineral', TRUE, 'De madeira não tratada', 'Portaria 52/2021', 'Rica em potássio'),
('Guano', 'Fertilizante', 'Orgânico', TRUE, 'De origem natural', 'Portaria 52/2021', 'Fezes de aves marinhas'),
('Sulfato de magnésio', 'Fertilizante', 'Mineral', TRUE, 'Uso limitado', 'Portaria 52/2021', 'Correção de deficiência de magnésio'),
('Sulfato de potássio', 'Fertilizante', 'Mineral', TRUE, 'Proveniente de processos naturais', 'Portaria 52/2021', 'Fonte de potássio');

-- =====================================================
-- ADUBAÇÃO VERDE E COBERTURA MORTA
-- =====================================================

INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, permitido, restricoes, normativa, observacoes) VALUES
('Crotalária', 'Outros', 'Adubação Verde', TRUE, NULL, 'Portaria 52/2021', 'Leguminosa para adubação verde'),
('Mucuna preta', 'Outros', 'Adubação Verde', TRUE, NULL, 'Portaria 52/2021', 'Leguminosa para adubação verde'),
('Feijão de porco', 'Outros', 'Adubação Verde', TRUE, NULL, 'Portaria 52/2021', 'Leguminosa para adubação verde'),
('Aveia preta', 'Outros', 'Adubação Verde', TRUE, NULL, 'Portaria 52/2021', 'Gramínea para cobertura de solo'),
('Palha de arroz', 'Outros', 'Cobertura Morta', TRUE, NULL, 'Portaria 52/2021', 'Cobertura vegetal'),
('Palha de trigo', 'Outros', 'Cobertura Morta', TRUE, NULL, 'Portaria 52/2021', 'Cobertura vegetal'),
('Serragem', 'Outros', 'Cobertura Morta', TRUE, 'De madeira não tratada', 'Portaria 52/2021', 'Cobertura vegetal');

-- =====================================================
-- DEFENSIVOS NATURAIS E BIOLÓGICOS
-- =====================================================

INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, permitido, restricoes, normativa, observacoes) VALUES
('Calda bordalesa', 'Defensivo', 'Fungicida', TRUE, 'Uso limitado, máx 6kg Cu/ha/ano', 'Portaria 52/2021', 'Sulfato de cobre + cal'),
('Calda sulfocálcica', 'Defensivo', 'Fungicida/Acaricida', TRUE, NULL, 'Portaria 52/2021', 'Enxofre + cal'),
('Óleo de neem', 'Defensivo', 'Inseticida', TRUE, 'Extração a frio', 'Portaria 52/2021', 'Azadiractina (princípio ativo)'),
('Bacillus thuringiensis', 'Defensivo', 'Inseticida biológico', TRUE, NULL, 'Portaria 52/2021', 'Bactéria entomopatogênica'),
('Trichoderma sp.', 'Defensivo', 'Fungicida biológico', TRUE, NULL, 'Portaria 52/2021', 'Fungo antagonista'),
('Beauveria bassiana', 'Defensivo', 'Inseticida biológico', TRUE, NULL, 'Portaria 52/2021', 'Fungo entomopatogênico'),
('Metarhizium anisopliae', 'Defensivo', 'Inseticida biológico', TRUE, NULL, 'Portaria 52/2021', 'Fungo entomopatogênico'),
('Extrato de alho', 'Defensivo', 'Repelente', TRUE, 'Preparado próprio', 'Portaria 52/2021', 'Repelente natural'),
('Extrato de pimenta', 'Defensivo', 'Repelente', TRUE, 'Preparado próprio', 'Portaria 52/2021', 'Repelente natural'),
('Extrato de fumo', 'Defensivo', 'Inseticida', TRUE, 'Preparado próprio, uso restrito', 'Portaria 52/2021', 'Nicotina (princípio ativo)'),
('Sabão de coco', 'Defensivo', 'Inseticida', TRUE, 'Sabão neutro natural', 'Portaria 52/2021', 'Adjuvante e inseticida'),
('Óleo mineral', 'Defensivo', 'Inseticida', TRUE, 'Uso limitado', 'Portaria 52/2021', 'Para cochonilhas e ácaros'),
('Enxofre', 'Defensivo', 'Fungicida', TRUE, NULL, 'Portaria 52/2021', 'Enxofre elementar'),
('Própolis', 'Defensivo', 'Fungicida/Bactericida', TRUE, NULL, 'Portaria 52/2021', 'Produto apícola'),
('Leite de vaca', 'Defensivo', 'Fungicida', TRUE, 'Uso preventivo', 'Portaria 52/2021', 'Diluído 10% em água'),
('Bicarbonato de sódio', 'Defensivo', 'Fungicida', TRUE, NULL, 'Portaria 52/2021', 'Controle de oídio'),
('Feromônios', 'Defensivo', 'Armadilha', TRUE, NULL, 'Portaria 52/2021', 'Controle por confusão sexual');

-- =====================================================
-- ADJUVANTES E ESPALHANTES
-- =====================================================

INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, permitido, restricoes, normativa, observacoes) VALUES
('Sabão neutro', 'Adjuvante', 'Espalhante', TRUE, 'Deve ser biodegradável', 'Portaria 52/2021', 'Aumenta aderência das caldas'),
('Lecitina de soja', 'Adjuvante', 'Espalhante', TRUE, NULL, 'Portaria 52/2021', 'Espalhante natural'),
('Óleo vegetal', 'Adjuvante', 'Espalhante', TRUE, 'Óleo puro, não refinado', 'Portaria 52/2021', 'Pode ser de soja, girassol, etc');

-- =====================================================
-- SEMENTES E MUDAS (CATEGORIAS GERAIS)
-- =====================================================

INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, permitido, restricoes, normativa, observacoes) VALUES
('Sementes orgânicas certificadas', 'Sementes', 'Orgânicas', TRUE, 'Preferência por variedades crioulas', 'Portaria 52/2021', 'Primeira opção - obrigatório após março/2026'),
('Sementes convencionais não tratadas', 'Sementes', 'Convencionais', TRUE, 'Quando não houver opção orgânica disponível', 'Portaria 52/2021', 'Mediante justificativa - apenas até março/2026'),
('Mudas orgânicas certificadas', 'Mudas', 'Orgânicas', TRUE, NULL, 'Portaria 52/2021', 'Preferência'),
('Mudas convencionais', 'Mudas', 'Convencionais', TRUE, 'Quando não houver opção orgânica', 'Portaria 52/2021', 'Mediante justificativa');

-- =====================================================
-- INSUMOS NÃO PERMITIDOS (Exemplos para referência)
-- =====================================================

INSERT INTO insumos_organicos_aprovados (nome, categoria, tipo, permitido, restricoes, normativa, observacoes) VALUES
('Agrotóxicos sintéticos', 'Defensivo', 'Químico sintético', FALSE, 'PROIBIDO', 'Portaria 52/2021', 'Qualquer herbicida, fungicida ou inseticida sintético'),
('Fertilizantes sintéticos solúveis', 'Fertilizante', 'Químico sintético', FALSE, 'PROIBIDO', 'Portaria 52/2021', 'Ureia, NPK sintético, etc'),
('Sementes transgênicas (OGM)', 'Sementes', 'Transgênico', FALSE, 'PROIBIDO', 'Portaria 52/2021', 'Organismos geneticamente modificados'),
('Hormônios sintéticos', 'Outros', 'Químico sintético', FALSE, 'PROIBIDO', 'Portaria 52/2021', 'Reguladores de crescimento sintéticos'),
('Lodo de esgoto', 'Fertilizante', 'Resíduo urbano', FALSE, 'PROIBIDO', 'Portaria 52/2021', 'Risco de contaminação'),
('Esterco de suínos criados convencionalmente', 'Fertilizante', 'Orgânico', FALSE, 'Uso restrito', 'Portaria 52/2021', 'Alto risco de contaminação por antibióticos e aditivos');

-- =====================================================
-- FIM DO SCRIPT DE SEED DATA
-- =====================================================
-- Total de insumos inseridos: 53
-- Permitidos: 47
-- Não permitidos (referência): 6
-- =====================================================
