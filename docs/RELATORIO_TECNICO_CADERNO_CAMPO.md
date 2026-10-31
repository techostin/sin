# Relatório Técnico — MÓDULO CADERNO DE CAMPO (SINPORG)

> Engenharia reversa do módulo `modulos/caderno_campo/` (65 arquivos), classes de domínio, schema SQL,
> PWA offline e endpoints públicos. Todas as afirmações são ancoradas em linha de código/DDL.
> Fontes de schema: `sql/_backup/sisagro_pos_fase0_20260911.sql` (dump de referência, 2026-09-11).
> Roteador: `index.php` (rewrite de `.htaccess` → `index.php?url=$1`).

---

## 0. Sumário executivo (10 achados de maior impacto)

| # | Achado | Evidência |
|---|--------|-----------|
| 1 | `propriedades/historico_conversao.php` **não tem rota** em `index.php` — a tela existe, é linkada (`propriedades/listar.php:132`) e sempre dá 404 | grep `historico_conversao` = só um TODO em markdown |
| 2 | `modulos/caderno_campo/relatorios.php` (713 linhas, dashboard com Chart.js) é **código morto**: nenhuma rota o inclui | `index.php:32-35` manda `/relatorios` para o `relatorios.php` da **raiz**; `/caderno-campo/relatorios` vai para `relatorios_tabs.php` (`index.php:503-506`). A rota da linha 495-498 é inalcançável |
| 3 | `insumos/editar.php` **destrói dados**: o form não tem `data_validade`, `aprovado_organico`, `tipo_insumo`, `insumo_aprovado_id`, `restricoes` (0 ocorrências de `data_validade`/`tipo_insumo` no arquivo) e `Insumo::update()` grava `tipo_insumo = 'Não Permitido'` e `aprovado_organico = 0` (`Insumo.php:193-196`) |
| 4 | `InsumoValidador` usa **LIKE %nome%** com `LIMIT 1` sem `ORDER BY` (`InsumoValidador.php:17-22`) → nome curto (ex.: `"a"`) casa com qualquer registro aprovado e libera a conformidade |
| 5 | `ocorrencias/listar.php` chama `/api/ocorrencias.php` por **jQuery \$.ajax PATCH** (linhas 311-328 e 343-357); o wrapper global de CSRF só intercepta `window.fetch` (`includes/header.php:110-131`) → `api/ocorrencias.php:27-34` responde **403** e os modais "Informar Correções"/"Alterar Status" nunca funcionam |
| 6 | Enum `insumos.tipo_insumo` está **corrompido (mojibake)** no banco: `'Org├ónico','Em Convers├úo','Convencional','N├úo Permitido'` (`dump:1763`); o código grava `'Orgánico'` (com **á**, `insumos/cadastrar.php:113`) → valor inválido virou `''` nos dados reais (`dump:1788`) |
| 7 | `categorias_atividades` tem **UNIQUE KEY duplicada em `associado_id`** (`dump:886-887`) → no máximo **1 categoria por associado**; a tela de cadastro não grava `associado_id` (`categorias_atividades/cadastrar.php:20`) |
| 8 | Várias telas **não validam CSRF no POST**: `insumos/cadastrar.php`, `insumos/editar.php`, `produtos/cadastrar|editar`, `lavouras/cadastrar|editar`, `lotes/cadastrar|editar`, `ocorrencias/editar.php`, `propriedades/cadastrar|editar`, `categorias/cadastrar|editar`, `historico_conversao.php` |
| 9 | Exclusões **físicas** (não soft-delete) em `lavouras/excluir.php:26`, `produtos/excluir.php:38`, `categorias_atividades/excluir.php:25`, `historico_conversao.php:27`; `produtos` é referenciado por `lotes_producao.produto_id` sem FK → lotes órfãos |
| 10 | `dashboard_agricultor.php:23-31` faz fallback `associado_id = SELECT id FROM associados LIMIT 1` quando o vínculo não resolve → usuário sem vínculo passa a ver dados do **primeiro associado da base** |

---

## 1. Roteamento e autenticação

### 1.1 Mecânica
- `.htaccess:24` → `RewriteRule ^(.*)$ index.php?url=$1`; `.htaccess:47-65` bloqueia `.sql/.bak/.md` e prefixos `check_*/run_*/test_*/debug_*/_tmp_*` (isso neutraliza o lixo de dev do módulo: `lotes_producao/teste_qr.php`, `teste_qrcodejs.php`, `debug_qr.php`, `ocorrencias/cadastrar.php.bak`).
- `index.php:2-3` só carrega `AtividadeDiaria`; as demais classes são carregadas pelas páginas (por isso `lavouras/editar.php` etc. não precisam do roteador).
- Todo roteamento é *path → require arquivo*; **não há gate de permissão no roteador** — cada página implementa o seu.

### 1.2 Tabela de rotas do caderno de campo

| Rota | Arquivo | Observação (linha em `index.php`) |
|---|---|---|
| `/caderno-campo/dashboard` | `dashboard.php` | 161-164 |
| `/caderno-campo/dashboard-agricultor` | `dashboard_agricultor.php` | 167-170 |
| `/dashboard/agricultor` | `dashboard_agricultor.php` | dispatcher de perfil 868-902 |
| `/propriedades`, `/caderno-campo/propriedades` | `propriedades/listar.php` | 173-180 |
| `/propriedade/novo`, `/caderno-campo/propriedade/novo` | `propriedades/cadastrar.php` | 181-188 |
| `/propriedade/{id}/editar`, `/caderno-campo/propriedade/{id}/editar` | `propriedades/editar.php` | 189-198 (`$_GET['id']`) |
| `/caderno-campo/propriedade/{id}/imagens` | `propriedades/visualizar_imagens.php` | 199-203 |
| **`/propriedade/{id}/historico-conversao`** | — | **AUSENTE** (link em `propriedades/listar.php:132`) |
| `/lavouras`, `/caderno-campo/lavouras` | `lavouras/listar.php` | 206-213 |
| `/lavoura/novo`, `/caderno-campo/lavoura/novo` | `lavouras/cadastrar.php` | 214-221 |
| `/lavoura/{id\|uuid}/editar` | `lavouras/editar.php` | 222-246 (UUID resolvido em 223-230) |
| `/insumos`, `/caderno-campo/insumos` | `insumos/listar.php` | 249-256 |
| `/insumo/novo`, `/caderno-campo/insumo/novo` | `insumos/cadastrar.php` | 257-264 |
| `/insumo/{id\|uuid}/editar` | `insumos/editar.php` | 265-289 |
| `/caderno-campo/insumos/validacao` | `insumos/validacao.php` | 489-492 |
| `/produtos`, `/caderno-campo/produtos` | `produtos/listar.php` | 292-299 |
| `/produto/novo`, `/caderno-campo/produto/novo` | `produtos/cadastrar.php` | 300-307 |
| `/produto/{id\|uuid}/editar` | `produtos/editar.php` | 308-332 |
| `/lotes`, `/caderno-campo/lotes` | `lotes_producao/listar.php` | 335-342 |
| `/lote/novo`, `/caderno-campo/lote/novo` | `lotes_producao/cadastrar.php` | 343-350 |
| `/lote/{id\|uuid}` | `lotes_producao/rastrear.php` | 367-383 (UUID 368-372) |
| `/lote/{id\|uuid}/editar` | `lotes_producao/editar.php` | 377-378 / 396-397 |
| `/caderno-campo/ocorrencias` | `ocorrencias/listar.php` | 352-355 (**não existe rota "crua" `/ocorrencias`**) |
| `/caderno-campo/ocorrencia/novo` | `ocorrencias/cadastrar.php` | 356-359 |
| `/caderno-campo/ocorrencia/{id}/editar` | `ocorrencias/editar.php` | 360-364 (só ID numérico) |
| `/categorias-atividades`, `/caderno-campo/categorias-atividades` | `categorias_atividades/listar.php` | 405-412 |
| `/categoria-atividade/novo`, `/caderno-campo/.../novo` | `categorias_atividades/cadastrar.php` | 413-420 |
| `/categoria-atividade/{id}/editar` | `categorias_atividades/editar.php` | 421-430 |
| `/atividades-diarias`, `/caderno-campo/atividades-diarias` | `atividade_diaria/listar.php` | 432-440 |
| `/atividade-diaria/novo`, `/caderno-campo/atividade-diaria/novo` | `atividade_diaria/cadastrar.php` | 441-448 |
| `/caderno-campo/atividade-diaria/{id\|uuid}/{editar\|imagens}` | `editar.php` / `visualizar_imagens.php` | 461-480 |
| `/caderno-campo/sincronizacao` | `sincronizacao.php` | 483-486 |
| `/relatorios` | **raiz** `relatorios.php` | 32-35 |
| `/relatorios-admin` | `relatorios_admin.php` | 499-502 |
| `/caderno-campo/relatorios` | `relatorios_tabs.php` | 503-506 |
| `/caderno-campo/relatorios/exportar` | `relatorios/exportar_relatorio.php` | 508-511 |
| `/caderno-campo/relatorios/{geral,atividades,insumos,producao,propriedade,lotes}` | `relatorios/*.php` | 519-545 |
| **`relatorios.php` do módulo** | — | **sem rota (código morto)** |

