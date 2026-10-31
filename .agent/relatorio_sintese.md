
# PARTE 8 — SÍNTESE CONSOLIDADA, RECONCILIAÇÃO E RISCOS GLOBAIS

## 8.1 Verificações cruzadas feitas pelo autor deste relatório

Os relatórios parciais produzidos para os grupos de produção foram **reconciliados** com leitura própria dos arquivos. Confirmações e correções:

| Afirmação de terceiro/parcial | Status | Evidência própria |
|---|---|---|
| `Mensalidade::gerarPix()`/`processarWebhookPix()` usam `mensalidades.txid` | **CONFIRMADO** | `classes/Mensalidade.php:698,711`; `migration.sql:1082-1099` (sem `txid`); `migration.sql:1235,1240` (`txid` só em `pagamentos`) |
| `PIX_PROVIDER` não definido | **CONFIRMADO** | busca global: só `classes/PixProvider.php:7,18` e `modulos/mensalidades/baixa.php:92,100` |
| `Fornecedor::vincularUnidades()` inexistente, mas invocado | **CONFIRMADO** | única ocorrência no repositório é a chamada `modulos/fornecedor/cadastrar.php:70` |
| `modulos/fornecedor/cadastrar.php` faz POST para rota inexistente | **CONFIRMADO** | `cadastrar.php:83,115` (`/fornecedor/cadastrar`); roteador só tem `/fornecedor`, `/fornecedor/novo`, `/fornecedor/{id}/editar` (`index.php:739-751`) |
| Módulo `fornecedor` não valida CSRF (só imprime o token) | **CONFIRMADO** | `cadastrar.php:11,153` (única referência a `CSRFProtection` no módulo); `editar.php`/`excluir.php` sem token |
| `haHigienizacaoValida()` ignora `$associadoId` | **CONFIRMADO** | `classes/ControleHigienizacao.php:38-48` (4 binds para 4 placeholders; `$associadoId` ausente do SQL) |
| Trava RF08 usa literal `'Qualquer linha'` | **CONFIRMADO** | `modulos/agroindustria/ordens_cadastrar.php:149` vs. texto livre em `higienizacao_cadastrar.php:75` |
| Docblock de `OrdemProducao::create()` promete RF08 que não existe na classe | **CONFIRMADO** | docblock `classes/OrdemProducao.php:96-100`; `create()` `:101-153` não referencia `ControleHigienizacao` |
| Balanço de massas: `|entrada−final|/entrada×100`, tolerância 10% | **CONFIRMADO** | `classes/OrdemProducao.php:82-83` |
| Percentual orgânico = soma de peso orgânico / soma de peso × 100 | **CONFIRMADO** | `classes/ReceitaAgroindustria.php:65-81` |
| Selo ≥95% / ≥70% / <70% | **CONFIRMADO** | `classes/ReceitaAgroindustria.php:89-99` |
| `proporcao_porcentagem` guarda peso (g/mL), não percentual | **CONFIRMADO** | `classes/ReceitaAgroindustria.php:188` grava `$peso`; `modulos/agroindustria/rastreabilidade.php:131` imprime "`g`" |
| `agroindustria/rastreabilidade.php` e `producao_animal/rastreabilidade.php` são públicas | **CONFIRMADO** | comentários `:2`; nenhuma checagem de sessão; roteador sem guarda (`index.php:1-8`) |
| `producao_animal/rastreabilidade.php` consulta `controle_reproducao_animal.lote_id` inexistente | **CONFIRMADO** | query `:69-72`; schema `migration.sql:659-679`; `catch` mudo `:75` |
| Rótulos de `tipo_entrada`/`origem` divergentes dos ENUMs | **CONFIRMADO** | `producao_animal/rastreabilidade.php:78-88` vs. `migration.sql:197,200,629` |
| Escopo por `getAssociadoId()` pode virar `null` ⇒ consulta global | **CONFIRMADO** | `classes/User.php:259-272` (retorna null no caso 4) vs. `classes/User.php:299-329` (versão robusta) |
| Sem CSRF nos módulos `producao_animal`/`manejo_animal` | **CONFIRMADO** | busca de `CSRFProtection` nesses diretórios: 0 ocorrências |
| Sem CSRF em `agroindustria` exceto `minhas_*` | **CONFIRMADO** | `CSRFProtection` aparece só em `minhas_cadastrar.php` e `minhas_editar.php` (busca no diretório) |
| `controle_reproducao_animal` não tem `lote_id` | **CONFIRMADO** | `migration.sql:659-679` |
| `atividades_diarias.insumo_utilisado` é typo legado real | **CONFIRMADO** (evita falso positivo) | `migration.sql:401`; usado corretamente em `classes/LoteProducao.php:161` e `rastreabilidade.php:43`; a tabela distinta `agro_atividades_diarias.insumo_utilizado` (`migration.sql:15`) é usada por `classes/AgroAtividadeDiaria.php:55-100` |
| Sem cálculo de curva de lactação / leite / DEL | **CONFIRMADO** | busca `lacta|litro|leite|curva|producao_leite` em `modulos/producao_animal`: 0 ocorrências; em `classes/ProducaoAnimal.php`: 0 |
| Sem SQL injection nos módulos de produção | **CONFIRMADO por amostragem** | todas as queries lidas usam `prepare()`/`execute()` com placeholders; única interpolação é `LIMIT `+inteiro (`classes/Mensalidade.php:95,532`) e `$where` de literais (`classes/ProducaoAnimal.php:453-458`) |

