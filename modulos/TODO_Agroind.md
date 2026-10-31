# Product Requirement Document (PRD)

## ✅ Status de Implementação (atualizado em 2026-08-04)

Implementado sobre o banco **MySQL `sisagro`** (o PRD usa PostgreSQL como referência). Migração idempotente: `dev-utils/migrate_conformidade.php`.

| Requisito | Status | Onde |
| :--- | :--- | :--- |
| RF01 — Vínculo de Certificação (OCS/SPG/Auditoria) | ✅ | `associados.tipo_certificacao` + campo em `modulos/associados/editar.php` |
| RF02 — Zona de Amortecimento | ✅ | `propriedades.zona_amortecimento` + campo em `propriedades/editar.php` |
| RF03 — Histórico de Conversão (cálculo automático) | ✅ | tabela `historico_conversao` + `classes/HistoricoConversao.php` + página `propriedades/historico_conversao.php` (12m anuais / 24m perenes) |
| RF05 — Manejo Animal (clínico/vacinação/alimentação) | ✅ | tabelas `animais` e `manejo_animal_registros` + `classes/ManejoAnimal.php` + módulo `/manejo-animal` |
| RF06 — Validação de Insumos (Portaria 52/2021) | ✅ | `classes/InsumoValidador.php` + bloqueio em `insumos/cadastrar.php`, `atividade_diaria/cadastrar.php` e `editar.php` + API `validar_insumo.php` |
| RF07 — Receitas / Fluxograma de Processamento | ✅ | `receitas_agroindustria` + módulo `/agroindustria/receitas` |
| RF08 — Segregação Espaço-Temporal (trava 24h) | ✅ | `controle_higienizacao` + bloqueio em `ordens_cadastrar.php` (`haHigienizacaoValida`) |
| RF09 — Percentual Orgânico e Selo (≥95% / 70–95% / <70%) | ✅ | `ReceitaAgroindustria::calcularPercentualOrganico/classificarSelo` |
| RF10 — Link Campo↔Agroindústria | ✅ | `ordens_producao` + `rastreabilidade_insumos_processo` vinculando `lotes_producao` |
| RF11 — Balanço de Massas + Alerta de Fraude | ✅ | `OrdemProducao::calcularBalanco` (tolerância 10%) + status `ALERTA_FRAUDE` |
| RF12 — QR Code do Lote | ✅ | `/agroindustria/rastreabilidade?lote=...` (pública) + QR em `ordens_visualizar.php` |
| NFR01 — Retenção/Soft Delete | ✅ | coluna `excluido` em 6 tabelas + classes/listers/`excluir.php` |
| NFR03 — Auditoria interna | ✅ | tabela `audit_log` + `classes/AuditLog.php` (login, logout, insumos, atividades, ocorrências, lotes, receitas, higienização, OPs, manejo) |
| NFR02 — Offline (IndexedDB/SW) | ⏳ | **Pendente** (etapa separada) |

### Correções de bugs latentes no caminho
- `validar_insumo.php` usava `../../../` (caminho quebrado) → corrigido para `../../`.
- Queries com `insumos.estoque_minimo` (coluna inexistente) quebravam em `api/relatorios/rapido.php` e `api/relatorios/visao-geral.php` → corrigidas para `quantidade_estoque < 10`.

### Testes de validação executados (HTTP + banco)
- Fase 1: bloqueio RF06 em insumo proibido (Glifosato) e em atividade; criação permitida com "Esterco animal curtido"; auditoria de login/logout/ações; soft delete.
- Fase 2: receita 80%+15% orgânicos → 95% → selo `PRODUTO_ORGANICO_BRASIL`; trava 24h (OP 2 dias após higienização bloqueada); OP válida → `APROVADO`; divergência 54% → `ALERTA_FRAUDE`; página pública de rastreabilidade; registros em `audit_log`.
- Fase 3: zona de amortecimento persistida; histórico de conversão com cálculo de meses restantes; `tipo_certificacao=OCS` salvo (com CSRF); animal + registros de vacinação/alimentação criados e auditados.