### 1.3 Modelo de autorização
- Bootstrap: `includes/header.php:5-19` — instancia `User` e redireciona para `/login` se não logado (exceções `$PERMITIR_ACESSO_ANONIMO`, `$PERMITIR_SESSAO_PORTAL`).
- Níveis: `config/config.php:23-25` → `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`, `NIVEL_USUARIO=3`.
- `User::hasPermission()` (`User.php:114-141`): rejeita nível não numérico (mitigação de escalonamento), Admin (1) passa sempre, e a regra é `nivel <= required_level`.
- `User::isGestorOuAdmin()` (`User.php:147-149`) = `hasPermission(NIVEL_GESTOR)`.
- `User::getAssociadoId()` (`User.php:259-272`) e `getAssociadoIdResolvido()` (`User.php:299-329`) — o segundo cobre sessão do Portal (`$_SESSION['associado_id']`) e a tabela `usuarios.associado_id`.
- `ApiAuth` (`classes/ApiAuth.php:133-183`): guarda para endpoints — `exigirLogin`, `exigirStaff`, `exigirNivel`, `exigirProprioAssociado` (403 JSON). Usado apenas em `categorias_atividades/excluir.php:23`.
- CSRF: `classes/CSRFProtection.php:24-38` (`hash_equals` do token de sessão, campo `csrf_token` — `config/config.php:47`); `tokenFromRequest()` (77-109) aceita header `X-CSRF-Token`, `$_POST`, `$_GET` ou JSON. `includes/header.php:102-131` expõe `window.CSRF_TOKEN` e **injeta o header em todo `window.fetch` de escrita** (não em XHR/jQuery).
- Auditoria: `classes/AuditLog.php:13-27` grava em `audit_log` (`user_id, acao, tabela, registro_id, descricao, ip`; DDL `dump:750-762`).

---

## 2. Modelo de dados

### 2.1 `atividades_diarias` (dump:706-730) — núcleo
| Campo | Tipo | Uso no código |
|---|---|---|
| `id` | int AI PK | |
| `uuid` | char(36) UNIQUE `idx_atividades_uuid` | gerado em `atividade_diaria/cadastrar.php:133-139`; backfill em `editar.php:41-51`; usado nas URLs |
| `nome` | varchar(255) NOT NULL | obrigatório no form (`cadastrar.php:175`) |
| `descricao` | text | |
| `data_atividade` | datetime | gravado como `Y-m-d` (input date) |
| `categotia_id` | int | **legado/typo — 0 referências no código** |
| `hora_inicio` / `hora_fim` | time | `hora_fim` = NULL quando status `Em andamento` (`cadastrar.php:110-112`; `editar.php:131-133`) |
| `lavoura_id`, `propriedade_id`, `associado_id` | int (sem FK) | |
| `status` | enum('Pendente','Em andamento','Concluído') | default `Pendente` |
| `categoria_id` | int | join com `categorias_atividades` |
| `tipo_atividade` | enum('adubação','capina','irrigação','encanteramento','aplicação de bio insumo','colheita','plantio','poda') | espelhado no select (`cadastrar.php:182-189`, `editar.php:256`) |
| `insumo_utilisado` | varchar(100) | **TEXTO livre = nome do insumo, não FK** (base da rastreabilidade) |
| `imagem_diaria` | varchar(255) | `uploads/atividades/...` |
| `observações` | text | coluna com acento, usada em SQL (`editar.php:202`) e chave PHP (`$atividade['observações']`) |
| `criado_em` / `atualizado_em` | timestamp | |
| `lote_id` | int | **nunca escrito** pelos forms; usado em LEFT JOIN nos relatórios (`relatorios/atividades.php:104`, `atividades_excel.php:96`) → sempre NULL |
| `excluido` | tinyint(1) | soft delete (`excluir.php:27`) |

**Classes:** `AtividadeDiaria.php` — `getAll()` (16-31), `getByUuid()` (41-51), `getById()` (58-80, com 4 LEFT JOINs), `create()` (87-126, gera UUID e usa `Sanitizer::sanitizeArray`). `update()`/`delete()` **não existem na classe** (`AtividadeDiaria.php:128`); as telas fazem SQL direto.

### 2.2 `categorias_atividades` (dump:878-888)
`id`, `nome` varchar(100) NOT NULL, `descricao` text, `associado_id` int, `cor` varchar(7) default `#000000`, `created_at`.
⚠ `UNIQUE KEY associado_id` **e** `UNIQUE KEY associado_id_2` (886-887) — duplicata; limita a 1 linha por `associado_id`.
`CategoriaAtividade::delete()` = `DELETE` físico (99-101) — divergente do soft delete das demais entidades. `getAll()` (16-25) não filtra por dono.

### 2.3 `insumos` (dump:1753-1779)
`id`, `uuid` UNIQUE, `associado_id`, `nome`, `descricao`, `fornecedor_id`, `estoq_anterior` **int**, `quant_recebida` **int**, `quantidade_estoque` **int**, `tipo_insumo` enum(mojibake, default `Org├ónico`), `aprovado_organico` tinyint(1), `certificacao_insumo`, `insumo_aprovado_id` (**FK** → `insumos_organicos_aprovados.id` ON DELETE SET NULL, 1777-1778), `restricoes` text, `composicao`, `fabricante`, `lote`, `data_validade` date, `excluido`.
Índices: `idx_tipo_insumo`, `idx_aprovado`.
⚠ O código grava **float** em colunas INT (`insumos/cadastrar.php:96-97`; `Insumo::create` = `bindParam` cru, `Insumo.php:128-130`) → truncamento. `cadastrar.php:100` calcula `quantidade_estoque = estoq_anterior + quant_recebida` (readonly no form).
`Insumo::delete()` é soft (`Insumo.php:211-220`).

### 2.4 `insumos_organicos_aprovados` (dump:1799-1822) — lista oficial
`id`, `nome` varchar(200), `nome_comercial`, `fabricante`, `categoria` enum('Fertilizante','Defensivo','Adjuvante','Sementes','Mudas','Outros','Alimentação Animal','Suplemento Mineral','Aditivo Tecnológico','Sanidade Animal','Higienização','Apicultura','Aditivo Alimentar','Coadjuvante de Tecnologia'), `tipo`, `subcategoria`, `permitido` tinyint(1) default 1, `restricoes` text, `normativa` varchar(100) default `'Portaria 52/2021'`, `data_inclusao_normativa`, `composicao`, `principio_ativo`, `observacoes`, `fonte_referencia`, `created_at`, `updated_at`.
Índices: `idx_nome`, `idx_categoria`, `idx_permitido`, `idx_tipo`. `AUTO_INCREMENT=447` → ~250 registros carregados.
Seeds: `sql/seed_insumos_aprovados.sql`, `sql/seed_insumos_aprovados_animal.sql`, `sql/seed_insumos_aprovados_processamento.sql`. Migração estrutural: `sql/alter_insumos_organico.sql` (enum "correto": `'Orgânico','Em Conversão','Convencional','Não Permitido'`, linhas 13-30).

