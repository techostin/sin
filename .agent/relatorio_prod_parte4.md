
# PARTE 4 — PRODUÇÃO: TRANSVERSAIS E NÚCLEO VERIFICADO

## 4.1 Modelo de permissão e escopo dos módulos de produção

Todas as telas de `agroindustria`, `producao_animal`, `manejo_animal` e `fornecedor` seguem o mesmo padrão:
1. Checagem de sessão no topo: `if (!$user->isLoggedIn()) { header('Location: '.SITE_URL.'/login'); exit; }` (ex.: `agroindustria/atividades_listar.php:10`, `producao_animal/painel.php:8`, `manejo_animal/animais_listar.php:10`, `fornecedor/listar.php:8`).
2. Escopo por produtor: `$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);` e repasse às classes, que só aplicam o filtro `if ($filtroAssociado)`.

Mapa de escopo (linhas citadas):

| Módulo | Arquivos com filtro de escopo | Linha |
|---|---|---|
| agroindustria | `atividades_listar` | `:32` |
| | `receitas_listar` / `receitas_cadastrar` / `receitas_editar` | `:27` / `:18` / `:28` |
| | `ordens_listar` / `ordens_cadastrar` | `:25` / `:24` |
| | `higienizacao_listar` | `:25` |
| | `ocorrencias_listar` | `:32` |
| | `setores_listar` | `:32` |
| producao_animal | `alimentacao_listar/editar/cadastrar` | `:26` / `:28` / `:16` |
| | `sanidade_listar/editar/cadastrar` | `:26` / `:28` / `:16` |
| | `reproducao_listar/editar/cadastrar` | `:26` / `:28` / `:16` |
| | `ambientes_listar` / `lotes_listar` | `:26` / `:26` |
| manejo_animal | `animais_listar` | `:25` |
| | `animais_cadastrar` / `animais_editar` (escopoLotes + lista de associados) | `:19,22` / `:31,35` |
| fornecedor | `listar` (gestor vê todos; nível 3 vê "Meus Fornecedores") | `:16,26-27` |
| | `editar` (bloqueios de gestor em `:37,53,81,136`), `excluir` (`:17`), `cadastrar` (exige `NIVEL_USUARIO`, `:18`) | — |

- **Defesa anti-IDOR pontual verificada**: `manejo_animal/animais_visualizar.php:27` compara `!(int)$animal['associado_id'] !== (int)$user->getAssociadoId()`; `agroindustria/ordens_visualizar.php:25-31` compara `ordens_producao.associado_id` com `getAssociadoIdResolvido()` e `isGestorOuAdmin()`.
- ⚠️ **Falha estrutural de escopo (IDOR)**: `getAssociadoId()` (`classes/User.php:259-272`) retorna `null` quando o vínculo existe só via `associados.user_id` (caso 4 de `getAssociadoIdResolvido()`, `classes/User.php:321-325`). Nesse cenário `$escopo` vira `null` e as classes **deixam de filtrar** (`if ($filtroAssociado)`), expondo registros de **todos** os produtores a um usuário de nível 3. As telas de detalhe que usam a versão robusta negam, mas as **listas** vazam. Correção mínima: usar `getAssociadoIdResolvido()` nos mesmos moldes de `ordens_visualizar.php:26-31`.

## 4.2 AGROINDÚSTRIA — núcleo de cálculo (verificado por leitura integral)

### `classes/ReceitaAgroindustria.php` (199) — receitas e selo orgânico
- **Tabelas**: `receitas_agroindustria` (soft delete via `excluido`, `:18,32,196`), `ingredientes_receita` (`:16,39,156,171`).
- `getAll($filtroAssociado)` `:13-28`: subquery `(SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes`, `WHERE r.excluido = 0`, filtro opcional por `r.associado_id`, ordem `criado_em DESC`.
- `getIngredientes()` `:37-42`; `softDelete()` `:194-198` (`UPDATE ... SET excluido = 1`).
- **`fatorConversao($unidade)` `:47-59`** — base comum gramas/mL:
  `kg → 1000`, `mg → 0.001`, `t → 1000000`, `l → 1000`, `ml → 1`, `un/und → 1`, **default `1`** (g/mL/outro).