## 1. Visão Geral do Produto
O objetivo deste sistema é gerenciar unidades de produção familiar orgânica de escopo misto (produção primária animal/vegetal e agroindústria processadora), garantindo total conformidade legal e auditoria simplificada perante os organismos de avaliação da conformidade (OAC) do MAPA.

## 2. Escopo Regulatório Base
*   *Lei nº 10.831/2003*: Dispõe sobre a agricultura orgânica.
*   *Decreto nº 6.323/2007*: Regulamenta os mecanismos de controle e comercialização.
*   *Portaria MAPA nº 52/2021*: Estabelece as regras técnicas operacionais e listas de substâncias permitidas.

---

## 3. Módulos e Requisitos Funcionais

### Módulo 1: Cadastro da Unidade de Produção Orgânica (UPO) e Mecanismos de Controle
*   *RF01 - Vínculo de Certificação*: O sistema deve permitir configurar o tipo de certificação do produtor: Organização de Controle Social (OCS), Sistema Participativo de Garantia (SPG/OPAC) ou Certificação por Auditoria.
*   *RF02 - Mapeamento de Áreas e Barreiras*: Cadastro de mapas/polígonos das parcelas produtivas, exigindo a delimitação de zonas de amortecimento (barreiras físicas) para proteção contra contaminações de vizinhos convencionais.
*   *RF03 - Histórico da Área e Conversão*: Registro cronológico de uso do solo com cálculo automático do período de conversão restante (ex: 12 meses para culturas anuais).

### Módulo 2: Plano de Manejo Orgânico (PMO) - Vegetal e Animal
*   *RF04 - Manejo Vegetal*: Registro de sementes/mudas (identificando se são de origem orgânica ou autorizadas em caráter excepcional), manejo da biodiversidade e conservação do solo.
*   *RF05 - Manejo Animal*: Registro de histórico clínico, calendário de vacinação obrigatória e controle de alimentação (exigência de ração orgânica ou limites de conversão).
*   *RF06 - Validação de Insumos*: Banco de dados integrado com a lista de substâncias permitidas da Portaria 52/2021. O sistema deve emitir um alerta impeditivo se o usuário tentar lançar defensivos sintéticos ou adubos químicos proibidos.

### Módulo 3: Gerenciamento da Agroindústria e Processamento
*   *RF07 - Fluxograma de Processamento*: Cadastro de receitas e etapas de beneficiamento para cada produto agroindustrializado.
*   *RF08 - Segregação Espaço-Temporal*: Cronograma de limpeza e agendamento de lotes. Se a agroindústria processar itens convencionais e orgânicos, o sistema deve exigir o registro do procedimento de higienização prévia (registro de "cleaning") e sanitizantes permitidos.
*   *RF09 - Cálculo de Percentual Orgânico*: Bloqueador de rotulagem com base na receita do produto processado:
    *   *≥ 95% de ingredientes orgânicos*: Permite selo "Produto Orgânico Brasil".
    *   *70% a 95% de ingredientes orgânicos*: Bloqueia o selo, permite apenas a frase "Produto com ingredientes orgânicos".
    *   *< 70% de ingredientes orgânicos*: Bloqueia qualquer menção ao termo orgânico.

### Módulo 4: Rastreabilidade Avançada e Balanço de Massas
*   *RF10 - Link Campo-Agroindústria*: Conexão nativa entre o lote de colheita/coleta primária da UPO com a ordem de produção da agroindústria.
*   *RF11 - Balanço de Massas Intermediário*: O sistema deve cruzar o volume de matéria-prima colhido com o volume final processado. Alertas automáticos devem ser emitidos caso a produção de suco ou geleia exceda a capacidade matemática da matéria-prima colhida (indicativo de fraude ou mistura).
*   *RF12 - Geração de QR Code de Lote*: Emissão automática de etiquetas com QR Code contendo o histórico do produto para livre consulta do consumidor final e fiscais do MAPA.

---