### 2.5 `lavouras` (dump:1842-1857)
`id`, `uuid` UNIQUE, `propriedade_id` NOT NULL (**FK** `lavouras_ibfk_1` → `propriedades.id` ON DELETE CASCADE), `nome`, `tipo_cultura`, `area` decimal(10,2), `data_plantio`, `status` varchar(20) default `planejada`, `data_colheita_prevista`, `observacoes`.
⚠ O form usa o campo `cultura` (`lavouras/cadastrar.php:72`) e a classe mapeia para `tipo_cultura` (`Lavoura.php:127`); o update mapeia `'cultura' => 'tipo_cultura'` (`Lavoura.php:188`). Não existem colunas `cultura`, `latitude`, `longitude` — embora a view legada `recicla/create_view_caderno_campo.sql:26,42-43` as referencie (a view real instalada é a do dump:2774+, que expõe `tipo_cultura`).
`Lavoura::create()` valida obrigatórios e **retorna string de erro** (113-118, 136-139) — contrato misto `true|string`, consumido em `lavouras/cadastrar.php:33-38`.
`Lavoura::update()` monta SET dinâmico (`Lavoura.php:182-209`) e **não atualiza `nome`** (fora do mapeamento 186-194), apesar de a tela não expor nome na edição.

### 2.6 `lotes_producao` (dump:1930-1946)
`id`, `uuid` UNIQUE `idx_lotes_uuid`, `nome_lote`, `produto_id` NOT NULL (**sem FK**), `associado_id`, `data_producao`, `quantidade` decimal(10,2), `unidade_medida`, `observacoes`, `criado_em`, `atividades_id` (**0 referências = morto**), `insumos_id` (**0 referências = morto**), `excluido`.

### 2.7 Tabelas de junção mortas
- `lote_atividade` (dump:1877-1883) — **0 ocorrências** em todo o código PHP.
- `lote_insumo` (dump:1902-1911) — 6 ocorrências, todas **fora** do caderno de campo (`OrdemProducao.php`, `agroindustria/rastreabilidade.php`, `ReportConformidade.php`, `api/relatorios/movimentacoes.php`).

### 2.8 `ocorrencias_manejo` (dump:2172-2201)
`id`, `associado_id` (**FK** CASCADE), `propriedade_id` (**FK** CASCADE), `lavoura_id` (**FK** SET NULL), `tipo` enum('Praga','Doença','Fisiológica/Nutricional','Climatológica','Outro'), `nome_comum`, `data_deteccao`, `severidade` enum('Baixa','Média','Alta'), `status` enum('Aberta','Em Correção','Resolvida','Fechada'), `descricao`, `acao_manejo`, `insumo_utilizado_id` (**FK** SET NULL → `insumos`), `foto_url`, `correcoes_realizadas`, `created_at`, `updated_at`, `excluido`. 6 índices + 4 FKs.
`Ocorrencia::create()` (`Ocorrencia.php:89-124`) **não grava `status` nem `correcoes_realizadas`** (defaults do banco). `update()` (129-174) filtra por `associado_id`. `delete($id,$associado_id)` soft (179-189).

### 2.9 `produtos` (dump:2490-2512)
`id`, `uuid` UNIQUE, `nome`, `descricao`, `tipo`, `unidade_medida`, `preco_unitario` decimal(10,2), `quantidade_produzida`, `data_colheita`, `lavoura_id`, `setor_id`, `associado_id`, `unidade_tipo` enum('propriedade','agroindustria','animal'), `unidade_id`, `observacoes`, `criado_em`. Índices `idx_produto_assoc`, `idx_produto_unidade`, `idx_produto_setor`.
`Produto::create()` (235-320) faz dupla conversão de preço: `Sanitizer::sanitizeFloat` na linha 256 e depois a heurística "centavos" nas 282-286 (dead code parcial, pois `$preco` é reatribuído da linha 283). `delete()` = **DELETE físico** (`Produto.php:411-420`).
`getProdutosDoAssociado()` (83-153) e `getUnidadesDoAssociado()` (162-211) implementam o modelo dono/membro via `propriedade_membros`, `agroindustria_membros`, `ambiente_criacao_membros`.

### 2.10 `propriedades` + `propriedade_membros`
`propriedades` (dump:2563-2589): `id`, `associado_id` NOT NULL (**FK** `propriedades_ibfk_1` → `associados`), `propriedade`, `area_total`, `endereco`, `cep`, `bairro`, `cidade` **varchar(100)**, `estado` char(2), `latitude`/`longitude` varchar(20), `observacoes`, `created_at`, `nome` varchar(20) (legado), `localizacao`, `tamanho`, `status`, `recursos_naturais`, `imagem_principal`, `imagem_secundaria`, `zona_amortecimento`. Índice `idx_propriedades_imagens`.
⚠ `cidade` é varchar mas `Propriedade::getAll/getById` fazem `LEFT JOIN cidades c ON p.cidade = c.id` (`Propriedade.php:29, 83`) — cast implícito; e o cadastro grava **ID** de cidade (select, `cadastrar.php:241`) enquanto a edição usa **texto livre** (`editar.php:137`). `estado` char(2) vs `estados` (id AI + uf UNIQUE, dump:1413-1422) → o join por `e.uf` funciona.
`propriedade_membros` (dump:2532-2543): `id`, `propriedade_id` FK CASCADE, `associado_id`, `excluido`, `criado_por`, `criado_em`, UNIQUE(`propriedade_id`,`associado_id`). **Usado só por `Produto`/`relatorios.php`** — as telas de propriedade ignoram membros (só `p.associado_id`).

### 2.11 `historico_conversao` (dump:1688-1701)
`id`, `propriedade_id` (KEY `idx_hist_conv_prop`, **sem FK**), `associado_id`, `tipo_uso_solo` varchar(100) NOT NULL, `data_inicio` date NOT NULL, `data_fim` date, `status` enum('EM_CONVERSAO','ORGANICO','CONVENCIONAL'), `observacoes`, `criado_por`, `criado_em`.
`HistoricoConversao::mesDeConversao()` (`HistoricoConversao.php:31-38`): 24 meses se o texto contiver `peren`/`pasto`/`pomar`, senão 12. `calcularConversao()` (43-68) devolve `data_conclusao`, `restante_meses`, `concluido`, `total_meses`. `delete()` = DELETE físico (89-93).

### 2.12 Diagrama de relacionamentos (deduzido)
```
associados 1─N propriedades 1─N lavouras 1─N produtos 1─N lotes_producao
                    │                              │
                    └─N atividades_diarias ────────┘ (via produto.lavoura_id = atividade.lavoura_id)
associados 1─N insumos N─1 fornecedores
insumos N─1 insumos_organicos_aprovados (fk_insumo_aprovado)
associados 1─N ocorrencias_manejo N─1 propriedades, N─1 lavouras, N─1 insumos
categorias_atividades 1─N atividades_diarias (categoria_id)
propriedade_membros N─N propriedades/associados (ignorado pelas telas do caderno)
historico_conversao N─1 propriedades (sem FK)
```
Observação: **`atividades_diarias` não declara nenhuma FK** — integridade referencial só existe na aplicação.

---

## 3. Telas (rota, propósito, campos, validações, regras, tabelas, permissões, ações)

### 3.1 `dashboard.php` — Painel do Caderno de Campo
- **Rota:** `/caderno-campo/dashboard` (`index.php:161`). **Permissão:** qualquer usuário logado (`dashboard.php:7-11`).
- **Dados:** `SELECT ad.*, p.propriedade, l.nome AS lavoura_nome ... WHERE ad.associado_id = ? AND ad.excluido = 0` (`19-23`), com `$user_id = $_SESSION['user_id']` (`13`). ⚠ Para usuário da tabela `usuarios`, `user_id` **não é** `associado_id` → lista vazia (contramedida em outras telas via `getAssociadoId()`).
- **Ações:** cards de navegação (propriedades/lavouras/insumos/produtos/lotes/categorias/atividades) e DataTable `#atividadesTable` (`121-152`) com Editar (`143`) e Excluir por POST + CSRF (`144-147`) para `atividade_diaria/excluir.php`.
- Inclui `includes/widget_eventos.php` (`160`) e `CSRFProtection` dentro do loop (`144`) — `require_once` repetido (idempotente, mas indicador de código improvisado).