- **`calcularPercentualOrganico(array $ingredientes)` `:65-81`**:
  `qtd = (float)($ing['quantidade'] ?? $ing['proporcao_porcentagem'] ?? 0)`;
  `peso = qtd * fatorConversao($ing['unidade_medida'] ?? 'kg')`;
  `somaPeso += peso`; se `!empty($ing['eh_organico']) → somaOrganico += peso`;
  retorno `round((somaOrganico / somaPeso) * 100, 2)` (`:80`); `0.0` se `somaPeso <= 0`.
  → Métrica é **massa (g)**, não "proporção declarada". Massa e volume são somados no mesmo total (kg→1000 g e l→1000 mL), o que **mistura unidades** quando há ingredientes líquidos e sólidos — o percentual orgânico fica metodologicamente inconsistente.
- **`classificarSelo($percentual)` `:89-99`** (Portaria 52/2021): `>= 95.0 → 'PRODUTO_ORGANICO_BRASIL'`; `>= 70.0 → 'INGREDIENTES_ORGANICOS'`; senão `'SEM_SELO'`.
- **`create($dados,$ingredientes)` `:101-131`**: calcula percentual e selo **antes** de abrir a transação (`:103-104`), insere a receita (`:108-121`), grava ingredientes (`:124`) e faz commit; rollback + rethrow em exceção (`:127-130`).
- **`salvarIngredientes()` `:168-192`**: ignora item quando `nome === '' || peso <= 0` (`:179-181`); grava `quantidade` **e** `proporcao_porcentagem = $peso` (`:188`) — ou seja, a coluna chamada "proporcao_porcentagem" armazena **peso em base g/mL**, não percentual. A página pública trata esse campo como gramas (`agroindustria/rastreabilidade.php:131`, imprime "`g`"), confirmando a sobrecarga semântica.
- **`update()` `:133-166`**: recalcula percentual/selo e faz `DELETE FROM ingredientes_receita WHERE receita_id = ?` + reinserção (`:156-158`) dentro de transação.
- **Enums do schema**: `selo_autorizado ENUM('PRODUTO_ORGANICO_BRASIL','INGREDIENTES_ORGANICOS','SEM_SELO')`, `status ENUM('Rascunho','Ativa','Inativa')`, `percentual_organico_calculado DECIMAL(5,2)` (`migration.sql:1436-1449`).

### `classes/OrdemProducao.php` (160) — ordem de produção e balanço de massas
- **Tabelas**: `ordens_producao o` JOIN `receitas_agroindustria r` (`:15-18,32-36`); `rastreabilidade_insumos_processo ri` LEFT JOIN `lotes_producao`, `produtos`, `insumos`, `fornecedores` (`:44-54`); `excluido = 0` em todas as leituras.
- `getAll($filtroAssociado)` `:13-28` (ordem `data_processamento DESC`); `getById($id)` `:30-40` (traz `r.selo_autorizado AS selo_receita`); `getItensRastreabilidade($ordemId)` `:42-57` (traz `nome_lote`, `nome_produto`, `nome_insumo`, `lote_insumo`, `tipo_insumo`, `fornecedor`, `fornecedor_cnpj`).
- **`calcularBalanco(array $itens, $quantidadeFinal, $toleranciaPct = 10.0)` `:64-94`** — implementação literal:
  - `totalEntrada = Σ (float)$item['quantidade_utilizada']` (`:66-69`).
  - Se `totalEntrada <= 0` → `divergencia_pct = 0`, `status = 'EM_ANALISE'`, mensagem "Nenhum lote primário informado para o balanço." (`:72-80`).
  - `divergenciaPct = round((abs(totalEntrada - quantidadeFinal) / totalEntrada) * 100, 2)` (`:82`).
  - `status = divergenciaPct > toleranciaPct ? 'ALERTA_FRAUDE' : 'APROVADO'` (`:83`) — **tolerância de 10%**; a mensagem de alerta cita kg e pede auditoria (`:90-92`).