## 4. Requisitos Não Funcionais

### NFR01 - Retenção Histórica de Dados e Auditoria
*   *Regra: Em atendimento às regras do MAPA, todos os diários de campo, notas fiscais de insumos e balanços de produção não podem ser deletados e devem ser guardados por no mínimo **3 anos*.

### NFR02 - Funcionamento Offline (App de Campo)
*   *Regra*: A coleta de dados no campo (aplicações, colheitas, manejos animais) deve funcionar de forma 100% offline, realizando a sincronização de dados via nuvem assim que uma conexão Wi-Fi ou celular for detectada.

### NFR03 - Segurança da Informação e Assinaturas
*   *Regra*: Logs detalhados de auditoria interna (quem inseriu, quando inseriu) para validação por comissões de ética de SPG/OPAC e auditorias externas.

---

## 5. Matriz de Rastreabilidade Regulatória

| Funcionalidade do Sistema | Base Legal Vinculada | Objetivo da Auditoria |
| :--- | :--- | :--- |
| *RF02 / RF06* (Zonas de Amortecimento / Insumos) | Portaria MAPA nº 52/2021 | Garantir que o sistema produtivo está protegido contra derivas químicas de propriedades vizinhas e que não há uso de transgênicos ou defensivos sintéticos. |
| *RF08* (Segregação de Processamento) | Decreto nº 6.323/2007 | Evitar a mistura acidental ou fraude na industrialização mista. |
| *RF09* (Cálculo de Percentual) | Instrução Normativa nº 50/2009 | Assegurar a rotulagem correta no painel frontal do produto industrializado. |
| *RF11 / NFR01* (Balanço de Massa / Guarda de 3 anos) | Lei nº 10.831/2003 / Portaria 52 | Fornecer provas materiais rápidas durante as fiscalizações periódicas do SisOrg. |

-- =========================================================================
-- MÓDULO 1: CADASTRO E CONTROLE REGULATÓRIO
-- =========================================================================

CREATE TYPE tipo_certificacao AS ENUM ('OCS', 'SPG_OPAC', 'AUDITORIA');
CREATE TYPE escopo_producao AS ENUM ('VEGETAL', 'ANIMAL', 'PROCESSADO', 'MISTO');

CREATE TABLE unidades_producao (
    id SERIAL PRIMARY KEY,
    nome_propriedade VARCHAR(150) NOT NULL,
    nome_produtor VARCHAR(150) NOT NULL,
    cpf_cnpj VARCHAR(14) UNIQUE NOT NULL,
    tipo_controle tipo_certificacao NOT NULL,
    nome_oac_ocs VARCHAR(100), -- Nome do Organismo de Avaliação da Conformidade
    numero_registro_mapa VARCHAR(50),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    excluido BOOLEAN DEFAULT FALSE -- Atende à retenção regulatória de 3 anos (Soft Delete)
);

CREATE TABLE parcelas_areas (
    id SERIAL PRIMARY KEY,
    unidade_producao_id INT REFERENCES unidades_producao(id),
    nome_parcela VARCHAR(100) NOT NULL,
    geometria_poligono TEXT, -- Coordenadas GIS da área produtiva
    zona_amortecimento_descricao TEXT NOT NULL, -- Barreiras contra vizinhos convencionais
    status_conversao VARCHAR(50) DEFAULT 'ORGANICO', -- 'EM_CONVERSAO', 'ORGANICO'
    data_inicio_conversao DATE
);

-- =========================================================================
-- MÓDULO 2: PRODUÇÃO PRIMÁRIA E MANEJO
-- =========================================================================

CREATE TABLE insumos_permitidos (
    id SERIAL PRIMARY KEY,
    nome_comercial VARCHAR(150) NOT NULL,
    principio_ativo_substancia VARCHAR(150) NOT NULL,
    categoria VARCHAR(50) NOT NULL, -- 'FERTILIZANTE', 'DEFENSIVO_NATURAL', 'VACINA'
    referencia_portaria_52_2021 VARCHAR(100) NOT NULL -- Base legal no sistema
);