### 3.2 `dashboard_agricultor.php` — Painel do Agricultor (PWA-first)
- **Rota:** `/caderno-campo/dashboard-agricultor` e `/dashboard/agricultor`. **Permissão:** exige `user_nivel === NIVEL_USUARIO` (`14-18`), senão "Acesso negado".
- ⚠ `20-31`: se `getAssociadoId()` falhar, cai em `SELECT id FROM associados LIMIT 1` (ou literal `1`) → **exposição de dados do primeiro produtor**.
- **Métricas (51-104):** total de atividades, insumos, ocorrências; último registro de `certificacao_participativa` (`63-69`); próxima/última `visitas_tecnicas` (`71-77`); `nao_conformidades` abertas (`79-81`); atividades de hoje (CURDATE) e da semana (`YEARWEEK`, `83-89`); insumos com `quantidade_estoque < 10` (`91-93`); `animais` (`95-97`); últimas 5 atividades (`99-104`).
- **Score de conformidade (106-118):** base 100 − 30 (certificação ≠ Ativa) − min(nc×8, 25) − 20 (atividades/semana < 2) − min(insumos baixos×5, 15); `max(0, …)`. Peso `ocorrencias`=10 declarado (112) e **nunca usado**.
- **UX:** banner offline (`189-192`, `622-627`), lista de pendências (320-356), eventos futuros com `evento_presenca` (369-408), tabela de atividades (419-460), card de certificação, FAB mobile (576-595) e atalhos.

### 3.3 Propriedades
| Tela | Arquivo | Propósito / regras |
|---|---|---|
| Listar | `propriedades/listar.php` | Nível 3 vê só `getByAssociadoId` (17-31); Admin/Gestor vê tudo (32-34). DataTable; botões Editar (`118-123`), Imagens (`124-130`), **Histórico de Conversão → rota inexistente** (`132`), Modal "visualizar" via `/api/get_propriedade_detalhes.php` (`243`) |
| Cadastrar | `propriedades/cadastrar.php` | Auth `16-19`; regra de dono `46-67` (bloqueia vínculo a terceiro e devolve **modal 403** em 166-175); uploads principal/secundária com `ImageUploader` (108-127) e rollback das imagens em falha (150-155); `cidade` via `/api/get_cidades.php?todos=1` (340-357) com auto-seleção de UF (360-376); valida **tamanho ≤ 5 MB no JS** (412-421). **Sem CSRF** |
| Editar | `propriedades/editar.php` | IDOR só para `NIVEL_USUARIO` (29-35); troca de imagem removendo a antiga (51-78); `cidade` **texto livre** (137); UF em select hardcoded (141-170); `zona_amortecimento` (244-245). **Sem CSRF** |
| Imagens | `propriedades/visualizar_imagens.php` | Mostra imagens, coordenadas (80-90), observações; aviso "inicializar mapa" não implementado (219-225) |
| Histórico conversão | `propriedades/historico_conversao.php` | **ÓRFÃ**. Regras: exige `tipo_uso_solo` + `data_inicio` (46-48); `criado_por = $_SESSION['user_id']` (43); exclusão via **GET `?excluir=`** sem CSRF (26-31); tabela com `calcularConversao()` (160-172) |

### 3.4 Lavouras
- `lavouras/listar.php`: Admin vê tudo (24-25), Gestor também (26-27), Nível 3 filtra `p.associado_id` (28-36); DataTable; Editar por **UUID** (`97`); Excluir **só para NIVEL_ADMIN** (`103-111`) → `lavouras/excluir.php` (POST+CSRF, `19-21`, **DELETE físico** `26`).
- `lavouras/cadastrar.php`: selects escopados (19-25); campos `nome*, propriedade_id*, cultura*, area*, data_plantio*` (54-81); **POST sem CSRF** (27-38); validação delegada a `Lavoura::create`.
- `lavouras/editar.php`: IDOR por dono da propriedade (30-39); campos `propriedade_id, cultura, area, data_plantio, data_colheita_prevista, status` (6 opções: planejada/plantada/em_desenvolvimento/colhida/suspensa/cancelada, `119-127`), `observacoes`; **POST sem CSRF**.

### 3.5 Insumos
- `insumos/listar.php`: Admin/Gestor `getAll()`, Nível 3 `getByAssociadoId` (17-23); colunas Estoque anterior/Recebida/Atual (`63-65`); Editar por UUID (`68-70`); Excluir (POST+CSRF) `71-75` → soft delete (`insumos/excluir.php:27`, exige **NIVEL_GESTOR**, `14-16`).
- `insumos/cadastrar.php` (553 linhas, a tela mais elaborada):
  - Carrega fornecedores com escopo por perfil via `Fornecedor::getVisiveisParaAssociado` (35-49).
  - Classifica a lista oficial em 3 linhas de produção (`52-56`): Vegetal/Animal/Processamento, indexando categoria→linha (`57-63`).
  - Carrega `insumos_organicos_aprovados WHERE permitido = 1` (`68`) e serializa para JS (`82`) alimentando um **modal de seleção** com filtros por linha/categoria/busca (406-544).
  - Campos: `nome*`, `associado_id*`, `descricao`, `fornecedor_id`, `data_validade`, `estoq_anterior`, `quant_recebida`, `quantidade_estoque` (readonly, soma em JS), `observacoes` (154-220).
  - **Regra RF06 (105-130):** `InsumoValidador::avaliar(nome)`; se não permitido → bloqueia; se permitido grava `aprovado_organico=1`, `tipo_insumo='Orgánico'` (**á**, 113), `insumo_aprovado_id`, `restricoes` (112-115).
  - Validação em tempo real no cliente via `validar_insumo.php` (310-400) + bloqueio no submit (369-400).
  - **Sem CSRF** no POST.
- `insumos/editar.php`: IDOR por `associado_id` (59-67); ⚠ **não revalida RF06** e o form não possui `data_validade/tipo_insumo/aprovado_organico/insumo_aprovado_id/restricoes/observacoes` → `Insumo::update()` sobrescreve com defaults (`Insumo.php:189-196`), zerando a conformidade e a validade. Também lê `$_POST['quantidade_estoque']`? Não: recalcula a soma (83-84).
- `insumos/validacao.php`: **restrita a Admin/Gestor** (15-19); busca paginada 50/página com filtro por nome/fornecedor (23-52); para **cada linha** executa um `SELECT ... LIKE ... LIMIT 1` na lista oficial (127-130, **N+1**); badge Permitido/Restrito/Não Verificado (132-143); link Editar por UUID (`160`).

### 3.6 Produtos
- `produtos/listar.php`: Admin/Gestor `getAll()`; Nível 3 `getProdutosDoAssociado` (16-21); Excluir POST+CSRF (`65-69`) → **DELETE físico** com checagem de acesso por lista de produtos acessíveis (`produtos/excluir.php:24-37`).
- `produtos/cadastrar.php`: `nome*`, `tipo`, `descricao`, `unidade_medida` (kg/unidade/caixa/litro/saca), `preco_unitario`, `quantidade_produzida`, `data_colheita`, `lavoura_id` **ou** `setor_id` (perfil agroindústria, `123-155`), `unidade_tipo` + `unidade_id` (159-176), `observacoes`; para perfil agroindústria quantidade/data ficam `disabled` (36-39) porque vêm da Ordem de Produção; JS filtra unidades/setores por tipo (202-245). **Sem CSRF**.
- `produtos/editar.php`: mesma estrutura; IDOR por `getProdutosDoAssociado` (30-42); `$qtdDesabilitado` também quando o produto é de agroindústria (55-57). **Sem CSRF**.

### 3.7 Lotes de Produção + rastreio
- `lotes_producao/listar.php`: Admin/Gestor `getAll()`; Nível 3 `getByAssociadoId` (17-22). Ações: Rastrear (`/lote/{uuid}`), Editar, **Imprimir QR** (`imprimirQRCode`, 229-290), Excluir POST+CSRF (soft, `excluir.php:29`).
- `cadastrar.php`: `nome_lote*`, `produto_id*` (só produtos acessíveis), `data_producao*`, `quantidade*`, `unidade_medida`, `observacoes` (64-91); `associado_id` forçado a `getAssociadoId()` (36-39). **Sem CSRF**.
- `editar.php`: aceita ID **ou** UUID (18-31); IDOR por `produtos → lavouras → propriedades.associado_id` (43-57) — ⚠ **quebra para lotes de agroindústria** (produto sem lavoura). **Sem CSRF**; redireciona por JS em 1,5 s após salvar (77).
- `rastrear.php`: rota `/lote/{id|uuid}`; exige login (11-14); anti-IDOR por `lotes_producao.associado_id` via `isGestorOuAdmin()/getAssociadoIdResolvido()` (31-37); monta produto→lavoura→propriedade (39-51); atividades e insumos via `LoteProducao::getAtividadesByLoteId`/`getInsumosByLoteId` (53-54). ⚠ usa `$propriedade['nome']` (`83`) — coluna legada — em vez de `propriedade`.