- **`create($dados, array $itens)` `:101-153`**:
  - Calcula o balanço (`:103`) e **grava o status resultante** em `ordens_producao.status_auditoria` (`:121`).
  - Campos: `receita_id`, `higienizacao_previa_id` (opcional, `:116`), `lote_processado_final`, `data_processamento`, `quantidade_final_obtida`, `unidade_medida` (default `'KG'`), `percentual_organico`, `selo_autorizado`, `associado_id`, `observacoes`, `criado_por` (`:108-127`).
  - Itens em `rastreabilidade_insumos_processo`: `lote_primario_id`, `produto_id`, `insumo_id`, `numero_nf`, `quantidade_utilizada`, `unidade_medida` (`:130-145`).
  - Transação com rollback + rethrow (`:147-152`).
  - ⚠️ **O docblock (`:96-100`) afirma "Bloqueia se não houver higienização válida nas últimas 24h (RF08)" — mas `create()` não chama `haHigienizacaoValida()`**. A trava existe apenas no controlador (`modulos/agroindustria/ordens_cadastrar.php:149`).
- `softDelete()` `:155-159` (`UPDATE ... excluido = 1`).

### `classes/ControleHigienizacao.php` (94) — higienização / RF08
- `getAll($filtroAssociado)` `:13-25` (`excluido = 0`, ordem `data_hora_inicio DESC`); `getById()` `:27-32`.
- **`haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24)` `:38-50`**:
  ```sql
  SELECT COUNT(*) FROM controle_higienizacao
   WHERE excluido = 0 AND data_hora_fim IS NOT NULL
     AND equipamento_linha = ? AND data_hora_fim <= ?
     AND data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)
  ```
  Parâmetros enviados: `[equipamentoLinha, dataProcessamento, dataProcessamento, (int)intervaloHoras]` (`:48`).
  ⚠️ **`$associadoId` é recebido mas NUNCA usado na query** — a higienização de outro produtor satisfaz a trava.
- **`listarSanitizantesPermitidos()` `:58-67`**: `SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome` — lista oficial (Anexo I da Portaria 52/2021, redação da Portaria SDA 811/2023).
- `create($dados)` `:69-87`: `data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por`; `softDelete()` `:89-93`.

### `modulos/agroindustria/higienizacao_cadastrar.php` (110) — rota `/agroindustria/higienizacao/nova`
- **Guarda**: `isLoggedIn()` (`:10-13`) — **qualquer nível**, sem restrição a gestor.
- **Campos** (`:64-100`): `data_hora_inicio` (datetime-local, required, default agora), `data_hora_fim` (opcional), `equipamento_linha` (**texto livre**, placeholder "Ex.: Linha de envase / Moinho / Tanque 03", `:75`), `sanitizante_utilizado` (**select** populado por `listarSanitizantesPermitidos()`), `responsavel_nome` (text required), `observacoes`. **Não há select de `associado_id`** — ele vem de `$_POST['associado_id']` ou `$user->getAssociadoId()` (`:30`).
- **Regra de conformidade** (`:34-39`): obrigatórios início+equipamento+sanitizante+responsável; e o sanitizante **precisa estar na lista permitida** (`in_array(trim(...), $sanitizantesNomes, true)`) senão bloqueia com mensagem normativa explícita.
- **Ação**: `create()` + `AuditLog::registrar('HIGIENIZACAO_REGISTRADA','controle_higienizacao',$id,'Equipamento/linha: ...')` (`:41-42`) e redirect `/agroindustria/higienizacao?registrada=1` (`:43`).
- **Sem CSRF** no formulário (não há `CSRFProtection` neste arquivo) — inconsistente com o resto do sistema.

