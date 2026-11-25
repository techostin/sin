-- sql/fix_microrregiao_codes.sql
-- Corrigir códigos IBGE das microrregiões para códigos reais do IBGE (5 dígitos)
-- Formato: 43 + 3 dígitos da microrregião

UPDATE microrregioes_rs SET codigo_ibge = 43001 WHERE id = 1;   -- Porto Alegre
UPDATE microrregioes_rs SET codigo_ibge = 43002 WHERE id = 2;   -- Canoas
UPDATE microrregioes_rs SET codigo_ibge = 43003 WHERE id = 3;   -- Novo Hamburgo
UPDATE microrregioes_rs SET codigo_ibge = 43004 WHERE id = 4;   -- São Jerônimo
UPDATE microrregioes_rs SET codigo_ibge = 43005 WHERE id = 5;   -- Gramado-Canela
UPDATE microrregioes_rs SET codigo_ibge = 43006 WHERE id = 6;   -- Osório
UPDATE microrregioes_rs SET codigo_ibge = 43007 WHERE id = 7;   -- Tramandaí

UPDATE microrregioes_rs SET codigo_ibge = 43008 WHERE id = 8;   -- Vacaria
UPDATE microrregioes_rs SET codigo_ibge = 43009 WHERE id = 9;   -- Caxias do Sul
UPDATE microrregioes_rs SET codigo_ibge = 43010 WHERE id = 10;  -- Guaporé
UPDATE microrregioes_rs SET codigo_ibge = 43011 WHERE id = 11;  -- Veranópolis
UPDATE microrregioes_rs SET codigo_ibge = 43012 WHERE id = 12;  -- São Marcos
UPDATE microrregioes_rs SET codigo_ibge = 43013 WHERE id = 13;  -- Soledade
UPDATE microrregioes_rs SET codigo_ibge = 43014 WHERE id = 14;  -- Passo Fundo
UPDATE microrregioes_rs SET codigo_ibge = 43015 WHERE id = 15;  -- Carazinho
UPDATE microrregioes_rs SET codigo_ibge = 43016 WHERE id = 16;  -- Não-Me-Toque
UPDATE microrregioes_rs SET codigo_ibge = 43017 WHERE id = 17;  -- Cerro Largo

UPDATE microrregioes_rs SET codigo_ibge = 43018 WHERE id = 18;  -- Santa Maria
UPDATE microrregioes_rs SET codigo_ibge = 43019 WHERE id = 19;  -- Santiago
UPDATE microrregioes_rs SET codigo_ibge = 43020 WHERE id = 20;  -- Restinga Seca
UPDATE microrregioes_rs SET codigo_ibge = 43021 WHERE id = 21;  -- Cachoeira do Sul

UPDATE microrregioes_rs SET codigo_ibge = 43022 WHERE id = 22;  -- Cruz Alta
UPDATE microrregioes_rs SET codigo_ibge = 43023 WHERE id = 23;  -- Ijuí
UPDATE microrregioes_rs SET codigo_ibge = 43024 WHERE id = 24;  -- Santo Ângelo
UPDATE microrregioes_rs SET codigo_ibge = 43025 WHERE id = 25;  -- São Luiz Gonzaga

UPDATE microrregioes_rs SET codigo_ibge = 43026 WHERE id = 26;  -- Campanha Ocidental
UPDATE microrregioes_rs SET codigo_ibge = 43027 WHERE id = 27;  -- Campanha Central
UPDATE microrregioes_rs SET codigo_ibge = 43028 WHERE id = 28;  -- Campanha Meridional
UPDATE microrregioes_rs SET codigo_ibge = 43029 WHERE id = 29;  -- Serra do Sudeste

UPDATE microrregioes_rs SET codigo_ibge = 43030 WHERE id = 30;  -- Pelotas
UPDATE microrregioes_rs SET codigo_ibge = 43031 WHERE id = 31;  -- Rio Grande

UPDATE microrregioes_rs SET codigo_ibge = 43032 WHERE id = 32;  -- Três Passos
UPDATE microrregioes_rs SET codigo_ibge = 43033 WHERE id = 33;  -- Santa Rosa
UPDATE microrregioes_rs SET codigo_ibge = 43034 WHERE id = 34;  -- São Luiz Gonzaga
UPDATE microrregioes_rs SET codigo_ibge = 43035 WHERE id = 35;  -- Ijuí