### 3.8 Ocorrências
- `ocorrencias/listar.php` (498 linhas): lista via PHP (`20-25`) e injeta JSON no DataTable (262-289). Colunas dinâmicas por perfil (182-256). **Gestor:** Editar, `alterarStatus` e Excluir (241-244). **Usuário:** `informarCorrecao` se `Aberta`/`Em Correção` (246-253). Modais: `#formCorrecao` (106-133) e `#formStatus` (136-173). Exclusão via POST+CSRF (`417-446`). ⚠ As duas chamadas AJAX (311-328 / 343-357) vão por **jQuery XHR** para `/api/ocorrencias.php`, que exige CSRF (`api/ocorrencias.php:27-34`) — o wrapper de `fetch` não cobre XHR → **403 sistemático**.
- `cadastrar.php`: `propriedade_id*`, `lavoura_id`, `tipo*` (5 opções, 129-136), `nome_comum*`, `data_deteccao*`, `severidade*`, `descricao`, `acao_manejo`, `insumo_utilizado_id`, `foto` (104-183); CSRF validado (52-55); `associado_id` **sempre** `getAssociadoId()` — inclusive Gestor (58-60); upload via `ImageUploader` para `uploads/ocorrencias/` (63-71).
- `editar.php`: IDOR por dono (38-46); CSRF token é **impresso** (116) mas **nunca validado**; não expõe `status` nem `correcoes_realizadas`; mantém a foto atual se não houver nova (74-84).
- `excluir.php`: POST + CSRF (20-22); permissão por dono ou gestor (30-37); `delete($id,$associado_id)` soft (39).

### 3.9 Categorias de Atividade
- `listar.php`: qualquer logado (7-11); DataTable; Editar (76-79); Excluir POST+CSRF (80-85).
- `cadastrar.php`: **só NIVEL_GESTOR** (6-10); `INSERT ... (nome, descricao, cor)` sem `associado_id` (20-21) → sempre categoria **global**; **sem CSRF**.
- `editar.php`: anti-IDOR por `associado_id` com `isGestorOuAdmin()/getAssociadoIdResolvido()` (28-37); **sem CSRF**.
- `excluir.php`: único ponto do módulo que usa `ApiAuth::exigirProprioAssociado` (23); **DELETE físico** (25).

### 3.10 Atividades Diárias (tela central)
- `listar.php`: filtro por perfil (`22-31`); SELECT com 5 LEFT JOINs (`33-55`); exportação para `/api/relatorios/relatorio_atividades_usuario.php?formato=pdf|excel` (`75-84`); badges de status/categoria; links por **UUID** (`155-167`); botão Excluir escondido de `user_tipo === 'usuario'` (`168-176`).
- `cadastrar.php`: selects escopados por perfil (28-71); campos `nome*`, `tipo_atividade*` (8 valores), `descricao`, `data_atividade*`, `hora_inicio`, `hora_fim`, `propriedade_id`, `lavoura_id`, `associado_id`, `categoria_id`, `insumo_utilisado` (lista de `insumos` por **nome**), `status*`, `observacoes`, `imagem_diaria` (169-302). Upload `ImageUploader` 1600×1200 → `uploads/atividades/` (97-108). **IDOR** revalidado no POST (114-129). **RF06 bloqueante** (141-151). UUID gerado (133-139). Auditoria (`AuditLog::registrar 'ATIVIDADE_CADASTRADA'`, 149/155). CSRF validado (79-81).
  - ⚠ O handler JS de submit (354-373) chama `form.submit()` programaticamente após validação assíncrona → **bypassa o handler global do `db_sync.js`** (o evento `submit` não é redisparado), exceto quando a validação já está em cache.
- `editar.php`: backfill de UUID (41-51); IDOR (54-61); transação com `beginTransaction/commit/rollBack` (153-229); remoção `remover_imagem` (160-167) e troca de arquivo (170-187); RF06 bloqueante via exceção (190-196); UPDATE de 15 campos + `atualizado_em=NOW()` (199-212); auditoria `ATIVIDADE_EDITADA` (217); observações lidas com fallback `observações`/`observacoes` (360).
- `excluir.php`: **exige NIVEL_GESTOR** (14-16) + POST + CSRF (20-22) + soft delete (27) + auditoria (29).
- `visualizar_imagens.php`: IDOR (26-32); ⚠ consulta `SELECT nome FROM propriedades` (`44`) — coluna legada — e não `propriedade`.

### 3.11 Sincronização (`sincronizacao.php`)
- **Rota:** `/caderno-campo/sincronizacao`. Verifica login (6-9). Mostra status de conexão, contador de pendentes, última sincronização (20-56) e tabela da fila `sync_queue` lida **diretamente do IndexedDB** (160-206), com remover (`removerRegistro`, 296-306) e limpar fila (280-294).
- `processarSincronizacao()` (209-278) delega para `window.syncEngine.syncQueuedData()` quando existe (218) e implementa um replay próprio com `URLSearchParams` (242-270), contando sucesso/erro e recarregando.

### 3.12 Validação avulsa (`validar_insumo.php`)
- Endpoint JSON (5-11), sem autenticação e sem validação de método (GET). Devolve `status`, `message` e `detalhes`/`restricoes` (13-34). É consumido por: atividade cadastrar/editar, insumos cadastrar/editar.

---

## 4. PWA / Offline

### 4.1 Registro e assets
- `includes/header.php:35` (manifest), `51-52` (`masks.js`, `db_sync.js`), `70-79` (registro de `/sw.js` no `load`), `100-131` (`window.CSRF_TOKEN` + wrapper de fetch).
- `manifest.json`: `start_url: ./index.php`, `display: standalone`, `orientation: portrait`, theme `#198754`, ícones 192/512 e 2 shortcuts (`./atividade-diaria/novo`, `./caderno-campo/ocorrencia/novo`).
- `sw.js`: `CACHE_VERSION = 'sinporg-v5'` (1). `install` pré-cacheia 7 assets estáticos (6-14) e `skipWaiting` (27); `activate` apaga versões antigas e `clients.claim` (32-42); `sync` com tag `sync-forms` apenas **notifica os clientes** via `postMessage({type:'TRIGGER_SYNC'})` (45-56); `fetch` ignora não-GET (64) e não-http (67), deixa passar API (`/\api/`, `/modulos/.*validar`, `\.php\?.*csrf` — linha 20, sempre Network, 70-73), Cache First para estáticos (76-79, 99-113) e Network First para navegação (82, 115-132); fallback final é uma página offline embutida (135-182) — que **retorna HTTP 200** em vez de 503.

### 4.2 `assets/js/db_sync.js` (320 linhas) — leitura integral
- Classe `OfflineSyncEngine` (2): `dbName='sinporg_offline_db'`, `dbVersion=1` (4-5). No construtor: `initDatabase()`, `initNetworkListeners()`, `setupFormsIntercept()` (7-9). Instanciada no fim do arquivo e exposta em `window.syncEngine` (319-320).
- **IndexedDB schema:** um único object store `sync_queue` com `keyPath:'id'`, `autoIncrement:true` (28-33). Registro: `{id, type, url, payload, timestamp}` (134-139).
- `initDatabase` (12-34): ao abrir com sucesso, se `navigator.onLine` chama `syncQueuedData()` (23-25).
- `initNetworkListeners` (36-58): `online` → badge + `syncQueuedData()`; `offline` → badge; status inicial no `DOMContentLoaded`; escuta `message` do SW para `TRIGGER_SYNC` (51-57).
- `updateOnlineStatus` (60-94): cria/atualiza o badge `#connection-status-badge` (fixo, top 15/right 70).
- **Formulários interceptados** (`setupFormsIntercept`, 96-113): `form.id === 'form-atividade' | 'form-insumo' | 'form-ocorrencia'` **ou** URL contendo `/insumos/cadastrar`, `/atividade-diaria/novo`, `/ocorrencia/novo`; e **somente** quando `!navigator.onLine` (108). Na prática:
  - Atividade: o form (`atividade_diaria/cadastrar.php:169`) **não tem id** → cai na URL `/caderno-campo/atividade-diaria/novo` ✔;
  - Insumo: id `form-insumo` (`insumos/cadastrar.php:150`) ✔ (a URL real é `/caderno-campo/insumo/novo` e **não** casa com `/insumos/cadastrar`, mas o id resolve);
  - Ocorrência: id `form-ocorrencia` + action `/caderno-campo/ocorrencia/novo` ✔ (`ocorrencias/cadastrar.php:104`).
  - **Nenhuma tela de edição é interceptada** (id diferente/ausente).