### `modulos/agroindustria/ordens_cadastrar.php` (357) — rota `/agroindustria/ordens/nova`
- **Guarda**: `isLoggedIn()` (`:14`); escopo `$filtroAssociado` (`:24`).
- **Montagem dos dados** (`:101-111`): `receita_id`, `higienizacao_previa_id`, `lote_processado_final`, `data_processamento`, `quantidade_final_obtida`, `unidade_medida` (default `'KG'`), `associado_id` (fallback `getAssociadoId()`), `observacoes`, `criado_por`.
- **Itens do balanço** (`:113-134`): arrays paralelos `item_insumo[]`, `item_quantidade[]`, `item_unidade[]`, `item_nf[]`; **ignora** linhas sem insumo ou com `qtd <= 0`; monta `['insumo_id','lote_primario_id'=>null,'produto_id'=>null,'quantidade_utilizada','unidade_medida','numero_nf']`. → **`lote_primario_id` e `produto_id` são sempre `null` pela UI**, apesar de existirem no schema (`migration.sql:1424-1425`).
- **Validações** (`:136-142`): exige receita, lote final, data e quantidade final; exige ≥1 item ("Informe ao menos um insumo utilizado com quantidade para o balanço de massas").
- **RF08 (trava 24h)** (`:144-170`):
  - Valida a receita (`:145-148`).
  - Chama `haHigienizacaoValida($dados['data_processamento'], 'Qualquer linha', associado_id, 24)` (`:149`) — **a linha é hardcoded como `'Qualquer linha'`**, enquanto o cadastro de higienização grava texto livre (`higienizacao_cadastrar.php:75`). Logo a checagem só passa se alguém tiver digitado literalmente "Qualquer linha".
  - Fallback (`:151-169`): se não há higienização "Qualquer linha" e o usuário **não** escolheu `higienizacao_previa_id` → bloqueia; se escolheu, valida que ela está concluída (`data_hora_fim` não nula) e que `proc - fim <= 24h` e `fim <= proc` (`:160-164`). A variável `$thisOp = null` (`:166`) é código morto.
  - ⚠️ Não valida que a higienização escolhida pertence ao **mesmo associado** nem à mesma linha do processamento.
- **Persistência** (`:172-187`): copia `percentual_organico_calculado` e `selo_autorizado` **da receita** para a ordem (`:175-176`), chama `OrdemProducao::create()` (`:177`), registra `AuditLog::registrar('ORDEM_PRODUCAO_CADASTRADA', ...)` com o status de auditoria (`:179`) e redireciona para `/agroindustria/ordens/{id}?criada=1` (`:180`). Erros são logados e convertidos em mensagem genérica (`:182-186`).
- **Sem CSRF** no POST.

### `modulos/agroindustria/ordens_visualizar.php` (215) — rota `/agroindustria/ordens/{id}`
- **Guarda**: `isLoggedIn()` (`:9-12`) + **anti-IDOR** (`:23-31`): `$donoOp = (int)$op['associado_id']`; se `!isGestorOuAdmin()` e (`$donoOp <= 0` ou `!== getAssociadoIdResolvido()`) → "Acesso negado: esta ordem de produção pertence a outro produtor.".
- **Recalcula o balanço em tempo real** (`:33-34`) a partir dos itens gravados — o número exibido pode divergir do `status_auditoria` armazenado se os itens forem alterados depois.
- **Blocos**: Dados da Ordem (`:66-81`); **RF11 — Balanço de Massas** com Entrada (lotes) / Saída (produto final) / Divergência % e alerta (`:83-105`); **Lotes Primários Processados (Campo)** (`:107-130`) — que, como a UI grava `lote_primario_id = null`, mostra "—"/"—" e o rótulo "Entrada (lotes)" na verdade soma **insumos**; QR Code do produto final via `qrcodejs` (`:134-143`, `:151-160`) e etiqueta de impressão 60×90 mm com população do QR por canvas→dataURL (`:162-214`).
- Link público: `/agroindustria/rastreabilidade?lote=<lote_processado_final>` (`:48`).

### `modulos/agroindustria/rastreabilidade.php` (232) — rota pública
- **Pública por design** (comentário `:2` "Não requer login"); acessível porque o roteador não tem guarda (`index.php:1-8`) e este arquivo não checa sessão.
- **Chave de consulta**: `?lote=` comparado a `ordens_producao.lote_processado_final` (`:14-23`) — **igualdade exata**, com `o.excluido = 0`.
- **Dados expostos**: produto final, lote, data de processamento, **nome do produtor e `registro_cnpo`** (`:15-19`, `:96`), `status_auditoria` (`:86-88`), `percentual_organico`, `selo_autorizado` (`:99-105`), quantidade final (`:107-109`); receita completa e ingredientes com flag orgânico e quantidade (`:116-139`); **origem dos insumos com fornecedor e CNPJ e número da nota fiscal** (`:141-188`).
- **Regra de exibição de quantidade** (`:128-132`): se `quantidade` vazia, imprime `proporcao_porcentagem` com sufixo "`g`" — confirma que a coluna guarda peso.
- **Riscos**: qualquer pessoa com o código do lote obtém nome do produtor, CNPJ do fornecedor e NFs; a página não aplica `noindex` nem rate-limit. `htmlspecialchars()` é usado em todas as saídas textuais verificadas (`:80,91,92,96,104,120,125,155,157,166,168,174,176`), sem XSS evidente.