## 8.2 Tabela única de risco (financeiro + produção), priorizada

| # | Sev. | Grupo | Achado | Evidência |
|---|---|---|---|---|
| 1 | Crítico | Financeiro | `mensalidades.txid` inexistente quebra `gerarPix()` e todos os webhooks PIX (exceção não capturada) | `classes/Mensalidade.php:698,711`; `migration.sql:1082-1099` |
| 2 | Crítico | Financeiro | `PIX_PROVIDER`/`ASAAS_*`/`GN_*`/`MP_*` inexistentes ⇒ fluxo PIX inalcançável | `modulos/mensalidades/baixa.php:92`; `classes/PixProvider.php:18` |
| 3 | Crítico | Fornecedor | Cadastro quebra: método inexistente + POST para rota inexistente | `modulos/fornecedor/cadastrar.php:70,83,115`; `index.php:739-751` |
| 4 | Alto | Financeiro | Webhooks sem assinatura/IP, CORS `*`, erro ecoa txid (sondagem) | `api/pix/webhook/*.php`; `api/.htaccess:14`; `classes/Mensalidade.php:716` |
| 5 | Alto | Financeiro | Baixa duplicável sob concorrência (sem lock/UNIQUE) | `classes/Mensalidade.php:719-721,237-283` |
| 6 | Alto | Financeiro | Estorno e exclusão por DELETE físico, sem auditoria | `classes/Mensalidade.php:318-326` |
| 7 | Alto | Produção | IDOR de exclusão por GET em 6 listagens (lotes, ambientes, alimentação, sanidade, reprodução, animais) | `producao_animal/*_listar.php:17-24`; `manejo_animal/animais_listar.php:17-23` |
| 8 | Alto | Produção | IDOR de edição (sem posse) em alimentação/sanidade/reprodução/animais | `*_editar.php`; `classes/ProducaoAnimal.php:130,208,301`; `classes/ManejoAnimal.php:31-36` |
| 9 | Alto | Produção | Ausência total de CSRF em produção/manejo animal e em quase toda a agroindústria | buscas por `CSRFProtection` nos diretórios |
| 10 | Alto | Transversal | Escopo `getAssociadoId()` nulo ⇒ consultas sem filtro (dados de todos os produtores) | `classes/User.php:259-272`; `*_listar.php:25-26`; `painel.php:18` |
| 11 | Alto | Produção | IDOR de escrita: `associado_id`/`animal_id`/`lote_id`/`receita_id` de POST sem revalidação | `ManejoAnimal.php:29`; `ProducaoAnimal.php:145-146,225-226,316`; `OrdemProducao.php:124` |
| 12 | Alto | Produção | RF08 contornável: higienização de outro produtor valida a OP | `classes/ControleHigienizacao.php:38-48` |
| 13 | Médio-Alto | Financeiro | `contas` sem CSRF/sanitização e sem vínculo contábil com `caixa` | `modulos/contas/cadastrar.php:13-25`; `editar.php:36-52`; `migration.sql:608-616` |
| 14 | Médio-Alto | Financeiro | `gerarLote()` não atômico; ignora valor/vencimento do plano | `classes/Mensalidade.php:158,165-200`; `gerar.php:40,103` |
| 15 | Médio | Produção | Exposição pública enumerável (ID sequencial) com dados pessoais e de fornecedores | `producao_animal/rastreabilidade.php:19-24,132-134`; `agroindustria/rastreabilidade.php:14-23` |
| 16 | Médio | Produção | `densidades_maximas_animais` sem seed ⇒ referência normativa vazia | `migration.sql:727-738`; `ambientes_cadastrar.php:102-105` |
| 17 | Médio | Produção | `macho_reprodutor_id` texto→INTEGER; `especie` coletada e não persistida | `classes/ProducaoAnimal.php:320,343`; `reproducao_cadastrar.php:95-96,121-126` |
| 18 | Médio | Produção | Hard delete de registros de manejo sem posse | `manejo_animal/animais_visualizar.php:39-44`; `classes/ManejoAnimal.php:117-121` |
| 19 | Médio | Fornecedor | Exclusão por GET sem CSRF e sem checagem de dependências | `modulos/fornecedor/excluir.php:23-40` |
| 20 | Médio | Financeiro | XSS refletido no aviso de comunicação + HTML injection no e-mail | `modulos/comunicacao/index.php:89-91,119,188` |
| 21 | Médio | Agro | `ReceitaAgroindustria::update()` faz DELETE+INSERT dos ingredientes (perde ids/histórico) | `classes/ReceitaAgroindustria.php:156-158` |
| 22 | Médio | Agro | Métrica de percentual orgânico mistura massa (g) e volume (mL) | `classes/ReceitaAgroindustria.php:47-59,71` |
| 23 | Médio | Agro | Balanço de massas soma unidades heterogêneas (KG/L/UN) sem conversão | `classes/OrdemProducao.php:66-69` |
| 24 | Baixo-Médio | Financeiro | `Caixa::getSaldo()` é acumulado de todo o histórico (sem competência) | `classes/Caixa.php:121-144` |
| 25 | Baixo-Médio | Financeiro | `getPrevisaoReceita()` agrupa por referência, não por vencimento | `classes/Mensalidade.php:606-624` |
| 26 | Baixo | Produção | `LoteProducao` gera UUID com `mt_rand` (não CSPRNG) para identificador público | `classes/LoteProducao.php:23-29` |
| 27 | Baixo | Produção | `manejo_animal_registros.tipo_registro` tem 7 valores e a UI oferece 3; `periodo_carencia_horas` não coletado | `migration.sql:1066,1071`; `animais_visualizar.php:128-132` |
| 28 | Baixo | Agro | Sem `AuditLog` no caminho financeiro (mensalidade/caixa) | `classes/Mensalidade.php`, `classes/Caixa.php` |