- `saveFormOffline(form)` (115-161): converte `FormData` em objeto plano (116-120); classifica `type` por URL (122-128) — `insumo` só se a URL contiver `/insumos`, o que **não acontece** na rota real (`/insumo/novo`) → a fila rotula insumos como `atividade`; `url = window.location.href` (129) é o **destino do replay**; grava no store (131-141); no sucesso notifica (144), registra Background Sync `'sync-forms'` quando disponível (146-150), dá `form.reset()` e restaura `#quantidade_estoque = '0'` (151-154).
- `syncQueuedData()` (163-237): lê tudo, e para cada registro escolhe `multipart/form-data` se houver `File/Blob` (181-196) ou `application/x-www-form-urlencoded` (197-211); faz `fetch(record.url, {method:'POST', …})`; **remove da fila quando `response.ok`** (215-219); conta processados/sucesso e atualiza badge/notifica (224-234). Chama-se `fetch` → recebe o header `X-CSRF-Token` do wrapper global.
- `updateSyncBadgeStatus` (239-258) e `showNotification` (260-295, toast Bootstrap com fallback de `alert`).
- `checkPendingCount(callback)` (297-315) — usado por `includes/header.php:57-66` para os badges `#sync-badge-count`/`#sync-badge-count-top`.

### 4.3 Resolução de conflitos — **não existe**
Não há versionamento, `updated_at` comparado, `etag`, `last-write-wins` explícito nem merge: o replay é um POST cego ao mesmo `url` do formulário. Consequências verificáveis:
1. **Perda silenciosa:** se a sessão expirou, o POST é redirecionado para `/login` (HTTP 200) → `response.ok` é verdadeiro → o registro é **apagado da fila sem ter sido gravado** (`db_sync.js:215-219`). Nada no código valida o corpo/JSON da resposta.
2. **Duplicidade possível:** não há chave de idempotência; reprocessar (`online` + `DOMContentLoaded` + `TRIGGER_SYNC`) pode enviar o mesmo registro duas vezes antes da remoção (o `delete` só ocorre no `then`).
3. Falha de rede no meio não é diferenciada de erro 4xx/5xx do servidor: só o `.catch` loga (221-223), e o registro permanece na fila (correto), porém sem backoff nem limite de tentativas.
4. O botão "Limpar fila" (`sincronizacao.php:280-294`) descarta tudo sem exportação.

---

## 5. Rastreabilidade de lotes

### 5.1 Cadeia completa
1. **Propriedade** (`propriedades`) → **Lavoura** (`lavouras.propriedade_id`, FK CASCADE) → **Produto** (`produtos.lavoura_id`) → **Lote** (`lotes_producao.produto_id`, sem FK) — `LoteProducao::create` (`LoteProducao.php:32-33`).
2. **Atividades:** `LoteProducao::getAtividadesByLoteId()` (`LoteProducao.php:136-152`) faz `lotes_producao → produtos → atividades_diarias ON p.lavoura_id = ad.lavoura_id`. Isto é, **o vínculo é por lavoura, não pelo lote** — todas as atividades da lavoura entram, inclusive as anteriores/posteriores ao lote, e `atividades_diarias.lote_id` (existente, dump:726) é ignorado.
3. **Insumos:** `getInsumosByLoteId()` (159-175) devolve `DISTINCT ad.insumo_utilisado` (texto) + data/tipo. Não há quantidade aplicada, dose, nem NF — embora as colunas existam em `lote_insumo` (quantidade_utilizada, unidade_medida, data_aplicacao — dump:1906-1908) e em `rastreabilidade_insumos_processo` (numero_nf etc. — dump:2609-2621). O elo é textual e frágil.
4. **Confirmação por assinatura/QR:** `lotes_producao.unidade_medida` é texto livre (o form é `type="text"`, `lotes_producao/cadastrar.php:86`).

### 5.2 QR Code
- Geração **no cliente** com `qrcodejs 1.0.0` (CDN `cdnjs`, `lotes_producao/listar.php:127`), um `<div class="qrcode" id="qrcode-{id}">` por linha (44, 58-61) e `new QRCode(el, {text: SITE_URL + '/rastreabilidade?uuid=' + uuid, correctLevel: H})` (215-222, e novamente em 277-283 em alta resolução para impressão).
- Etiqueta: `imprimirQRCode(loteId)` (229-290) monta uma janela de impressão com 34 mm, dados do lote e rodapé "Escaneie o QR Code… (Portaria MAPA 52/2021)" (270).
- ⚠ `lotes_producao/listar.php:57` usa `data-rastreio-url="…/rastreabilidade?uuid=…"` e a coluna 1 é o QR; `imprimirQRCode` lê `td:nth-child(2)` para a URL (240) e `td:nth-child(3..6)` para nome/produto/data/quantidade (234-237) — funciona apenas porque a coluna 0 (`id`) está `display:none` no HTML mas **continua contando** no `nth-child` (o índice usado é coerente com a tabela real, não com o texto do comentário).
- Não há endpoint de QR no servidor (nenhuma lib PHP de QR no `composer.json`).

### 5.3 Endpoint público
- `rastreabilidade.php` (raiz do docroot, **sem login**, raiz:2). Aceita `?uuid=` (6) e consulta (`14-26`) juntando lote→produto→lavoura→propriedade→associado, expondo produtor, `registro_cnpo`, produto, colheita, lavoura+cultura+área, endereço/CEP, observações (91-136). Em seguida lista atividades da lavoura (31-38/148-173) e insumos distintos (43-50/175-199).
- Se `uuid` for vazio, exibe formulário de consulta manual (205-208); UUID inválido → mensagem de não encontrado (211-216).
- ⚠ Não há rate-limiting, token, nem auditoria de acesso; o `uuid` do lote é o único segredo (`char(36)`, gerado por `mt_rand` em `LoteProducao.php:23-29`, PHP `mt_rand` = **não criptográfico**). Um lote excluído deixa de ser rastreável (`excluido = 0` na cláusula, 24).
- ⚠ A cadeia é quebrada para **lotes de agroindústria** (produto sem `lavoura_id`): lavoura/propriedade/atividades/insumos ficam vazios (`p.lavoura_id` nulo nos LEFT JOINs).

---

## 6. Validação de insumos orgânicos

### 6.1 `InsumoValidador` (`classes/InsumoValidador.php`)
- `buscarAprovado($nome)` (16-23): `SELECT * FROM insumos_organicos_aprovados WHERE nome LIKE ? OR nome_comercial LIKE ? LIMIT 1` com `%\${nome}%`, **sem ORDER BY e sem índice utilizável** (`idx_nome` não serve para prefixo curinga).
- `avaliar($nome)` (30-64) devolve 4 estados: `success` (permitido=1), `warning` (achou registro com `permitido` falso → "lista de RESTRIÇÃO ou PROIBIÇÃO"), `not_found` (não localizado → **bloqueado por conformidade**), `error` (nome vazio).
- `permitido($nome)` (71-73) = `avaliar()['permitido']`.

### 6.2 Comportamento de bloqueio (RF06)
| Ponto | Arquivo:linha | Efeito |
|---|---|---|
| Cadastro de insumo (servidor) | `insumos/cadastrar.php:105-130` | Bloqueia; se permitido grava `aprovado_organico=1`, `tipo_insumo`, `insumo_aprovado_id`, `restricoes` |
| Cadastro de insumo (cliente) | `insumos/cadastrar.php:310-400` | Consulta `validar_insumo.php` (comprimento mínimo 3) e impede o submit |
| Edição de insumo | `insumos/editar.php` | **Não valida** (não há chamada ao validador) |
| Cadastro de atividade | `atividade_diaria/cadastrar.php:141-151` | Bloqueia a atividade inteira se o insumo não for permitido |
| Edição de atividade | `atividade_diaria/editar.php:190-196` | Lança exceção → `rollBack` |
| UI de atividade | `cadastrar.php:319-373` / `editar.php:403-460` | `estadoInsumo` cacheado; `form.submit()` em 361-367 |
| Vitrine de conformidade | `insumos/validacao.php:126-157` | Reclassifica cada insumo na hora (N+1) |
| API da lista | `api/insumos_aprovados.php:41-61` | Filtro `?permitido=1\|0` |