### `classes/Agroindustria.php`, `AgroSetor.php`, `AgroCategoriaAtividade.php`, `AgroAtividadeDiaria.php`, `AgroOcorrencia.php`
- Persistem respectivamente em `agroindustrias`, `agro_setores`, `agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias` (schema em `migration.sql:1-149`), todos com `associado_id`, `excluido` (soft delete) e `criado_em`.
- `AgroAtividadeDiaria` grava `insumo_utilizado` (`classes/AgroAtividadeDiaria.php:55,58,71,88,100`), coluna **existente** em `agro_atividades_diarias.insumo_utilizado VARCHAR(255)` (`migration.sql:15`) — **não confundir** com `atividades_diarias.insumo_utilisado VARCHAR(100)` (`migration.sql:401`, erro de digitação legado) usado por `classes/LoteProducao.php:161` e `rastreabilidade.php:43`; ambos estão corretos para suas respectivas tabelas.

### `classes/LoteProducao.php` (220) — lotes de produção (produção vegetal/agroindustrial)
- **Tabelas**: `lotes_producao` (`:32,58,79,104,121,186,209`), JOIN `produtos` (`:60,81,140,163`), `lavouras`/`propriedades` (`:82-83`), `atividades_diarias` (`:141,164`).
- `create()` `:17-50`: gera **UUID v4 via `mt_rand`** (`:23-29`) — gerador **não criptográfico** para um identificador público de rastreabilidade (previsível/testável); grava `uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes`.
- `getAll()` `:56-70` — **sem filtro de associação** (só `excluido = 0`); usado onde? Ver Parte 5.
- `getByAssociadoId($id)` `:77-95`: `WHERE (lp.associado_id = :associado_id OR (lp.associado_id IS NULL AND p.associado_id = :associado_id))` — resolve lotes órfãos pela propriedade da lavoura.
- `getByUuid()` `:102-112`; `getById()` `:119-129`.
- `getAtividadesByLoteId($lote_id)` `:136-152`: liga o lote às atividades **pela lavoura do produto** (`produtos.lavoura_id = atividades_diarias.lavoura_id`), não por vínculo direto.
- `getInsumosByLoteId()` `:159-175`: `SELECT DISTINCT ad.insumo_utilisado, ad.data_atividade, ad.tipo_atividade` com o mesmo join — coluna correta (ver acima).
- `update()` `:183-200` (**não atualiza `uuid` nem `associado_id`**); `delete()` `:207-216` (`excluido = 1`).

## 4.3 PRODUÇÃO ANIMAL — schema e travas verificadas