## 8.3 Verificações cruzadas adicionais — AGROINDÚSTRIA (extraídas e conferidas pelo autor)

Extração independente sobre `modulos/agroindustria` para validar os achados da PARTE 5:

| Verificação | Resultado objetivo |
|---|---|
| Guard de perfil `temAgroindustria()` | presente em **12** arquivos: `atividades_{cadastrar,editar,listar}`, `categorias_{cadastrar,editar,listar}`, `setores_{cadastrar,editar,listar}`, `ocorrencias_{cadastrar,editar,listar}`; **ausente** em `receitas_*`, `ordens_*`, `higienizacao_*` e `minhas_*` (que usam apenas `isLoggedIn()` + `hasPermission`) |
| CSRF | só `minhas_cadastrar.php:24` e `minhas_editar.php:40` (`CSRFProtection::validatePostRequest()`); **nenhum** outro arquivo do módulo valida CSRF |
| Exclusão por GET | confirmada em **7** telas de listagem: `setores_listar`, `categorias_listar`, `atividades_listar`, `ocorrencias_listar`, `receitas_listar`, `ordens_listar`, `higienizacao_listar` — todas com `?excluir=<id>` e `softDelete($id)` sem verificação de dono |
| Anti-IDOR presente | apenas em `atividades_editar.php:39`, `setores_editar.php:39`, `ocorrencias_editar.php:38` (compara `associado_id`), `categorias_editar.php:37-40` e `ordens_visualizar.php:26-28` (usam `getAssociadoIdResolvido()`), e `minhas_editar.php:28` |
| Anti-IDOR **ausente** | `receitas_listar.php`, `receitas_editar.php`, `ordens_listar.php`, `higienizacao_listar.php`, `ordens_cadastrar.php`, `higienizacao_cadastrar.php`, `categorias_cadastrar.php`, `minhas_listar.php` (só nas rotas de exclusão) |
| Escrita cross-tenant | `associado_id` vem do POST em `setores_cadastrar.php:41`, `atividades_cadastrar.php:42`, `ocorrencias_cadastrar.php:39`, `receitas_cadastrar.php:57`, `receitas_editar.php:78`, `ordens_cadastrar.php:108`, `higienizacao_cadastrar.php:30` (com fallback para `getAssociadoId()`) |
| `AuditLog::registrar()` com `registro_id = null` | confirmado em `setores_cadastrar.php:43`, `categorias_cadastrar.php:35`, `atividades_cadastrar.php:45`, `ocorrencias_cadastrar.php:44` |
| Ordem de includes em `ordens_visualizar.php` | `header.php`/`menu.php` nas linhas `:3-4` e o `if (!isLoggedIn())` só em `:9-12` ⇒ a checagem local é código morto (o guard real é `includes/header.php:16-19`) |