### 6.3 Fragilidades específicas (com prova)
1. **Substring libera qualquer coisa:** `LIKE '%nome%'` + `LIMIT 1`. Digitar `"a"` (`atividade_diaria/cadastrar.php:319-323` só exige ≥2 no cliente; o servidor não exige nada, `141-145`) encontra o primeiro registro aprovado cuja `nome` contenha "a" e retorna `permitido = true`. **Bypass de conformidade.**
2. **Casamento invertido:** o parâmetro é o texto digitado, então nomes comerciais longos ("Adubo de Cama de Aviário") nunca casam com o genérico "Esterco animal curtido" → `not_found` → bloqueio indevido. O caminho do modal (`insumos/cadastrar.php:406-544`) mitiga isso ao forçar a escolha de um item exato da lista, mas preencher o campo livremente contorna.
3. **Inconsistência de gravidade:** `warning` sai do `avaliar()` como não-permitido, e tanto `cadastro de insumo` quanto `atividade` tratam igual a `not_found` (bloqueio), ignorando que o registro restrito poderia ter uso condicionado pelas `restricoes`.
4. **Enum corrompido:** gravar `'Orgánico'` (`insumos/cadastrar.php:113`) contra um enum `'Org├ónico'` resulta em string vazia nos dados reais (`dump:1788`) — a classificação por tipo de certificação está perdida.
5. **Nenhum vínculo com lote/produto:** o resultado da validação não é propagado para `lotes_producao`, `lote_insumo` nem `rastreabilidade_insumos_processo` no contexto do caderno de campo.

---

## 7. Relatórios do caderno de campo

### 7.1 Formatos e bibliotecas
- **PDF:** mPDF via `vendor/autoload.php` (`use Mpdf\Mpdf`), `Content-Type: application/pdf`, `Content-Disposition: inline` (ex.: `relatorios/atividades.php:13,20,231-237`).
- **Excel:** PhpSpreadsheet `Xlsx` com `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` + `attachment;filename=...xlsx` (ex.: `atividades_excel.php:105,219-230`).
- **HTML/tela:** `relatorios_tabs.php` (abas Bootstrap + DataTable + botões "Excel"/"PDF" que montam URL para o despachante) e `relatorios_admin.php` (abas + links para `/api/relatorios/admin_export.php?tipo=html|excel|pdf`).
- Despachante: `relatorios/exportar_relatorio.php:18-98` mapeia `relatorio × tipo` → arquivo; `propriedade` e `geral` têm **apenas PDF** (76, 80); se o `_excel` não existir, cai no PDF (88-93) — hoje todos os pares existem.

### 7.2 Inventário
| Relatório | HTML | PDF | Excel | Filtros (linhas) |
|---|---|---|---|---|
| Atividades | `form_atividades.php` (form GET, período padrão mês corrente, status, categoria, propriedade, associado só para nível 1/2 — 32-38, 58-113) | `atividades.php` | `atividades_excel.php` | `data_inicio`, `data_fim`, `status`, `categoria_id`, `propriedade_id`, `associado_id` (24-29; SQL 89-106) |
| Insumos | — | `insumos.php` | `insumos_excel.php` | escopo por nível (39-45) |
| Produção | — | `producao.php` | `producao_excel.php` | `prop.associado_id` + data (31, 37-67) |
| Lotes | — | `lotes.php` | `lotes_excel.php` | `prop.associado_id` (31, 37-53); `lotes.php:176-178` traz as atividades da lavoura do produto |
| Produtores/Associados | — | `produtores.php` | `produtores_excel.php` | repassa `$_GET` ao `ReportManager::getAssociadosReport` (19-20) |
| Propriedades | — | `propriedades.php` | `propriedades_excel.php` | `$_GET` → `getPropriedadesReport` (`cidade` em `ReportManager.php:319-322`) |
| Ocorrências | — | `ocorrencias.php` | `ocorrencias_excel.php` | `severidade`, `status`, `propriedade_id`, `data_inicio`, `data_fim`, escopo (21-70) |
| Propriedade (detalhado) | — | `propriedade.php` | — | `p.associado_id` (42, 49-63) + atividades da propriedade (135-138) |
| Geral consolidado | — | `relatorio_geral.php` | — | consome a **view** `view_caderno_campo_consolidado` (37-55) e filtra por e-mail do associado (40-51); lotes em 79-96 |
| Abas (tela) | `relatorios_tabs.php` | via despachante | via despachante | `data_inicio`, `data_fim`, `categoria_id`, `status` (17-25); 6 abas com colunas/campos declarados (28-65) |
| Admin (tela) | `relatorios_admin.php` | `admin_export.php` | idem | `data_inicio`, `data_fim`, `status`, `tipo`, `cidade`, `tipo_fornecedor` (18-26); 5 abas (31-62) |
| Dashboard de relatórios | `modulos/caderno_campo/relatorios.php` — **sem rota** | — | — | filtros decorativos (441-471); estatísticas 36-247; gráficos Chart.js 608-689; `exportarRelatorio()` 696-703 |

### 7.3 Regras de escopo nos relatórios
- `relatorios_tabs.php`/`relatorios_admin.php`: `relatorios_tabs` exige apenas login (7-11); `relatorios_admin` exige `hasPermission(NIVEL_GESTOR)` (8-13).
- `ReportManager` aplica o escopo por `user_nivel > NIVEL_GESTOR` em cada método (`ReportManager.php:166-169, 206-209, 238-241, 273-276, 313-316`); `getUsuariosReport` retorna `[]` para não-gestor (344-346).
- Arquivos de exportação checam somente `isset($_SESSION['user_id'])` (`atividades.php:8-10`, `insumos.php:6-8`, `atividades_excel.php:8-10`, etc.) e **não revalidam nível** — a proteção depende do `WHERE` interno (linhas 74-84 em `atividades.php`, 39-45 em `insumos.php`).
- ⚠ `relatorios/lotes.php:42` e `producao.php:42`/`producao_excel.php:31` filtram por `prop.associado_id` (caminho produto→lavoura→propriedade): lotes de agroindústria **não aparecem** para ninguém abaixo de gestor e, para gestor, aparecem com responsável vazio.
- ⚠ `relatorio_geral.php:54` depende da view `view_caderno_campo_consolidado` (existe no dump, 2774+; definição canônica em `recicla/create_view_caderno_campo.sql`, que por sua vez referencia colunas inexistentes `l.cultura`, `l.latitude`, `l.longitude` — o script de `recicla/` **falharia** se rodado hoje).

---

## 8. Gaps, duplicações e inconsistências

### 8.1 Rotas / código morto e órfão
1. `propriedades/historico_conversao.php` — **sem rota** (link quebrado em `propriedades/listar.php:132`).
2. `modulos/caderno_campo/relatorios.php` — **sem rota** (713 linhas de dashboard/Chart.js inalcançáveis).
3. `index.php:495-498` — bloco `if ('relatorios' && !isset($parts[1]))` **inalcançável** (o roteador sai antes, em 32-35).
4. `lotes_producao/debug_qr.php`, `teste_qr.php`, `teste_qrcodejs.php`, `ocorrencias/cadastrar.php.bak` — lixo de desenvolvimento (bloqueados por `.htaccess:47-65`, mas versionados).
5. Tabelas/colunas mortas: `lote_atividade` (0 usos), `lotes_producao.atividades_id`, `lotes_producao.insumos_id`, `atividades_diarias.categotia_id`, `caderno_campo` (tabela legada vazia, dump:782-795), `atividades_diarias.lote_id` (lido por relatórios, nunca escrito).

### 8.2 Duplicações
6. Duas telas de relatório para o mesmo domínio: `relatorios_tabs.php` (via `ReportManager`) e `relatorios.php` (via SQL próprio) + `relatorios_admin.php` — três implementações de "listar e exportar" com colunas e filtros divergentes.
7. Duas rotas para o mesmo listador (ex.: `/insumos` e `/caderno-campo/insumos`; `/lavouras` e `/caderno-campo/lavouras`) — 2 blocos duplicados por recurso em `index.php`.
8. Dois dashboards de produtor: `dashboard.php` (não usado pelo login) e `dashboard_agricultor.php` (destino de `login.php:12,181,207`).
9. Lógica de IDOR reimplementada em 15+ arquivos em vez de usar `ApiAuth::exigirProprioAssociado` (usado uma única vez).
10. Validação de insumo duplicada em 4 blocos JS quase idênticos (atividade cadastrar/editar, insumo cadastrar/editar).
11. Duas `UNIQUE KEY` idênticas em `categorias_atividades.associado_id` (dump:886-887).
12. Duas colunas de "nome da propriedade" na mesma tabela (`propriedade` e `nome`) e usos conflitantes: `p.propriedade` (`ReportManager.php:182`, `relatorios.php:194`) **vs** `p.nome` (`atividade_diaria/listar.php:43`, `visualizar_imagens.php:44`, `lotes_producao/rastrear.php:83`).