### Tabelas (baseline `migration.sql`)
| Tabela | Linhas | Campos-chave |
|---|---|---|
| `animal_lotes` | `:214-228` | `associado_id, nome_lote, especie, categoria, quantidade, descricao, excluido, criado_por` |
| `animais` | `:187-211` | `associado_id, nome_animal, especie, raca, identificacao, sexo ENUM('M','F'), data_nascimento, status ENUM('ATIVO','VENDIDO','ABATIDO','MORTO'), origem_conversao ENUM('ORGANICO','EM_CONVERSAO','CONVENCIONAL'), lote_id, peso_kg, tipo_entrada ENUM('NASCIDO_PROPRIEDADE','COMPRADO_ORGANICO','COMPRA_CONVENCIONAL','TRANSFERIDO_OCS'), fornecedor_origem, data_entrada, observacoes_origem` |
| `ambiente_criacao_animal` | `:152-170` | `associado_id, nome_instalacao, especie ENUM(BOVINOS..APICULTURA), sistema ENUM('EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO'), tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo` |
| `ambiente_criacao_membros` | `:173-184` | pivot `(ambiente_id, associado_id)` UNIQUE |
| `controle_alimentacao_animal` | `:619-639` | `associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento ENUM('FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA'), produto_utilizado, quantidade_kg, percentual_materia_seca, origem ENUM('ORGANICO','EM_CONVERSAO','NAO_ORGANICO_AUTORIZADO')` |
| `controle_sanidade_animal` | `:682-708` | `data_tratamento, tipo_tratamento ENUM('VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL'), produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento` |
| `controle_reproducao_animal` | `:659-679` | `associado_id, animal_id, **grupo_reprodutivo**, tipo_evento ENUM('COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME'), data_evento, macho_reprodutor_id, previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias` — **não possui `lote_id`** |
| `manejo_animal_registros` | `:1063-1079` | `animal_id, tipo_registro ENUM('CLINICO','VACINACAO','ALIMENTACAO','REPRODUCAO','PARASITARIO','BEM_ESTAR','OBITO'), data_registro, descricao, produto_utilizado, dose_quantidade, periodo_carencia_horas, responsavel` |
| `densidades_maximas_animais` | `:727-738` | `especie, fase, densidade VARCHAR(80), referencia, ativo`, UNIQUE `(especie, fase)` |

### `classes/ProducaoAnimal.php` (461) — inventário de métodos
`getLotes`(`:17`), `getLoteById`(`:32`), `criarLote`(`:39`), `atualizarLote`(`:57`), `softDeleteLote`(`:73`), `atualizarAnimal`(`:81`), `getAlimentacoes`(`:110`), `getAlimentacaoById`(`:128`), `criarAlimentacao`(`:135`), `atualizarAlimentacao`(`:159`), `softDeleteAlimentacao`(`:180`), `getSanidades`(`:188`), `getSanidadeById`(`:206`), `criarSanidade`(`:213`), `atualizarSanidade`(`:245`), `softDeleteSanidade`(`:274`), `getReproducoes`(`:282`), `getReproducaoById`(`:299`), `criarReproducao`(`:306`), `atualizarReproducao`(`:331`), `softDeleteReproducao`(`:353`), `getAmbientes`(`:361`), `getAmbienteById`(`:375`), `criarAmbiente`(`:382`), `atualizarAmbiente`(`:406`), `softDeleteAmbiente`(`:427`), `getDensidades($especie)`(`:435`), `estatisticas($filtroAssociado)`(`:451`).
- **Não existe cálculo de produção de leite / curva de lactação / dias em lactação** em lugar nenhum do módulo: busca por `lacta`, `litro`, `leite`, `curva`, `producao_leite` em todo o diretório `modulos/producao_animal/` retorna **0 ocorrências**, e o mesmo vale em `classes/ProducaoAnimal.php`. O sistema registra reprodução (eventos, `previsao_parto`, `nascidos_vivos`, `natimortos`, `periodo_aleitamento_dias`) mas **não calcula curva de lactação nem volume de leite**; "leite" só aparece no schema como tipo de alimento (`LEITE_COLOSTRO`, `migration.sql:625`).
- **`estatisticas($filtroAssociado)`** `:451-502` — contadores para dashboard: de `animais` → `total`, `ativos` (`status='ATIVO'`), `bovinos` (`especie='BOVINOS' OR especie LIKE '%bovino%'`), `aves`, `suinos` (aceita 'suíno'/'suino'), `convencionais` (`origem_conversao='CONVENCIONAL'`); de `animal_lotes` → `total` e `SUM(quantidade) AS cabecas`; de `controle_sanidade_animal` → `total`; de `controle_reproducao_animal` → `total`; de `manejo_animal_registros` (INNER JOIN `animais` filtrando por `a.associado_id` e `a.excluido = 0`) → `registros`. Reutiliza o mesmo `$where = " WHERE excluido = 0"` (+ `associado_id`) para todas as tabelas — funciona porque **todas** as tabelas consultadas têm as duas colunas (`migration.sql:214-228,619-708,1063-1079`).
- Padrão de exclusão: todos os `softDelete*` são lógicos.