Confirmações qualitativas da PARTE 5 (reconciliadas com a leitura própria das PARTES 4 e 6):
- **RF08**: além do literal `'Qualquer linha'` (`ordens_cadastrar.php:149`) e da ausência de filtro por produtor (`classes/ControleHigienizacao.php:38-50`), verifica-se que **se** existisse um registro com `equipamento_linha = 'Qualquer linha'` dentro de 24 h, a checagem específica da `higienizacao_previa_id` escolhida (`:154-169`) **não seria executada** — a trava desaparece nesse ramo.
- **Rastreabilidade frágil**: `ordens_producao.lote_processado_final VARCHAR(50)` não tem índice único nem FK (`migration.sql:1203`), e `modulos/agroindustria/rastreabilidade.php:14-23` usa `fetch()` (primeira linha) — dois lotes com a mesma string tornam a consulta pública ambígua (falha de RF12).
- **Balanço de massas**: a fórmula `abs(entrada − final)/entrada` (`classes/OrdemProducao.php:82`) não distingue ganho de perda e as mensagens fixam a unidade em "kg" (`:91-92`), enquanto a UI exibe "kg" independentemente de `unidade_medida` (`ordens_visualizar.php:89,93`).
- **Custo/rendimento/perdas**: **não encontrado** — não há coluna de valor/custo nas tabelas de receita/ordem (dicionário em `migration.sql:1199-1219,1436-1449`) nem qualquer cálculo de rendimento ou perda no módulo.
- **FKs**: `agro_setores`, `agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias`, `receitas_agroindustria` e `controle_higienizacao` não possuem FK declarada no baseline (busca de `FOREIGN KEY` em `migration.sql` cobre apenas `ingredientes_receita`, `ordens_producao`, `rastreabilidade_insumos_processo` e `ocorrencias_manejo`).

## 8.4 Recursos previstos no schema e NÃO implementados (mapa de lacunas)

Financeiro:
- `mensalidades.status_mensalidade` aceita `'isenta'` e `'cancelada'` — nenhum código escreve esses valores (`migration.sql:1093`).
- `pagamentos.txid` (UNIQUE) nunca é preenchido por `baixar()` (`classes/Mensalidade.php:239-258`).
- `caixa.comprovante_url` existe e é gravado na baixa, mas não há upload em nenhuma tela financeira.
- `planos_mensalidades.dia_vencimento`/`valor` não alimentam `gerarLote()`.

Produção animal (lacunas normativas — Portaria MAPA 52/2021):
- kg/animal/dia, composição de dieta, custo e baixa de estoque de alimentação; limite de 15%/20% de MS não orgânica (só texto de ajuda, `alimentacao_cadastrar.php:115`); capacidade/ocupação de ambiente (área ÷ densidade) — nenhum cálculo em `classes/ProducaoAnimal.php`; contagem de 2 tratamentos/ano; cálculo de carência/liberação orgânica; diagnóstico de gestação (ausente do ENUM); intervalo entre partos; dias em lactação; curva de lactação/produção de leite; SISBOV/NIF; genealogia; histórico de movimentação de animais; `ambiente_criacao_membros` não é referenciada por nenhum PHP.

Agroindústria:
- `ingredientes_receita` não referencia `insumo_id`/`fornecedor_id` ⇒ cadeia fornecedor→insumo→receita é textual (`migration.sql:931-943`).
- `lotes_producao.atividades_id`/`insumos_id` e as tabelas `lote_insumo`/`lote_atividade` não são usadas por `LoteProducao` (`migration.sql:1030,1020,1054-1055`).
- Rastreabilidade de lote primário sempre nula porque a UI fixa `lote_primario_id = null` (`ordens_cadastrar.php:128`).

---