CREATE TABLE caderno_campo_manejo (
    id SERIAL PRIMARY KEY,
    parcela_id INT REFERENCES parcelas_areas(id),
    data_registro DATE NOT NULL,
    tipo_manejo VARCHAR(50) NOT NULL, -- 'PLANTIO', 'ADUBACAO', 'TRATAMENTO_FITOSSANITARIO'
    insumo_id INT REFERENCES insumos_permitidos(id), -- Obriga uso apenas de permitidos
    quantidade_aplicada NUMERIC(10,2),
    observacoes TEXT
);

CREATE TABLE colheitas_coletas (
    id SERIAL PRIMARY KEY,
    parcela_id INT REFERENCES parcelas_areas(id),
    produto_primario VARCHAR(100) NOT NULL, -- ex: 'Morango', 'Leite'
    lote_primario VARCHAR(50) UNIQUE NOT NULL, -- ex: LOTE-MOR-20260804
    quantidade_colhida NUMERIC(10,2) NOT NULL,
    unidade_medida VARCHAR(10) NOT NULL, -- 'KG', 'L'
    data_colheita TIMESTAMP NOT NULL
);

-- =========================================================================
-- MÓDULO 3: AGROINDÚSTRIA E PROCESSAMENTO
-- =========================================================================

CREATE TABLE receitas_agroindustria (
    id SERIAL PRIMARY KEY,
    nome_produto_final VARCHAR(150) NOT NULL, -- ex: 'Geleia de Morango Orgânica'
    percentual_organico_calculado NUMERIC(5,2) NOT NULL, -- Calculado via sistema
    selo_autorizado VARCHAR(50) NOT NULL -- 'PRODUTO_ORGANICO_BRASIL', 'INGREDIENTES_ORGANICOS'
);

CREATE TABLE ingredientes_receita (
    id SERIAL PRIMARY KEY,
    receita_id INT REFERENCES receitas_agroindustria(id),
    nome_ingrediente VARCHAR(100) NOT NULL,
    eh_organico BOOLEAN DEFAULT TRUE,
    proporcao_porcentagem NUMERIC(5,2) NOT NULL
);

CREATE TABLE controle_higienizacao (
    id SERIAL PRIMARY KEY,
    data_hora_inicio TIMESTAMP NOT NULL,
    data_hora_fim TIMESTAMP NOT NULL,
    equipamento_linha VARCHAR(100) NOT NULL,
    sanitizante_utilizado VARCHAR(100) NOT NULL, -- Deve constar na Portaria 52
    responsavel_nome VARCHAR(100) NOT NULL
);

CREATE TABLE ordens_producao_agroindústria (
    id SERIAL PRIMARY KEY,
    receita_id INT REFERENCES receitas_agroindustria(id),
    higienizacao_previa_id INT REFERENCES controle_higienizacao(id), -- Garante segregação temporal
    lote_processado_final VARCHAR(50) UNIQUE NOT NULL,
    data_processamento TIMESTAMP NOT NULL,
    quantidade_final_obtida NUMERIC(10,2) NOT NULL,
    status_auditoria VARCHAR(30) DEFAULT 'APROVADO' -- 'APROVADO', 'ALERTA_FRAUDE'
);

-- Tabela de junção para Rastreabilidade Total e Balanço de Massas
CREATE TABLE rastreabilidade_insumos_processo (
    id SERIAL PRIMARY KEY,
    ordem_producao_id INT REFERENCES ordens_producao_agroindústria(id),
    colheita_primaria_id INT REFERENCES colheitas_coletas(id), -- Origem direta do campo
    quantidade_utilizada NUMERIC(10,2) NOT NULL
);

Para dar continuidade ao PRD, estruturei o banco de dados utilizando conceitos de rastreabilidade total (da semente ao frasco de geleia) e, logo em seguida, desenhei a arquitetura de fluxos de telas focado na usabilidade de produtores familiares (priorizando simplicidade e funcionamento offline).
------------------------------
## 1. Esquema do Banco de Dados (PostgreSQL / Relacional)
Este modelo mapeia a produção primária, a transformação agroindustrial, a limpeza de maquinários e o balanço de massas exigido pelo MAPA.