### `modulos/producao_animal/rastreabilidade.php` (333) — rota pública `/producao-animal/rastreabilidade`
- **Pública** (comentário `:2`, "Não requer login"; sem checagem de sessão no arquivo).
- **Duas chaves**: `?uuid=` comparado a `CONCAT('AL-', LPAD(al.id, 6, '0'))` (`:19-24`) e `?lote=` (id numérico) (`:27-35`); sempre com `al.excluido = 0`. O comentário `:18` menciona formato "SINGER-lote-XXXXXX ou uuid", mas o único formato aceito é `AL-######` — o campo do formulário anuncia "AL-000001" (`:296`), coerente com o SQL; o comentário está desatualizado.
- **Blocos**: identificação do lote + produtor e `registro_cnpo` (`:114-169`); **Origem dos Animais (Recepção)** — `animais` do lote com `raca, identificacao, tipo_entrada, fornecedor_origem, data_entrada` (`:38-45`, `:171-198`); **Alimentação** — `controle_alimentacao_animal WHERE lote_id = ?` (`:48-55`, `:200-224`); **Sanidade** — `controle_sanidade_animal WHERE lote_id = ?` (`:58-65`, `:226-262`), com carência e `data_liberacao_organica`; **Reprodução** (`:264-289`).
- ⚠️ **Bug confirmado**: a consulta de reprodução (`:69-72`) usa `WHERE lote_id = ?` em `controle_reproducao_animal`, **tabela que não tem coluna `lote_id`** (`migration.sql:659-679`). A exceção é engolida pelo `try/catch` (`:68-75`) e a seção de reprodução **nunca aparece**.
- ⚠️ **Rótulos incompatíveis com os ENUMs**: `$tipoEntradaLabels` (`:78-83`) mapeia `NASCIDO_PROPRIEDADE/COMPRADO/TRANSFERIDO/RECEBIDO`, mas o schema usa `COMPRADO_ORGANICO/COMPRA_CONVENCIONAL/TRANSFERIDO_OCS` → esses três aparecem **crus** (`:186`). `$origemLabels` (`:84-88`) define `ORGANICO/CONVENCIONAL/DESCONHECIDO`, também divergente do ENUM (`EM_CONVERSAO`), e **não é usado** — a tabela de alimentação imprime `$al['origem']` sem tradução (`:216`).
- **Exposição pública**: nome do produtor, CNPJ/registro CNPO, lista nominal de animais com identificação, tratamentos sanitários e fornecedor de origem. IDs são **sequenciais** (`AL-000001`, `AL-000002`, …) → **enumerável** por qualquer anônimo.
- `htmlspecialchars()` presente nas saídas de texto; o conteúdo de `$lote['descricao']` passa por `nl2br(htmlspecialchars(...))` (`:155`).

## 4.4 MANEJO ANIMAL e FORNECEDOR — verificações transversais
- `manejo_animal` opera a tabela `animais` (cadastro/edição/visualização) e usa `LoteProducao`/lotes para o `select` de lote (`animais_cadastrar.php:19`, `animais_editar.php:31`), restringindo as opções ao produtor quando nível 3.
- `fornecedor` distingue a visão por perfil: gestor vê "Fornecedores Cadastrados" e todos os registros; nível 3 vê "Meus Fornecedores" — vinculados às suas unidades (`fornecedor/listar.php:16,26-27`). O vínculo fornecedor↔unidade é a tabela `fornecedor_unidades` com `unidade_tipo ENUM('propriedade','agroindustria','animal')` + `unidade_id` e UNIQUE `(fornecedor_id, unidade_tipo, unidade_id)` (`migration.sql:874-888`).
- `fornecedores` tem `UNIQUE(cnpj)` e `UNIQUE(email)` (`migration.sql:908-909`) e **não tem `excluido`** — exclusão é tratada em `fornecedor/excluir.php` (ver Parte 5).
- `fornecedores` é referenciada pela cadeia de rastreabilidade da agroindústria via `insumos.fornecedor_id` → `OrdemProducao::getItensRastreabilidade()` (`classes/OrdemProducao.php:52`) e `agroindustria/rastreabilidade.php:34`.

---