### 8.3 Inconsistências de dados e de contrato
13. `insumos.tipo_insumo` enum com mojibake (dump:1763) × `'Orgánico'` no código (`insumos/cadastrar.php:113`) × valores `''` nos dados (dump:1788).
14. `insumos.estoq_anterior/quant_recebida/quantidade_estoque` são `int` na DDL (dump:1760-1762) mas o form aceita `step="0.01"` (`insumos/cadastrar.php:202-215`) e o código converte para `float` (96-97).
15. `propriedades.cidade` varchar(100) com JOIN em `cidades.id` (`Propriedade.php:29,83`); cadastro grava ID, edição grava texto (`editar.php:137`).
16. `propriedades.estado` char(2) juntado a `estados.uf` (correto) mas o form de edição tem lista hardcoded (141-170) e o cadastro usa `Associado::getEstados()` (179).
17. Soft delete inconsistente: soft em atividades/insumos/lotes/ocorrências; **hard** em lavouras, produtos, categorias, histórico de conversão — e nenhuma FK protege `lotes_producao.produto_id`.
18. `AtividadeDiaria`/`CategoriaAtividade` declaram "métodos update/delete seriam adicionados" (`AtividadeDiaria.php:128`) — as telas driblam a classe com SQL inline, então dois caminhos de escrita coexistem (classe vs página).
19. `Lavoura::create()` retorna `bool|string`; `Insumo::create/update`, `Produto::*/`, `LoteProducao::*` retornam `bool`.
20. `ocorrencias/cadastrar.php:59` sobrescreve `associado_id` mesmo para Gestor (não permite registrar ocorrência em nome de terceiro), enquanto `atividades` e `propriedades` permitem.
21. `ocorrencias_manejo.status` (enum com 4 estados) nunca é definido no cadastro nem editável na edição — só via `api/ocorrencias.php` (que está quebrado por CSRF).
22. `lotes_producao.rastrear.php` e `relatorios/lotes.php` divergem no critério de dono: o primeiro por `lotes.associado_id`, o segundo por `propriedades.associado_id`.

### 8.4 Lacunas funcionais
23. Sem idempotência/versionamento no sync offline (seção 4.3) e sem tratamento de sessão expirada.
24. Offline cobre **apenas 3 formulários de criação**; edições, exclusões, uploads de propriedade e registros de correção de ocorrência não têm caminho offline.
25. Não há UI para vincular atividades a lotes (`lote_atividade`, `lote_id`) nem quantidades de insumos por lote (`lote_insumo`) — a rastreabilidade é reconstruída por `lavoura_id`.
26. Não há tela para consultar/gerenciar `insumos_organicos_aprovados` (só leitura via `api/insumos_aprovados.php` e o modal); atualizar a lista exige rodar SQL.
27. Não há exportação de dados do caderno no formato exigido por auditoria orgânica (ex.: planilha de insumos aplicados por lote/talhão com doses) — `relatorios/lotes.php:176-178` só lista atividade/categoria.
28. `dashboard_agricultor` calcula peso `ocorrencias = 10` (112) sem utilizá-lo.

### 8.5 Segurança (matriz resumida)
| Problema | Onde | Impacto |
|---|---|---|
| POST sem CSRF | insumos cadastrar/editar; produtos cadastrar/editar; lavouras cadastrar/editar; lotes cadastrar/editar; ocorrencias/editar; propriedades cadastrar/editar; categorias cadastrar/editar; historico_conversao (POST e **delete via GET**) | CSRF em criação/edição e, no histórico de conversão, exclusão por `<img src>` |
| Endpoint de validação sem autenticação | `validar_insumo.php` | enumeração da lista oficial (baixo) |
| Bypass de conformidade | `InsumoValidador.php:17-22` + limite de 2 caracteres só no cliente | atividade/insumo irregular cadastrado |
| UUID previsível | `LoteProducao.php:23-29` (`mt_rand`) | adivinhação de `/rastreabilidade?uuid=` (dados do produtor, endereço, CNPO) |
| Fallback de dono | `dashboard_agricultor.php:23-31` | leitura de dados de outro produtor |
| IDOR residual | `propriedades/editar.php:29`, `propriedades/visualizar_imagens.php:25`, `historico_conversao.php:68` checam `$_SESSION['user_nivel'] == NIVEL_USUARIO` (comparação frouxa) em vez de `hasPermission`/`getAssociadoIdResolvido` | inconsistente com o resto |
| Consulta por coluna legada | `atividade_diaria/listar.php:43`, `visualizar_imagens.php:44`, `lotes_producao/rastrear.php:83` (`p.nome`) | coluna "Propriedade" vazia (defeito funcional, não segurança) |
| Sem FK em `atividades_diarias` | dump:706-730 | órfãos e `associado_id`/propriedade cruzados entre produtores |
| `uploads/` | `uploads/.htaccess` desliga o engine PHP e nega extensões executáveis | OK |
| SW devolve 200 no fallback offline (`sw.js:181`) | `sw.js` | mascara falha de rede em testes automatizados |

---

## 9. Anexos — pontos de código citados

- Roteador: `index.php:2-3, 32-35, 158-545, 868-902`; `.htaccess:1-65`.
- Classes: `AtividadeDiaria.php` (16-31, 41-51, 58-80, 87-126, 128), `CategoriaAtividade.php` (16-25, 49-66, 73-90, 97-106), `Insumo.php` (16-44, 51-80, 87-97, 104-141, 165-204, 211-220), `InsumoValidador.php` (16-23, 30-64, 71-73), `Lavoura.php` (19, 48-54, 97-140, 164-231), `LoteProducao.php` (17-50, 56-70, 77-95, 136-152, 159-175, 183-200, 207-216), `Ocorrencia.php` (15-40, 45-69, 89-124, 129-174, 179-189), `Produto.php` (17-26, 33-50, 83-153, 162-211, 235-320, 344-404, 411-420), `Propriedade.php` (16-39, 46-71, 78-94, 101-127, 134-162, 169-178), `HistoricoConversao.php` (13-18, 31-38, 43-68, 70-87, 89-93).
- Infra: `includes/header.php:5-19, 35, 51-52, 70-79, 99-131`, `classes/Database.php:8-23`, `classes/CSRFProtection.php:24-38, 77-109`, `classes/ApiAuth.php:133-183`, `classes/AuditLog.php:13-27`, `classes/Sanitizer.php:15-21, 119-131`, `classes/ImageUploader.php:10-20, 37-71, 78-112`, `classes/User.php:114-149, 259-329`, `classes/ReportManager.php:161-337`.
- PWA: `sw.js` (1, 6-14, 23-42, 45-56, 59-83, 99-132, 135-182), `assets/js/db_sync.js` (2-9, 12-34, 36-58, 60-94, 96-113, 115-161, 163-237, 239-295, 297-315, 319-320), `manifest.json`, `modulos/caderno_campo/sincronizacao.php`.
- Schema: `sql/_backup/sisagro_pos_fase0_20260911.sql` — `audit_log` 750-762, `atividades_diarias` 706-730, `caderno_campo` 782-795, `categorias_atividades` 878-888, `cidades` 1031-1040, `estados` 1413-1422, `fornecedores` 1649-1668, `historico_conversao` 1688-1701, `insumos` 1753-1779, `insumos_organicos_aprovados` 1799-1822, `lavouras` 1842-1857, `lote_atividade` 1877-1883, `lote_insumo` 1902-1911, `lotes_producao` 1930-1946, `ocorrencias_manejo` 2172-2201, `produtos` 2490-2512, `propriedade_membros` 2532-2543, `propriedades` 2563-2589, `rastreabilidade_insumos_processo` 2609-2621, `view_caderno_campo_consolidado` 2770+.
- Migrações/seeds: `sql/alter_insumos_organico.sql:12-30`, `sql/schema_organico_sprint1.sql:14-53`, `sql/seed_insumos_aprovados{,_animal,_processamento}.sql`, `sql/criar_tabela_ocorrencias.sql`.