-- =========================================================================-- MÓDULO 1: CADASTRO E CONTROLE REGULATÓRIO-- =========================================================================
CREATE TYPE tipo_certificacao AS ENUM ('OCS', 'SPG_OPAC', 'AUDITORIA');CREATE TYPE escopo_producao AS ENUM ('VEGETAL', 'ANIMAL', 'PROCESSADO', 'MISTO');
CREATE TABLE unidades_producao (
    id SERIAL PRIMARY KEY,
    nome_propriedade VARCHAR(150) NOT NULL,
    nome_produtor VARCHAR(150) NOT NULL,
    cpf_cnpj VARCHAR(14) UNIQUE NOT NULL,
    tipo_controle tipo_certificacao NOT NULL,
    nome_oac_ocs VARCHAR(100), -- Nome do Organismo de Avaliação da Conformidade
    numero_registro_mapa VARCHAR(50),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    excluido BOOLEAN DEFAULT FALSE -- Atende à retenção regulatória de 3 anos (Soft Delete)
);
CREATE TABLE parcelas_areas (
    id SERIAL PRIMARY KEY,
    unidade_producao_id INT REFERENCES unidades_producao(id),
    nome_parcela VARCHAR(100) NOT NULL,
    geometria_poligono TEXT, -- Coordenadas GIS da área produtiva
    zona_amortecimento_descricao TEXT NOT NULL, -- Barreiras contra vizinhos convencionais
    status_conversao VARCHAR(50) DEFAULT 'ORGANICO', -- 'EM_CONVERSAO', 'ORGANICO'
    data_inicio_conversao DATE
);
-- =========================================================================-- MÓDULO 2: PRODUÇÃO PRIMÁRIA E MANEJO-- =========================================================================
CREATE TABLE insumos_permitidos (
    id SERIAL PRIMARY KEY,
    nome_comercial VARCHAR(150) NOT NULL,
    principio_ativo_substancia VARCHAR(150) NOT NULL,
    categoria VARCHAR(50) NOT NULL, -- 'FERTILIZANTE', 'DEFENSIVO_NATURAL', 'VACINA'
    referencia_portaria_52_2021 VARCHAR(100) NOT NULL -- Base legal no sistema
);
CREATE TABLE caderno_campo_manejo (
    id SERIAL PRIMARY KEY,
    parcela_id INT REFERENCES parcelas_areas(id),
    data_registro DATE NOT NULL,
    tipo_manejo VARCHAR(50) NOT NULL, -- 'PLANTIO', 'ADUBACAO', 'TRATAMENTO_FITOSSANITARIO'
    insumo_id INT REFERENCES insumos_permitidos(id), -- Obriga uso apenas de permitidos
    quantidade_aplicada NUMERIC(10,2),
    observacoes TEXT
);
CREATE TABLE colheitas_coletas (
    id SERIAL PRIMARY KEY,
    parcela_id INT REFERENCES parcelas_areas(id),
    produto_primario VARCHAR(100) NOT NULL, -- ex: 'Morango', 'Leite'
    lote_primario VARCHAR(50) UNIQUE NOT NULL, -- ex: LOTE-MOR-20260804
    quantidade_colhida NUMERIC(10,2) NOT NULL,
    unidade_medida VARCHAR(10) NOT NULL, -- 'KG', 'L'
    data_colheita TIMESTAMP NOT NULL
);
-- =========================================================================-- MÓDULO 3: AGROINDÚSTRIA E PROCESSAMENTO-- =========================================================================
CREATE TABLE receitas_agroindustria (
    id SERIAL PRIMARY KEY,
    nome_produto_final VARCHAR(150) NOT NULL, -- ex: 'Geleia de Morango Orgânica'
    percentual_organico_calculado NUMERIC(5,2) NOT NULL, -- Calculado via sistema
    selo_autorizado VARCHAR(50) NOT NULL -- 'PRODUTO_ORGANICO_BRASIL', 'INGREDIENTES_ORGANICOS'
);
CREATE TABLE ingredientes_receita (
    id SERIAL PRIMARY KEY,
    receita_id INT REFERENCES receitas_agroindustria(id),
    nome_ingrediente VARCHAR(100) NOT NULL,
    eh_organico BOOLEAN DEFAULT TRUE,
    proporcao_porcentagem NUMERIC(5,2) NOT NULL
);
CREATE TABLE controle_higienizacao (
    id SERIAL PRIMARY KEY,
    data_hora_inicio TIMESTAMP NOT NULL,
    data_hora_fim TIMESTAMP NOT NULL,
    equipamento_linha VARCHAR(100) NOT NULL,
    sanitizante_utilizado VARCHAR(100) NOT NULL, -- Deve constar na Portaria 52
    responsavel_nome VARCHAR(100) NOT NULL
);
CREATE TABLE ordens_producao_agroindústria (
    id SERIAL PRIMARY KEY,
    receita_id INT REFERENCES receitas_agroindustria(id),
    higienizacao_previa_id INT REFERENCES controle_higienizacao(id), -- Garante segregação temporal
    lote_processado_final VARCHAR(50) UNIQUE NOT NULL,
    data_processamento TIMESTAMP NOT NULL,
    quantidade_final_obtida NUMERIC(10,2) NOT NULL,
    status_auditoria VARCHAR(30) DEFAULT 'APROVADO' -- 'APROVADO', 'ALERTA_FRAUDE'
);
-- Tabela de junção para Rastreabilidade Total e Balanço de MassasCREATE TABLE rastreabilidade_insumos_processo (
    id SERIAL PRIMARY KEY,
    ordem_producao_id INT REFERENCES ordens_producao_agroindústria(id),
    colheita_primaria_id INT REFERENCES colheitas_coletas(id), -- Origem direta do campo
    quantidade_utilizada NUMERIC(10,2) NOT NULL
);

