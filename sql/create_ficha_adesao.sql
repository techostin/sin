-- =============================================================================
-- Ficha de Adesão ao Quadro Associativo (AAES/RS)
-- Migração: tabelas ficha_adesao e ficha_adesao_produtos
--
-- A ficha eletrônica reproduz a "Ficha de Cadastro de Associado" (/doc),
-- com os 7 blocos: Identificação Pessoal, Endereço, Contato, Dados da
-- Propriedade Rural, Produção Agroecológica, Dados Bancários e Observações.
--
-- Fluxo de status:
--   rascunho  -> ficha criada/salva sem submissão (modal reaparece a cada acesso)
--   pendente  -> submetida, aguardando análise da Diretoria/Presidência
--   aprovada  -> Diretoria aprovou: numero_cadastro e data_entrada gerados
--   recusada  -> Diretoria recusou com parecer; associado edita e reenvia
-- =============================================================================

CREATE TABLE IF NOT EXISTS ficha_adesao (
    id INT(11) NOT NULL AUTO_INCREMENT,
    associado_id INT(11) NOT NULL,

    -- Cabeçalho da ficha
    numero_cadastro VARCHAR(20) DEFAULT NULL COMMENT 'Gerado automaticamente na aprovação',
    data_entrada DATE DEFAULT NULL COMMENT 'Data de entrada no quadro associativo (aprovacao)',
    tipo_associado ENUM('agricultor_familiar','colaborador','honorario','institucional') DEFAULT NULL,

    -- 1. Identificação Pessoal
    nome_completo VARCHAR(200) DEFAULT NULL,
    nome_social VARCHAR(200) DEFAULT NULL,
    data_nascimento DATE DEFAULT NULL,
    cpf VARCHAR(14) DEFAULT NULL,
    rg_orgao_emissor VARCHAR(100) DEFAULT NULL COMMENT 'RG / Órgão emissor',
    sexo_genero VARCHAR(40) DEFAULT NULL,
    nacionalidade VARCHAR(60) DEFAULT NULL,
    estado_civil VARCHAR(40) DEFAULT NULL,
    escolaridade VARCHAR(60) DEFAULT NULL,

    -- 2. Endereço
    logradouro VARCHAR(200) DEFAULT NULL COMMENT 'Logradouro / Linha / Comunidade',
    numero VARCHAR(20) DEFAULT NULL COMMENT 'Número / Lote',
    complemento VARCHAR(200) DEFAULT NULL COMMENT 'Complemento / Localidade',
    bairro VARCHAR(100) DEFAULT NULL COMMENT 'Bairro / Distrito',
    municipio VARCHAR(100) DEFAULT NULL,
    estado CHAR(2) DEFAULT NULL,
    cep VARCHAR(9) DEFAULT NULL,
    zona ENUM('rural','urbana','periurbana') DEFAULT NULL,
    latitude_longitude VARCHAR(80) DEFAULT NULL COMMENT 'Latitude / Longitude (opcional)',
    georreferenciamento_sncr VARCHAR(120) DEFAULT NULL COMMENT 'Georreferenciamento SNCR (opcional)',

    -- 3. Contato
    telefone_whatsapp VARCHAR(20) DEFAULT NULL COMMENT 'Telefone / WhatsApp',
    telefone_alternativo VARCHAR(20) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,

    -- 4. Dados da Propriedade Rural
    nome_propriedade VARCHAR(200) DEFAULT NULL COMMENT 'Nome / Denominação da propriedade',
    nirf_itr VARCHAR(30) DEFAULT NULL COMMENT 'Nº do NIRF / ITR',
    area_total_ha DECIMAL(10,2) DEFAULT NULL,
    area_cultivada_ha DECIMAL(10,2) DEFAULT NULL,
    area_preservacao_ha DECIMAL(10,2) DEFAULT NULL,
    situacao_fundiaria ENUM('propria','arrendada','parceria','assentamento','outra') DEFAULT NULL,
    dap_caf VARCHAR(40) DEFAULT NULL COMMENT 'DAP / CAF (nº)',
    pessoas_familia INT(11) DEFAULT NULL COMMENT 'Nº de pessoas na família',
    pessoas_trabalham INT(11) DEFAULT NULL COMMENT 'Nº de pessoas que trabalham na propriedade',
    renda_mensal_estimada DECIMAL(12,2) DEFAULT NULL COMMENT 'Renda mensal estimada da propriedade (R$)',

    -- 5. Produção Agroecológica
    tempo_producao_agroecologica VARCHAR(40) DEFAULT NULL COMMENT 'Tempo de produção agroecológica (anos)',
    certificacao_organica VARCHAR(200) DEFAULT NULL,
    tipo_certificacao ENUM('nao_possui','auditagem','ocs','opac') DEFAULT NULL,
    sistemas_producao SET(
        'horticultura','fruticultura','olericultura','graos_cereais','pecuaria_leite',
        'avicultura','apicultura','ervas_medicinais','plantas_ornamentais','agrofloresta_saf',
        'silvicultura','aquicultura','beneficiamento','artesanato_rural','outros'
    ) DEFAULT NULL,
    canais_comercializacao SET('feiras_livres','paa_pnae','csa','ceasa','mercado_local','ecommerce') DEFAULT NULL,
    outra_organizacao VARCHAR(200) DEFAULT NULL COMMENT 'Participa de outra organização / cooperativa? Qual?',

    -- 6. Dados Bancários (para recebimentos / repasses)
    banco VARCHAR(80) DEFAULT NULL,
    agencia VARCHAR(20) DEFAULT NULL,
    conta VARCHAR(30) DEFAULT NULL COMMENT 'Conta (corrente / poupança)',
    chave_pix VARCHAR(120) DEFAULT NULL,
    titular_conta VARCHAR(120) DEFAULT NULL,

    -- 7. Observações / Informações Complementares
    observacoes TEXT,

    -- Assinatura / aceite
    aceite_termos TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Declaração de veracidade + LGPD',

    -- Fluxo de avaliação da Diretoria/Presidência
    status ENUM('rascunho','pendente','aprovada','recusada') NOT NULL DEFAULT 'rascunho',
    data_submissao DATETIME DEFAULT NULL,
    data_analise DATETIME DEFAULT NULL,
    analisado_por INT(11) DEFAULT NULL COMMENT 'id do associado/usuario que avaliou',
    parecer_diretoria TEXT COMMENT 'Motivo da recusa / observações da diretoria',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_ficha_associado (associado_id),
    KEY idx_ficha_status (status),
    CONSTRAINT fk_ficha_adesao_associado FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Principais produtos / culturas (tabela de até 5 linhas da ficha)
CREATE TABLE IF NOT EXISTS ficha_adesao_produtos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    ficha_id INT(11) NOT NULL,
    produto_cultura VARCHAR(150) DEFAULT NULL,
    variedade_especie VARCHAR(150) DEFAULT NULL,
    producao_estimada VARCHAR(80) DEFAULT NULL,
    unidade VARCHAR(40) DEFAULT NULL,
    destino_principal VARCHAR(150) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_fap_ficha (ficha_id),
    CONSTRAINT fk_fap_ficha FOREIGN KEY (ficha_id) REFERENCES ficha_adesao(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