------------------------------
## 2. Fluxos de Telas (User Flows & Wireframes Conceituais)
O fluxo foi dividido entre o Aplicativo Mobile do Produtor (foco em agilidade no campo e modo offline) e o Painel Web de Gestão/Auditoria (foco em relatórios e prestação de contas para fiscais).
## Fluxo A: O Dia a Dia do Produtor no Campo (Mobile - Offline First)

[Tela de Login]
       │
       ▼
[Dashboard Inicial (Modo Offline Ativo)] 
  ├── Opção 1: Registrar Manejo/Insumo
  ├── Opção 2: Registrar Colheita/Coleta ───► (Gera Lote Primário Automaticamente)
  └── Opção 3: Iniciar Processamento na Agroindústria


* Tela A.1 - Registro de Insumo no Campo:
* Interface: Dropdown de seleção de Parcela ➔ Dropdown de Insumos (Filtrado automaticamente apenas com os itens permitidos pela Portaria 52/2021) ➔ Campo numérico de quantidade ➔ Botão "Salvar Offline".
* Tela A.2 - Registro de Colheita:
* Interface: Seleção da cultura ➔ Peso colhido ➔ Botão "Concluir Lote". O app gera um código provisório (ex: TMP-2026-001) e armazena localmente.

------------------------------
## Fluxo B: O Processamento na Agroindústria (Mobile/Tablet na Fábrica)

[Menu Agroindústria] 
       │
       ▼
[Passo 1: Check-in de Higienização] ───► Exige marcar checklist de limpeza e sanitizante usado.
       │
       ▼
[Passo 2: Nova Ordem de Produção (OP)] ───► Seleciona a receita (ex: Geleia de Morango).
       │
       ▼
[Passo 3: Vinculação de Matéria-Prima] ───► Câmera abre para bipar QR Code do lote do campo
       │                                     OU selecionar da lista de colheitas recentes.
       ▼
[Passo 4: Fechamento de Lote] ───► Sistema calcula rendimento, valida percentual orgânico 
                                    e gera o QR Code final de rotulagem.

------------------------------
## Fluxo C: O Painel do Auditor / OAC (Web Desktop)
Esta tela é utilizada pelo técnico do SPG, auditor certificador ou fiscal do MAPA para validar a conformidade da propriedade sem precisar revirar papéis.

[Painel de Auditoria Geral]
       │
       ▼
[Buscar Produtor por CPF/CNPJ] 
       │
       ▼
[Perfil de Conformidade da Unidade]
  ├── Aba 1: Histórico de Insumos Aplicados (Validação da Portaria 52)
  ├── Aba 2: Livro de Higienização de Máquinas (Verificação de contaminação cruzada)
  └── Aba 3: Tela de Balanço de Massas (Gráfico Comparativo)


* Tela C.3 - Detalhe do Balanço de Massas:
* Interface Visual: Um gráfico de barras simples comparando a Entrada de Matéria-Prima vs. Saída de Produto Processado.
   * Regra de Negócio Visual: Se o produtor deu entrada em 100kg de morango orgânico em seu diário de campo, mas a agroindústria gerou o equivalente a 300kg de geleia pura (sem adição permitida de outros ingredientes), a barra de saída fica vermelha piscante com o alerta: [ALERTA DE DESVIO: Volume final incompatível com a capacidade produtiva da matéria-prima orgânica originária].

------------------------------
Para garantir que o time de desenvolvimento implemente a solução em total conformidade regulatória e com foco na usabilidade do produtor familiar, os critérios de aceitação foram estruturados no formato padrão Gherkin (Dado/Quando/Então), divididos pelas principais histórias de usuário (User Stories).
------------------------------
## Módulo 1: Cadastro e Controle de Insumos (Conformidade Portaria 52/2021)## Histórico de Usuário 1: Validação de Insumo no Diário de Campo

Como Produtor Familiar,
Quero registrar os insumos aplicados no meu caderno de campo,
Para que eu possa comprovar ao auditor que não utilizei substâncias proibidas na produção orgânica.


* Critério de Aceitação 1 (Bloqueio de Insumos Proibidos):
* Dado que o produtor está na tela de registro de manejo fitossanitário ou adubação,
   * Quando ele tentar selecionar ou digitar um insumo que não conste na tabela insumos_permitidos (baseada na Portaria MAPA nº 52/2021),
   * Então o sistema deve exibir uma mensagem de erro impeditiva: "Ação bloqueada. Este insumo não é autorizado para uso na produção orgânica de acordo com a legislação vigente." e não permitir o salvamento do registro.
* Critério de Aceitação 2 (Alerta de Período de Carência / Conversão):
* Dado que uma parcela de terra está cadastrada com o status EM_CONVERSAO,
   * Quando o produtor registrar uma colheita vinculada a essa parcela,
   * Então o sistema deve marcar automaticamente o lote gerado com a tag PRODUTO EM CONVERSÃO ORGÂNICA, impedindo que ele seja associado ao selo SisOrg definitivo até que o prazo regulatório configurado em data_inicio_conversao seja atingido.

------------------------------
## Módulo 2: Segregação e Higienização na Agroindústria (Decreto nº 6.323/2007)## Histórico de Usuário 2: Check-in de Higienização Pré-Processamento

Como Operador da Agroindústria Familiar,
Quero ser obrigado a registrar a higienização do maquinário antes de processar um lote orgânico,
Para evitar a contaminação cruzada caso a linha também processe alimentos convencionais.


* Critério de Aceitação 1 (Trava de Segurança Temporal):
* Dado que o operador deseja abrir uma nova Ordem de Produção (OP) de um produto orgânico,
   * Quando ele clicar em "Iniciar Processamento",
   * Então o sistema deve verificar se existe um registro na tabela controle_higienizacao realizado nas últimas 24 horas para aquela linha de equipamentos. Se não houver, o sistema deve bloquear a abertura da OP e exibir o botão: "Registrar Higienização Obrigatória".
* Critério de Aceitação 2 (Validação de Sanitizantes):
* Dado que o operador está preenchendo o formulário de higienização,
   * Quando ele informar o sanitizante utilizado,
   * Então o sistema só deve aceitar produtos listados como permitidos para pós-colheita e processamento industrial orgânico (ex: base de ácido peracético, álcool 70%), emitindo um alerta caso um produto químico proibido seja digitado.

------------------------------
## Módulo 3: Rotulagem e Percentual Orgânico (Instrução Normativa nº 50/2009)## Histórico de Usuário 3: Cálculo Automático de Categoria de Rótulo

Como Gestor do Sistema,
Quero que a plataforma calcule automaticamente o percentual de ingredientes orgânicos da receita,
Para atribuir o direito de uso correto do selo "Brasil Orgânico" no rótulo final.


* Critério de Aceitação 1 (Classificação de Selo ≥ 95%):
* Dado uma receita onde a soma da proporção dos ingredientes marcados como eh_organico = TRUE seja de 96%,
   * Quando a receita for salva,
   * Então o campo selo_autorizado deve receber o valor PRODUTO_ORGANICO_BRASIL e liberar o download da imagem do selo oficial para a etiqueta.
* Critério de Aceitação 2 (Classificação de Ingredientes 70% a 95%):
* Dado uma receita onde os ingredientes orgânicos somem 85% (devido ao uso de especiarias ou conservantes convencionais permitidos em caráter de exceção),
   * Quando a receita for salva,
   * Então o sistema deve preencher o campo selo_autorizado como INGREDIENTES_ORGANICOS, bloquear a exibição do selo SisOrg e forçar o texto padrão do rótulo para: "Este produto contém ingredientes orgânicos".
* Critério de Aceitação 3 (Bloqueio Total < 70%):
* Dado uma receita com menos de 70% de ingredientes orgânicos,
   * Quando o usuário tentar salvar,
   * Então o sistema deve emitir um aviso: "Este produto não atinge o mínimo regulatório para menção ao termo Orgânico" e classificar o produto como convencional para fins de rotulagem.

------------------------------
## Módulo 4: Rastreabilidade Avançada e Auditoria (Lei nº 10.831/2003)## Histórico de Usuário 4: Alerta Automático de Quebra no Balanço de Massas

Como Auditor ou Técnico do SPG/OPAC,
Quero que o sistema cruze os volumes de entrada e saída de matéria-prima,
Para detectar fraudes ou misturas indevidas de produtos convencionais de forma automatizada.


* Critério de Aceitação 1 (Gatilho de Alerta de Fraude):
* Dado que o produtor registrou uma colheita de 100 kg de morango orgânico (quantidade_colhida na tabela colheitas_coletas),
   * Quando ele encerrar uma Ordem de Produção de Geleia de Morango Pura informando uma saída de 250 kg de produto final utilizando apenas aquele lote de morango como ingrediente principal,
   * Então o sistema deve alterar o campo status_auditoria da OP para ALERTA_FRAUDE, pintar a linha correspondente de vermelho no painel web do auditor e gerar uma notificação interna para o comitê de avaliação da conformidade.

------------------------------
## Requisitos Não Funcionais (Regras de Sistema)## Histórico de Usuário 5: Retenção Regulatória Imutável de Dados

* Critério de Aceitação 1 (Impossibilidade de Hard Delete):
* Dado que um fiscal do MAPA está auditando a propriedade,
   * Quando qualquer usuário (incluindo o administrador) tentar clicar em "Excluir" em uma nota fiscal, diário de campo ou ordem de produção com menos de 3 anos de criação,
   * Então o sistema deve realizar estritamente um Soft Delete (mudando a flag excluido para TRUE), mantendo o dado oculto na interface comum, mas totalmente recuperável e visível no relatório de auditoria fiscal obrigatória para cumprir o prazo legal de guarda de dados.