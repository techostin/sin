## Base de verificação

- Leitura integral dos arquivos: modulos/fornecedor/{cadastrar,editar,excluir,listar}.php e classes/{Fornecedor,OrdemProducao,ReceitaAgroindustria,ControleHigienizacao,LoteProducao,Agroindustria}.php.
- Schema de referência: sinporg/prisma/migrations/0_baseline/migration.sql (baseline Prisma).
- Schema real conferido no MySQL local (banco sisagro, via information_schema.COLUMNS e SHOW CREATE TABLE).
- Comportamento verificado em runtime com o PHP do XAMPP (scripts de probe removidos após uso). Resultados citados como "verificado em runtime".

---

# Grupo 1 — modulos/fornecedor

### modulos/fornecedor/cadastrar.php

- **Rota/acesso**: rota amigável em index.php:743-746 (**fornecedor/novo** -> require de modulos/fornecedor/cadastrar.php). Link de menu em includes/menu.php:172 (**SITE_URL/fornecedor**), dentro do bloco "Produção Vegetal" cujo gate é **$temAreaVegetal** (includes/menu.php:18, bloco 160-180). Link "Adicionar Novo Fornecedor" em modulos/fornecedor/listar.php:32 (**SITE_URL/fornecedor/novo**).
- **Guard de sessão/permissão**: guard indireto em includes/header.php:16-19 (redireciona para /login se **!$user->isLoggedIn()**); guard próprio em cadastrar.php:18 — **if (!$user->hasPermission(NIVEL_USUARIO))**. Como NIVEL_USUARIO=3 (config/config.php:25) e User::hasPermission retorna **$nivel <= $required_level** (classes/User.php:114-141), o teste é verdadeiro para qualquer usuário logado de nível 1, 2 ou 3. O bloco 144-145 ("Acesso negado") é inalcançável na prática, pois sem login o header.php já redirecionou. **Observação**: o bloco POST (49-74) executa antes do teste de renderização ($hasAccess, 144).
- **Propósito de negócio**: cadastrar fornecedor (pessoa física/jurídica) e vinculá-lo às unidades produtivas do associado (propriedade / agroindústria / criação animal) para que apareça nas telas dos associados dessas unidades e nas telas de insumo.
- **Campos/formulário**: definidos no array $formFields (90-111), renderizados em 155-182. Campos com **name=**: nome (text, **required**, col md-8, 92); cnpj (text, col md-4, placeholder 00.000.000/0000-00, 93); razao_social (text, md-6, 96); representante (text, md-6, 97); email (type=email, md-6, 100); telefone (declarado tipo "tel" mas renderizado como **type=text**, pois o teste da linha 176 só preserva email/password/date; md-6, 101); produto_fornecido (text, md-12, 104); endereco (text, md-6, 107); bairro (text, md-3, 108); estado (**select, required**, md-3, opções = array_column(Associado::getEstados(),'nome','id'), 109 + 77).
  - Bloco "Unidades atendidas" (184-211): **name="unidade_tipo[]"** (select, 189) e **name="unidade_id[]"** (select com optgroups por tipo, 194), botões "+ Adicionar unidade" (209) e "Remover" (206). CSRF: **CSRFProtection::getTokenField()** na linha 153.
  - **Campo cidade ausente**: não existe input name="cidade" no formulário, apesar de Fornecedor::create fazer bind de :cidade (classes/Fornecedor.php:39) a partir de $dados_sanitizados['cidade']. Verificado em runtime: Sanitizer::sanitizeArray(['nome'=>'Teste','estado'=>23]) não contém a chave 'cidade' -> PHP 8 emite "Undefined array key 'cidade'" e grava NULL.
- **Regras/validações/cálculos**: praticamente inexistentes no arquivo.
  - Guard de permissão (18) e eleição de tipos/unidades (27-47): gestor/admin usa Fornecedor::getTodasUnidades() e $todosTipos fixo ['propriedade','agroindustria','animal'] (28-31); usuário comum usa Produto::getUnidadesDoAssociado($user->getAssociadoId() ?? -1) e $todosTipos derivado das unidades retornadas (33-36). $tipoPadrao (37-46): 'agroindustria' se o perfil dashboard for agroindustria e houver agroindústria; senão 'propriedade' se houver; senão o único tipo; senão o primeiro.
  - Whitelist de unidades no POST (54-69): monta $permitidas como chave "tipo:id" a partir de $unidadesDisponiveis e só aceita pares presentes (64) — validação correta contra adulteração de POST.
  - Nenhuma validação de CNPJ/CPF, e-mail, telefone ou obrigatoriedade server-side além de "nome" (NOT NULL no banco). Não há checagem de duplicidade prévia (só a UNIQUE do banco).
- **Tabelas SQL usadas**: indiretas (via classes). Escrita: INSERT em **fornecedores** (classes/Fornecedor.php:22-23) e INSERT/UPDATE em **fornecedor_unidades** (classes/Fornecedor.php:244, 248-250). Leitura: **propriedades**, **agroindustrias**, **ambiente_criacao_animal** (classes/Fornecedor.php:92-110), **estados** (classes/Associado.php:199), **propriedades/propriedade_membros**, **agroindustrias/agroindustria_membros**, **ambiente_criacao_animal/ambiente_criacao_membros** (classes/Produto.php:167-204).
- **Ações/POST/redirects**:
  - POST recebido no próprio arquivo (49): $fornecedor->create($_POST) (51). Se o retorno for numérico (53), monta $vincular validado (58-69) e chama **$fornecedor->vincularUnidades($resultado, $vincular)** (70) — método que **não existe** em Fornecedor.php (grep em todo o repositório retorna apenas essa chamada; verificado em runtime: method_exists(Fornecedor,'vincularUnidades') === false). Resultado: **PHP Fatal error "Call to undefined method Fornecedor::vincularUnidades()"** sempre que o INSERT funciona. O cadastro morre após gravar o fornecedor, deixando o registro sem vínculo de unidade.
  - Se create() falhar (retorno string), **nenhuma mensagem é exibida** (53 só trata is_numeric), incluindo o caso de CNPJ/e-mail duplicado.
  - action do formulário: **SITE_URL + '/fornecedor/cadastrar'** (83, repetido em 115) — **rota inexistente**: o roteador só trata fornecedor, fornecedor/novo e fornecedor/<id>/editar (index.php:739-751); qualquer outra URL cai no fallback 404.php (index.php:1175-1177). Ou seja, o POST do formulário nem chega ao arquivo pelo roteador; apenas a rota /fornecedor/novo renderiza a tela.
  - Sem redirect após sucesso (renderiza a mesma página com alert success, 71-72).
- **Riscos/gaps**:
  1. **Crítico — fatal error** por chamada a método inexistente (cadastrar.php:70 x classes/Fornecedor.php, sem vincularUnidades).
  2. **Crítico — POST aponta para rota inexistente** (cadastrar.php:83/115 vs index.php:739-751) => 404 no envio.
  3. **CSRF não validado**: token é impresso (153) mas nenhuma chamada a CSRFProtection::validatePostRequest/validateToken existe no módulo (grep global lista apenas cadastrar.php:153 para o módulo fornecedor).
  4. **Cidade nunca coletada** no cadastro (sem input) => NULL e warning.
  5. Telefone renderizado como text (176), perdendo validação nativa; máscara só via JS externo (assets/js/masks.js, header.php:51), que aqui não é usada no campo telefone (sem classe mask-telefone, diferente de editar.php:190).
  6. **Redefinição duplicada** de $page_title/$page_subtitle/$formAction/$cancelUrl/$submitLabel/$message/$message_type/$hasAccess (81-88 e 113-120) e terceira definição de $page_title (122); require_once de header.php e menu.php repetido (6-7 e 123-124) — código morto, sintoma de merge.
  7. **XSS armazenado potencial via JS**: as unidades são embutidas por json_encode (235) e reinseridas por template string com ${u['nome']} sem escape (270) — nome de unidade controlável pelo usuário vira HTML/JS.
  8. Mensagens de erro de banco são silenciadas (nada exibido), mas Fornecedor::create loga e devolve $e->getMessage() (classes/Fornecedor.php:57) — vazamento de detalhe interno apenas no retorno, não renderizado aqui.
  9. Entrada não canonicalizada: CNPJ gravado como digitado (com ou sem máscara), sem padronização (evidência em banco: cnpj "45454654546541" e "98765432000100", ambos inválidos no dígito verificador — verificado com Agroindustria::validarCnpj).

### modulos/fornecedor/editar.php

- **Rota/acesso**: index.php:747-751 (**fornecedor/<id>/editar**, injeta $_GET['id'] = $parts[1]). Link em listar.php:65. action do formulário coerente: SITE_URL + '/fornecedor/' + $id + '/editar' (171).
- **Guard de sessão/permissão**: includes/header.php:3 dispara o guard de login (header.php:16-19). Guard próprio: **if (!$user->isLoggedIn())** (13-17) — libera qualquer nível, inclusive NIVEL_USUARIO. Validação de id em 19-23 e existência em 29-34.
  - **Checagem anti-IDOR explícita** em 37-49: se o usuário não tem NIVEL_GESTOR, carrega Fornecedor::getVisiveisParaAssociado($user->getAssociadoId() ?? -1) (39) e só permite se o id estiver na lista (41-48); senão exibe "Acesso Negado" e encerra (45-48). A checagem roda **antes** do bloco POST (101), portanto o escopo é respeitado na escrita.
- **Propósito de negócio**: editar dados cadastrais do fornecedor e reatribuir as unidades atendidas, com preservação dos vínculos de unidades de outros associados quando o editor é usuário comum.
- **Campos/formulário** (171-288): nome (text, **required**, 174); representante (178); razao_social (182); cnpj (text, classe mask-cnpj, 186); telefone (text, classe mask-telefone, 190); email (**type=email**, 194); produto_fornecido (198); endereco (202); bairro (206); estado (**select required**, 210-215, options de Associado::getEstados() com id/nome/uf); cidade (**select required disabled**, 219-221, populado via AJAX). Bloco de unidades 233-283 com name="unidade_tipo[]" (237, 260) e name="unidade_id[]" (242, 265). **Não há campo status** (não é possível inativar pelo formulário). **Não há token CSRF** no formulário.
- **Regras/validações/cálculos**:
  - Escolha de unidades disponíveis 52-73 (idêntica a cadastrar.php:27-47).
  - Separação dos vínculos em gerenciáveis x alheios para usuário comum (79-96): monta $chavesPermitidas com "tipo:id" (83-85) e classifica cada vínculo (86-93).
  - Validação do POST de vínculos (121-135): whitelist $permitidas, strtolower(trim(tipo)), uid>0, e só aceita chave existente (132).
  - Preservação dos vínculos alheios (136-142) para não-gestor.
  - Nenhuma validação de CNPJ/e-mail/telefone; nenhum uso de Sanitizer — valores de $_POST vão direto para bindParam (106-117).
- **Tabelas SQL usadas**: **UPDATE fornecedores** literal em 104: "UPDATE fornecedores SET nome = :nome, representante = :representante, razao_social = :razao_social, cnpj = :cnpj, telefone = :telefone, email = :email, endereco = :endereco, bairro = :bairro, cidade = :cidade, estado = :estado, produto_fornecido = :produto_fornecido WHERE id = :id" (binds 106-117). Vínculos: UPDATE fornecedor_unidades SET excluido = 1 ... e INSERT ... ON DUPLICATE KEY UPDATE (classes/Fornecedor.php:244 e 248-250). Leitura: fornecedores + cidades + estados (classes/Fornecedor.php:124-128), fornecedor_unidades + UNION de propriedades/agroindustrias/ambiente_criacao_animal (classes/Fornecedor.php:211-224), estados (classes/Associado.php:199).
- **Ações/POST/redirects**: POST tratado no próprio arquivo (101-159) com try/catch PDOException (154-158); não há redirect — apenas mensagem e re-render (145-149, 165-169). Se executa, chama substituirUnidades($id, $vincular, $user->getAssociadoId() ?? null) (143) e recarrega os vínculos (149). AJAX de cidades: fetch em SITE_URL + '/api/get_cidades.php?estado_id=' (383), com API real em api/get_cidades.php.
- **Riscos/gaps**:
  1. **Sem CSRF** (nenhum token no formulário 171-288; CSRFProtection não é requerido no arquivo; nenhuma validação). O POST de edição é aceito de qualquer origem autenticada.
  2. **substituirUnidades apaga todos os vínculos do fornecedor** antes de reinserir (classes/Fornecedor.php:244 — WHERE fornecedor_id = :fid, sem filtro por associado) e o ON DUPLICATE reescreve associado_id com o do usuário corrente (classes/Fornecedor.php:250, 260). Para um gestor, os vínculos de outros associados só sobrevivem se o formulário reenviar todas as linhas; linhas de unidades que não estão mais em $unidadesDisponiveis (ex.: agroindústria com excluido=1, que não aparece no optgroup 244-253) viram uid=0, são descartadas em 131 e o vínculo é permanentemente soft-deleted.
  3. **Corrupção de metadado associado_id**: mesmo para não-gestor, os vínculos "alheios" são reinjetados em $vincular (139-141) e regravados com associado_id = do usuário corrente (260) — perde-se a autoria original registrada no pivô.
  4. **Cidade pode ser apagada**: o select #cidade fica **disabled** até o JS carregar as cidades (219, 366-376, 424-426); campo disabled não é enviado no POST, então $_POST['cidade'] fica indefinido quando o fornecedor não tem cidade e o JS não habilita — warning + NULL (114). O mesmo não ocorre para estado (select ativo).
  5. Data exibida pode divergir do banco: array_merge($fornecedor, $_POST) (148) injeta o POST cru na variável de exibição, e o valor é escapado só na saída (htmlspecialchars 174-219) — aceitável, mas mascarado.
  6. **Indentação/aninhamento quebrados** em 118-150 (if($stmt->execute()){ colado à margem e blocos aninhados sem recuo) — alto risco de manutenção/merge.
  7. **Sem trilha de auditoria**: nenhuma chamada a AuditLog (diferente de módulos como agroindustria/ordens_cadastrar.php:179) — não se sabe quem alterou o quê.
  8. Escrita direta de SQL no arquivo (104-117) em vez de método da classe, burlando qualquer sanitização centralizada e duplicando a regra de negócio (Fornecedor::update não existe; a classe tem só create/getAll/getById/getVisiveisParaAssociado/getTodasUnidades/getUnidadesPorFornecedor/substituirUnidades).

### modulos/fornecedor/excluir.php

- **Rota/acesso**: **não há rota no roteador** — index.php:739-751 trata apenas fornecedor, fornecedor/novo e fornecedor/<id>/editar. Acesso somente por caminho físico direto: **SITE_URL/modulos/fornecedor/excluir.php?id=N** (o .htaccess raiz só reescreve quando o arquivo não existe; .htaccess:16-24). Padrão análogo em outros módulos, ex.: modulos/caderno_campo/lotes_producao/listar.php:73 aponta para /modulos/caderno_campo/lotes_producao/excluir.php.
- **Guard de sessão/permissão**: session_start() incondicional na linha 2 (antes de qualquer include). Guard 1 (3-12): se $_SESSION['user_tipo'] === 'usuario' encerra com modal de permissão negada. Guard 2 (17-21): **if (!$user->hasPermission(NIVEL_GESTOR))** — restrito a Admin/Gestor. includes em 13-15 (header antes da checagem de nível).
- **Propósito de negócio**: excluir fornecedor.
- **Campos/formulário**: nenhum (não há formulário; não há POST).
- **Regras/validações/cálculos**: valida id numérico em 23-27 (senão redirect para listar.php?msg=erro_id, 24) e converte com intval (28). Nada mais.
- **Tabelas SQL usadas**: **DELETE FROM fornecedores WHERE id = :id** (32-33, bind PDO::PARAM_INT) — **exclusão física**. Não remove nem faz soft delete em fornecedor_unidades (não há ON DELETE CASCADE declarado no baseline: migration.sql:874-888 não tem FOREIGN KEY), deixando vínculos órfãos na pivot.
- **Ações/POST/redirects**: apenas GET. Redireciona para listar.php?msg=excluido (35), listar.php?msg=erro_excluir (38, 42) e listar.php?msg=erro_id (24). **listar.php não lê $_GET['msg'] em nenhum ponto** — as mensagens nunca aparecem.
- **Riscos/gaps**:
  1. **Exclusão por GET sem CSRF** (23-40): qualquer imagem/link/pré-carregamento dispara a exclusão; um gestor logado é suficiente. Viola o padrão do próprio sistema, que em outros módulos exige POST + CSRF (ex.: modulos/caderno_campo/lotes_producao/excluir.php:20 e modulos/usuarios/excluir.php:13).
  2. **Contradição funcional**: listar.php:151-205 exibe modal dizendo que "a funcionalidade de exclusão de fornecedores foi temporariamente desabilitada", e listar.php:66 usa um botão sem ação de exclusão; porém o endpoint de exclusão física continua alcançável por URL direta. Não há segregação por status/inativação (não existe coluna excluido em fornecedores no banco real — confirmado via information_schema).
  3. **Perda de rastreabilidade**: DELETE físico sem AuditLog e sem verificação de dependências — insumos.fornecedor_id e rastreabilidade/relatórios passam a apontar para fornecedor inexistente (LEFT JOINs passam a NULL silenciosamente, ex.: classes/OrdemProducao.php:52, classes/Relatorio.php:153, classes/ReportConformidade.php:324/390, relatorios/form_insumos.php…).
  4. Evidência de uso real do endpoint: a tabela fornecedores tem ids 1,2,3,5,6,7,8 (id 4 ausente) — compatível com exclusão física já executada.
  5. session_start() na linha 2 sem checagem de session_status (config.php:88-103 já faz a checagem correta) — redundância que pode gerar notice de sessão já iniciada dependendo da ordem de inclusão.

### modulos/fornecedor/listar.php

- **Rota/acesso**: index.php:739-742 (**/fornecedor** -> modulos/fornecedor/listar.php). Guard: **if (!$user->isLoggedIn())** (8-12); comentário da linha 7 ("Apenas Admin ou Gestor podem ver a lista completa") **não corresponde ao código** — a segmentação real é por escopo de dados (16-23).
- **Propósito de negócio**: listar fornecedores; gestor vê todos, usuário comum vê apenas os vinculados às unidades que acessa (dono ou membro).
- **Campos/formulário**: nenhum formulário de entrada (apenas a tabela e o botão de advertência).
- **Regras/validações/cálculos**: seleção do conjunto (16-23): NIVEL_GESTOR -> $fornecedor_obj->getAll() (18); senão Fornecedor::getVisiveisParaAssociado($associado_id ?? -1) (21-22). Título/subtítulo condicionais (26-27). Para cada fornecedor, resolve unidades com getUnidadesPorFornecedor($forn['id']) (50) e monta badges com o mapa de rótulos ['propriedade'=>'Propriedade','agroindustria'=>'Agroindústria','animal'=>'Criação Animal'] (53); se não houver vínculo, exibe "Somente gestores" (61).
- **Tabelas SQL usadas**: leitura de **fornecedores** + **cidades** + **estados** (classes/Fornecedor.php:67-75 getAll; 155-194 getVisiveisParaAssociado, com JOIN em fornecedor_unidades e subconsultas em propriedades/propriedade_membros/agroindustrias/agroindustria_membros/ambiente_criacao_animal/ambiente_criacao_membros); **fornecedor_unidades** + UNION de propriedades/agroindustrias/ambiente_criacao_animal por linha da tabela (classes/Fornecedor.php:211-224).
- **Ações/POST/redirects**: nenhum POST. Ações de linha: botão Editar -> SITE_URL/fornecedor/<id>/editar (65); botão Excluir -> onclick mostrarAdvertenciaExclusao() (66, função 151-205), que apenas monta e exibe um modal Bootstrap via jQuery — **não há chamada ao endpoint de exclusão**.
- **Riscos/gaps**:
  1. **N+1 queries**: getUnidadesPorFornecedor dentro do foreach (49-69, chamada na linha 50) executa 1 query por fornecedor.
  2. **XSS armazenado potencial**: json_encode($fornecedores) impresso dentro de <script> (117) sem JSON_HEX_TAG/JSON_HEX_APOS, e os dados incluem nome e representante livres — um valor contendo </script> quebra o contexto e injeta script. (O restante da página usa htmlspecialchars, 54/58-63.)
  3. Comentário/UX inconsistente com o backend: o botão de exclusão é "desabilitado" na UI (66, CSS 84-112) mas o endpoint físico existe (modulos/fornecedor/excluir.php:32).
  4. Mensagens de retorno do excluir.php (?msg=...) nunca são tratadas (nenhuma leitura de $_GET['msg'] no arquivo).
  5. Dados sensíveis em console: console.log dos dados completos do fornecedor (118-122).
  6. Dependência de CDN para i18n do DataTables (133) — sem fallback offline.

## Síntese do grupo — modulos/fornecedor

**Fluxo ponta-a-ponta (estado real do código)**

1. Menu (includes/menu.php:172) -> **/fornecedor** (index.php:739-742) -> listar.php. Header.php:16-19 já garantiu login.
2. listar.php escolhe getAll() (gestor) ou getVisiveisParaAssociado() (comum) — 16-23 — e renderiza badges por unidade (50-55).
3. Botão "Adicionar Novo Fornecedor" (listar.php:32) -> **/fornecedor/novo** (index.php:743-746) -> cadastrar.php, que exibe o formulário com CSRF (153) e action **/fornecedor/cadastrar** (83) — URL que **não existe** no roteador (fallback 404.php, index.php:1175-1177). Mesmo que a rota existisse, o sucesso chamaria vincularUnidades() (70), método ausente -> fatal error. Portanto **o cadastro de fornecedor está funcionalmente quebrado de ponta a ponta**.
4. Botão Editar (listar.php:65) -> **/fornecedor/<id>/editar** (index.php:747-751) -> editar.php, com anti-IDOR por escopo de unidades (37-49), UPDATE literal em fornecedores (104) e rearranjo de vínculos via substituirUnidades (143), sem CSRF.
5. Exclusão: UI apenas informativa (listar.php:66, 151-205); o endpoint físico (excluir.php:32, DELETE por GET sem CSRF, restrito a NIVEL_GESTOR) só é alcançável por URL direta e não é roteado.
6. Fornecedores criados aparecem como opção de insumo (modulos/caderno_campo/insumos/cadastrar.php:39-44 e 183-190) e nos relatórios/rastreabilidade via insumos.fornecedor_id (classes/OrdemProducao.php:52, modulos/agroindustria/rastreabilidade.php:34).

**Tabelas consolidadas do grupo (com colunas efetivamente usadas)**

| Tabela | Colunas usadas | Onde |
|---|---|---|
| fornecedores | id, nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, data_cadastro (+abrangencia existe e nunca é lida/escrita por PHP) | Fornecedor.php:22-23, 67-75, 124-128; editar.php:104 |
| fornecedor_unidades | fornecedor_id, unidade_tipo ENUM('propriedade','agroindustria','animal'), unidade_id, associado_id, excluido | Fornecedor.php:161, 211-224, 244-250 |
| cidades / estados | cidades.id/nome, estados.id/nome/uf | Fornecedor.php:73-74, 126-127; Associado.php:199 |
| propriedades / propriedade_membros | id, propriedade, associado_id | Fornecedor.php:93-95, 166-171; Produto.php:167-173 |
| agroindustrias / agroindustria_membros | id, nome_fantasia, razao_social, associado_id, excluido | Fornecedor.php:98-102, 174-181; Produto.php:179-187 |
| ambiente_criacao_animal / ambiente_criacao_membros | id, nome_instalacao, associado_id, excluido | Fornecedor.php:105-109, 184-191; Produto.php:193-201 |

**Não encontrado no código**: campos/tabelas de categoria de fornecimento, certificações do fornecedor, CPF (só existe a coluna cnpj VARCHAR(18)), histórico de alterações do fornecedor, e qualquer validação de dígito verificador de CNPJ no fluxo de fornecedor (a validação existe apenas em Agroindustria::validarCnpj, classes/Agroindustria.php:280-306, e não é chamada por Fornecedor.php).

---

# Grupo 2 — classes

### classes/Fornecedor.php

- **Rota/acesso**: não é tela; instanciado por modulos/fornecedor/{cadastrar,listar,editar}.php (cadastrar.php:29, 50; listar.php:15; editar.php:26), modulos/caderno_campo/insumos/{cadastrar,editar}.php (cadastrar.php:38-44; editar.php:39), dev-utils/{backfill_fornecedor_unidades,migrate_fornecedor_unidades}.php.
- **Propósito de negócio**: persistência e consulta de fornecedores e do vínculo multi-tipo fornecedor <-> unidade produtiva (propriedade/agroindústria/criação animal), com escopo por associado dono ou membro.
- **Campos/formulário**: recebe array $dados com as chaves nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado (create, 30-40). Não há validação de presença/formatos.
- **Regras/validações/cálculos**:
  - create (18-59): Sanitizer::sanitizeArray($dados) (20) => strip_tags+trim por valor (classes/Sanitizer.php:15-21, 119-131); **status fixo 'ativo'** (27, 41); INSERT com data_cadastro = NOW() (22-23); retorna (int)lastInsertId() (44).
  - Tratamento de duplicidade (46-55): captura PDOException com getCode() == 23000 e devolve mensagens específicas se a mensagem do driver contiver "cnpj" (49-51) ou "email" (52-54); caso contrário devolve a mensagem crua do banco (57) — informação interna exposta ao chamador.
  - getVisiveisParaAssociado (152-202): retorna [] se $associado_id falsy (153); exige **f.status = 'ativo'** (164) e vínculo ativo na pivot (**fu.excluido = 0**, 161); resolve o escopo de forma derivada (não usa fornecedor_unidades.associado_id) em três ramos: propriedade dono OU membro (166-171), agroindústria dono OU membro com ag.excluido=0 (174-181), ambiente de criação dono OU membro com amb.excluido=0 (184-191). ORDER BY f.nome (193).
  - getTodasUnidades (89-115): concatena 3 SELECTs (propriedades sem filtro de exclusão — a tabela não tem coluna excluido; agroindustrias e ambiente_criacao_animal com excluido=0). Nome da agroindústria = COALESCE(NULLIF(nome_fantasia,''), NULLIF(razao_social,''), CONCAT('Agroindústria #', id)) (99). Verificado em runtime: 19 unidades.
  - getUnidadesPorFornecedor (209-232): LEFT JOIN com UNION ALL dos três tipos (215-221), COALESCE(nome, 'Unidade #'.id) (213), WHERE fu.fornecedor_id = :fid AND fu.excluido = 0 (222).
  - substituirUnidades (240-270): transação (242); **UPDATE fornecedor_unidades SET excluido = 1 WHERE fornecedor_id = :fid** (244) sem filtro de associado; INSERT ... ON DUPLICATE KEY UPDATE excluido = 0, associado_id = VALUES(associado_id) (248-250); whitelist de tipo com in_array(['propriedade','agroindustria','animal'], true) e uid > 0 (254); bindValue com PARAM_NULL quando associado_id é null (260); commit (263) / rollback em PDOException (266).
- **Tabelas SQL usadas**:
  - INSERT INTO fornecedores (nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, data_cadastro) VALUES (..., NOW()) — 22-23.
  - SELECT f.id, f.nome, f.representante, c.nome AS cidade_nome, e.uf AS estado_sigla FROM fornecedores f LEFT JOIN cidades c ON f.cidade = c.id LEFT JOIN estados e ON f.estado = e.id ORDER BY f.nome ASC — 67-75 (getAll; **sem filtro de status**).
  - SELECT p.id, 'propriedade' AS tipo, p.propriedade AS nome FROM propriedades p — 92-95.
  - SELECT ag.id, 'agroindustria' AS tipo, COALESCE(...) AS nome FROM agroindustrias ag WHERE ag.excluido = 0 — 98-102.
  - SELECT amb.id, 'animal' AS tipo, amb.nome_instalacao AS nome FROM ambiente_criacao_animal amb WHERE amb.excluido = 0 — 105-109.
  - SELECT f.*, c.nome AS cidade_nome, e.uf AS estado_sigla FROM fornecedores f LEFT JOIN cidades c ... WHERE f.id = :id — 124-128.
  - getVisiveisParaAssociado (155-194) — usa fornecedores, fornecedor_unidades, cidades, estados, propriedades, propriedade_membros, agroindustrias, agroindustria_membros, ambiente_criacao_animal, ambiente_criacao_membros.
  - getUnidadesPorFornecedor (211-224) — fornecedor_unidades + UNION ALL (propriedades | agroindustrias WHERE excluido=0 | ambiente_criacao_animal WHERE excluido=0).
  - UPDATE/INSERT fornecedor_unidades (244, 248-250).
- **Ações/POST/redirects**: nenhum; a classe apenas retorna valores. create devolve int (id) ou string (erro), contrato ambíguo que o chamador trata com is_numeric (cadastrar.php:53).
- **Riscos/gaps**:
  1. **vincularUnidades não existe** (confirmado por leitura integral e por method_exists em runtime) enquanto cadastrar.php:70 a invoca.
  2. **substituirUnidades destrói vínculos globais**: o UPDATE de 244 ignora o associado dono e o ON DUPLICATE reescreve associado_id (250) — vínculos de terceiros podem ser soft-deleted ou ter autoria sobrescrita (ver análise de editar.php:136-143).
  3. **Exclusão lógica x física**: a pivot tem excluido (soft delete), mas a tabela fornecedores **não tem coluna excluido** (confirmado no banco real) — a única exclusão possível é o DELETE físico de excluir.php:32, que deixa a pivot órfã e não é tratado por esta classe.
  4. **getAll() ignora status** (67-75): fornecedores 'inativo' apareceriam para gestores; porém nada no sistema escreve status='inativo' (não há update de status em nenhum arquivo do módulo) — o enum e o filtro de 164 são parcialmente mortos.
  5. **CNPJ sem validação** no fluxo inteiro (18-59 não chama validador algum); evidência em banco: cnpj '98765432000100' (id 1) e '45454654546541' (id 3), ambos reprovados por Agroindustria::validarCnpj (verificado em runtime).
  6. **UNIQUE(cnpj)/UNIQUE(email) + string vazia**: existe uma linha com cnpj = '' e email = '' (fornecedor id 2, verificado via HEX). Como create() grava a string vazia quando o campo é deixado em branco, o segundo cadastro sem CNPJ colide na UNIQUE e recebe a mensagem enganosa "Erro: O CNPJ informado já está cadastrado." (49-51).
  7. **Escopo por join derivado é correto, mas custoso**: getVisiveisParaAssociado usa 3 subconsultas EXISTS por linha (155-194) e é chamado em telas de listagem e de insumo (listar.php:22, insumos/cadastrar.php:43).
  8. Vazamento de detalhe de erro de banco no retorno de create (57).

### classes/OrdemProducao.php

- **Rota/acesso**: usada por modulos/agroindustria/ordens_cadastrar.php:19 (nova), ordens_listar.php:15, ordens_visualizar.php:15; rotas /agroindustria/ordens, /ordens/nova e /ordens/<id> (index.php:980-988).
- **Propósito de negócio**: ordens de produção da agroindústria com rastreabilidade de insumos, balanço de massas (RF11) e status de auditoria antifraude; trava de higienização prévia (RF08) é atribuída ao fluxo.
- **Campos/formulário (contrato de entrada)**: create($dados, $itens) lê $dados em receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida (default 'KG'), status_auditoria (calculado), percentual_organico, selo_autorizado, associado_id, observacoes, criado_por (114-127). Cada item usa lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida (136-144). O chamador monta tudo de $_POST (ordens_cadastrar.php:101-134) e valida obrigatórios em ordens_cadastrar.php:137-142.
- **Regras/validações/cálculos (literais)**:
  - calcularBalanco (64-94): totalEntrada = soma de (float)item['quantidade_utilizada'] (67-69). Se totalEntrada <= 0 -> status 'EM_ANALISE' com mensagem "Nenhum lote primário informado para o balanço." (72-80). Divergência: **divergenciaPct = round((abs(totalEntrada - quantidadeFinal) / totalEntrada) * 100, 2)** (82). **status = divergenciaPct > $toleranciaPct ? 'ALERTA_FRAUDE' : 'APROVADO'**, com tolerância padrão **10.0%** (83, 64). Mensagens 90-92.
  - create (101-153): chama calcularBalanco com $dados['quantidade_final_obtida'] (103); abre transação (105); INSERT em ordens_producao com 12 colunas (108-113); normalizações: higienizacao_previa_id -> null se vazio (116), trim(lote_processado_final) (117), unidade_medida default 'KG' (120), percentual_organico/selo/associado_id/observacoes/criado_por -> null se vazios (122-126); INSERT por item em rastreabilidade_insumos_processo (130-134, 135-145); commit (147) / rollback + rethrow (149-152).
  - getItensRastreabilidade (42-57): expõe nome_lote, nome_produto, nome_insumo, lote_insumo, tipo_insumo e **fornecedor / fornecedor_cnpj** (47, 52).
  - softDelete (155-159): UPDATE ordens_producao SET excluido = 1 WHERE id = ?.
- **Tabelas SQL usadas**:
  - getAll (15-24): SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_processamento DESC.
  - getById (32-37): SELECT o.*, r.nome_produto_final, r.selo_autorizado AS selo_receita ... WHERE o.id = ? AND o.excluido = 0.
  - getItensRastreabilidade (44-54): FROM rastreabilidade_insumos_processo ri LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id LEFT JOIN produtos p ON ri.produto_id = p.id LEFT JOIN insumos ins ON ri.insumo_id = ins.id LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id WHERE ri.ordem_producao_id = ?.
  - create: INSERT INTO ordens_producao (receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico, selo_autorizado, associado_id, observacoes, criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) — 108-113. INSERT INTO rastreabilidade_insumos_processo (ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida) VALUES (?,?,?,?,?,?,?) — 131-133.
  - softDelete: UPDATE ordens_producao SET excluido = 1 WHERE id = ? — 157.
- **Ações/POST/redirects**: a classe não redireciona. O chamador, em sucesso, registra AuditLog ('ORDEM_PRODUCAO_CADASTRADA') e redireciona para /agroindustria/ordens/<id>?criada=1 (ordens_cadastrar.php:179-181).
- **Riscos/gaps**:
  1. **Docblock ≠ implementação**: 96-100 afirma "Bloqueia se não houver higienização válida nas últimas 24h (RF08)", mas **não há nenhuma verificação de higienização dentro de create()** — a classe não referencia ControleHigienizacao. O bloqueio vive no chamador (ordens_cadastrar.php:149-170). Qualquer outro chamador futuro grava OP sem RF08.
  2. **Balanço de massas soma unidades heterogêneas**: 67-69 soma quantidade_utilizada sem converter KG/L/UN; e não considera água/rendimento — a divergência de 10% pode ser estrutural.
  3. **Selo e percentual dependem do chamador**: create() aceita percentual_organico e selo_autorizado vindos de $dados (122-123); ordens_cadastrar.php:175-176 copia da receita. Não há recálculo nem validação de coerência com os insumos da OP.
  4. **Sem validação de posse (IDOR de escrita)**: create() não verifica se receita_id pertence ao associado/agroindústria do usuário nem se associado_id do POST é o do usuário; ordens_cadastrar.php:108 aceita **$_POST['associado_id']** arbitrário. Um usuário comum pode atribuir a OP a outro associado.
  5. **getItensRastreabilidade não filtra o.excluido** (não faz JOIN com ordens_producao) — retorna itens de ordens excluídas se chamado por id.
  6. **Sem auditoria de exclusão**: softDelete (155-159) não registra AuditLog nem verifica dependentes (a tabela rastreabilidade_insumos_processo não é tocada).
  7. **lote_primario_id sempre NULL na prática**: o chamador grava null (ordens_cadastrar.php:128), então o LEFT JOIN de 49 e os campos nome_lote nunca resolvem — a rastreabilidade "do lote primário" não é preenchida apesar do schema permitir.

### classes/ReceitaAgroindustria.php

- **Rota/acesso**: modulos/agroindustria/receitas_{listar,cadastrar,editar}.php (linhas 15, 16, 17) e ordens_cadastrar.php:20; rotas /agroindustria/receitas, /receitas/nova, /receitas/<id>/editar (index.php:965-973).
- **Propósito de negócio**: receitas de produto final da agroindústria com ingredientes, cálculo de percentual orgânico e classificação de selo (Portaria 52/2021).
- **Campos/formulário (contrato de entrada)**: create/update recebem $dados com nome_produto_final, descricao, associado_id, status, criado_por (114-120, 147-153); $ingredientes com nome_ingrediente, eh_organico, quantidade, unidade_medida, observacoes, proporcao_porcentagem (70, 175-189). Origem dos campos no formulário: modulos/agroindustria/receitas_cadastrar.php:103 (nome_produto_final), 107 (associado_id), 118 (status), 126 (descricao) e arrays ing_nome[], ing_organico[], ing_quantidade[], ing_unidade[], ing_obs[] (29-33).
- **Regras/validações/cálculos (literais)**:
  - fatorConversao (47-59): kg=1000; mg=0.001; t=1000000; l=1000; ml=1; un/und=1; default=1.
  - calcularPercentualOrganico (65-81): **qtd = quantidade ?? proporcao_porcentagem ?? 0** (70); **peso = qtd * fatorConversao(unidade_medida)** (71); acumula somaPeso e, se !empty(eh_organico), somaOrganico (72-75); se somaPeso <= 0 retorna 0.0 (77-79); senão **round((somaOrganico / somaPeso) * 100, 2)** (80).
  - classificarSelo (89-99): **>= 95.0 -> 'PRODUTO_ORGANICO_BRASIL'**; **>= 70.0 -> 'INGREDIENTES_ORGANICOS'**; senão **'SEM_SELO'** (justificado no docblock 83-88 como Portaria 52/2021).
  - create (101-131): calcula percentual e selo (103-104); transação (106); INSERT com status default 'Ativa' (119) — **divergente do default do banco 'Rascunho'** (migration.sql:1443); salva ingredientes (124); commit (125).
  - update (133-166): recalcula (135-136); UPDATE (141-144); **DELETE FROM ingredientes_receita WHERE receita_id = ?** e reinsere (156-158) — estratégia destrutiva dentro da transação.
  - salvarIngredientes (168-192): trim/normaliza (175-177); **peso = quantidade * fatorConversao(unidade)** (178); descarta ingrediente com nome vazio OU peso <= 0 (179-181); insere 7 colunas (182-190) — **o valor gravado na coluna proporcao_porcentagem é o peso convertido** (188), apesar do nome/comment da coluna sugerir percentual; eh_organico normalizado para 1/0 (185).
- **Tabelas SQL usadas**:
  - getAll (15-18): SELECT r.*, (SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes FROM receitas_agroindustria r WHERE r.excluido = 0 [AND r.associado_id = ?] ORDER BY r.criado_em DESC.
  - getById (32): SELECT * FROM receitas_agroindustria WHERE id = ? AND excluido = 0.
  - getIngredientes (39): SELECT * FROM ingredientes_receita WHERE receita_id = ? ORDER BY id ASC.
  - create (109-111): INSERT INTO receitas_agroindustria (nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por) VALUES (?,?,?,?,?,?,?).
  - update (141-144): UPDATE receitas_agroindustria SET nome_produto_final=?, descricao=?, percentual_organico_calculado=?, selo_autorizado=?, associado_id=?, status=? WHERE id=?.
  - salvarIngredientes (171-172): INSERT INTO ingredientes_receita (receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem, observacoes) VALUES (?,?,?,?,?,?,?).
  - softDelete (196): UPDATE receitas_agroindustria SET excluido = 1 WHERE id = ?.
- **Ações/POST/redirects**: classe não redireciona; o chamador registra AuditLog 'RECEITA_CADASTRADA' e redireciona para /agroindustria/receitas?cadastrada=1 (receitas_cadastrar.php:61-62).
- **Riscos/gaps**:
  1. **Sem validação de posse (IDOR de escrita)**: update($id, ...) (133) e softDelete (194) não verificam se a receita pertence ao associado; getById (30-35) idem. Também não há validação de que ingredientes/quantidades vieram de um conjunto autorizado.
  2. **Vínculo com insumos/fornecedores inexistente**: ingredientes_receita guarda apenas nome livre (migration.sql:931-943); não há insumo_id/fornecedor_id. Logo a cadeia fornecedor -> insumo -> receita é textual e não auditável.
  3. **DELETE + INSERT de ingredientes em update** (156-158) apaga histórico/ids — qualquer referência externa a ingredientes_receita.id quebra.
  4. **Semântica enganosa da coluna proporcao_porcentagem**: recebe peso em g/mL (188), enquanto calcularPercentualOrganico lê primeiro 'quantidade' (70) — se um registro vier apenas com proporcao_porcentagem preenchida, o valor será interpretado como quantidade bruta e multiplicado pelo fator (ex.: 50 (peso g) * 1000 = 50000), distorcendo o cálculo.
  5. **Status default divergente** (código 'Ativa' em 119; banco 'Rascunho' em migration.sql:1443) — registros criados pela API/outros fluxos ficam Rascunho, pela tela ficam Ativa.
  6. Ingredientes com peso 0 ou nome vazio são silenciosamente descartados (179-181), sem avisar o usuário; o percentual é calculado sobre os ingredientes de entrada (103) **antes** do descarte, portanto percentual e ingredientes efetivamente gravados podem divergir.
  7. Sem AuditLog dentro da classe e sem controle de concorrência (dois updates simultâneos perdem ingredientes).

### classes/ControleHigienizacao.php

- **Rota/acesso**: modulos/agroindustria/higienizacao_cadastrar.php:15, higienizacao_listar.php:15, ordens_cadastrar.php:21, ordens_visualizar.php (via OrdemProducao); rotas /agroindustria/higienizacao e /higienizacao/nova (index.php:974-979).
- **Propósito de negócio**: registrar a higienização de equipamentos/linhas (RF08) e fornecer a lista de sanitizantes permitidos em produção orgânica; servir de pré-requisito para a ordem de produção.
- **Campos/formulário (contrato de entrada)**: create($dados) lê data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por (76-85). Formulário em higienizacao_cadastrar.php:67/71/75/79/94/98 com os mesmos names; obrigatoriedade validada no chamador (higienizacao_cadastrar.php:34-36) e o sanitizante contra a lista permitida (37-39).
- **Regras/validações/cálculos**:
  - haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24) (38-50): COUNT(*) em controle_higienizacao com **excluido = 0**, **data_hora_fim IS NOT NULL**, **equipamento_linha = ?**, **data_hora_fim <= ?**, **data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)** (41-47); bind de [equipamentoLinha, dataProcessamento, dataProcessamento, intervaloHoras] (48); retorna fetchColumn() > 0 (49).
  - **$associadoId é aceito mas nunca usado na query** (assinatura 38 vs SQL 41-48) — verificado em runtime: haHigienizacaoValida(fim, 'Linha de Preparo', 999999, 24) retornou **true** para uma higienização cujo associado_id real é 38.
  - listarSanitizantesPermitidos (58-67): SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome ASC. Verificado em runtime: 29 registros (primeiro "Ácido acético").
  - create (69-87): INSERT direto, sem validação; data_hora_fim -> null se vazia (78); associado_id/criado_por -> null se vazios (82, 84); retorna lastInsertId (86).
  - softDelete (89-93): UPDATE controle_higienizacao SET excluido = 1 WHERE id = ?.
- **Tabelas SQL usadas**: controle_higienizacao (SELECT * em 15/29; COUNT em 41-47; INSERT em 72-74; UPDATE em 91) e insumos_organicos_aprovados (60-64).
- **Ações/POST/redirects**: não redireciona; o chamador grava AuditLog 'HIGIENIZACAO_REGISTRADA' e redireciona para /agroindustria/higienizacao?registrada=1 (higienizacao_cadastrar.php:42-43).
- **Riscos/gaps**:
  1. **Isolamento por associado ausente em haHigienizacaoValida** (38-50): higienização de qualquer produtor valida a OP de outro — falha de integridade/conformidade (comprovada em runtime).
  2. **Sem validação de sanitizante na classe**: create (69-87) não checa listarSanitizantesPermitidos; a checagem é só no chamador (higienizacao_cadastrar.php:37-39) — qualquer outro caminho grava sanitizante proibido.
  3. **Coerência temporal não validada**: não há exigência de data_hora_fim > data_hora_inicio, nem proibição de data futura; higienizacao_cadastrar.php:34 só exige não-vazio no início.
  4. **Sem validação de posse (IDOR)**: getById (27-32) e softDelete (89-93) não checam associado; não há update de higienização (correção exige novo registro).
  5. **Acoplamento frágil com a OP**: ordens_cadastrar.php:149 chama haHigienizacaoValida(..., 'Qualquer linha', ...) — valor que **nunca existe** em equipamento_linha (verificado em runtime: false para todos os registros), tornando o ramo 149-153 código morto e deslocando a validação efetiva para o bloco 154-170 (janela calculada em PHP: proc - fim > 24*3600 || fim > proc).
  6. Sem índice em equipamento_linha nem em data_hora_fim (migration.sql:655-656 só declara PRIMARY KEY) — COUNT de 41-47 faz varredura.

### classes/LoteProducao.php

- **Rota/acesso**: modulos/caderno_campo/lotes_producao/{listar,cadastrar,editar,rastrear}.php (linhas 15, 29, 25, 19) e index.php:369-372/388-391 (resolução de UUID para ID). Rotas /lotes, /lote/novo, /lote/<id|uuid>[/editar] e /caderno-campo/lote/... (index.php:335-402).
- **Propósito de negócio**: lotes de produção do caderno de campo (produção vegetal/agroindústria): identifica o lote por UUID, amarra produto, data, quantidade e fornece rastreabilidade de atividades e insumos usados na lavoura do produto.
- **Campos/formulário (contrato de entrada)**: create($dados) usa uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes (36-43). Formulário: modulos/caderno_campo/lotes_producao/cadastrar.php:64 (nome_lote, required), 68 (produto_id, required), 78 (data_producao, required), 82 (quantidade, required number step 0.01), 86 (unidade_medida), 91 (observacoes). update($id,$dados) usa nome_lote, produto_id, data_producao, quantidade, unidade_medida, observacoes (186-194) — **não altera associado_id**.
- **Regras/validações/cálculos**:
  - create (17-50): Sanitizer::sanitizeArray (19); **geração de UUID quando ausente com mt_rand e máscara de versão 4** sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', ... | 0x4000, ... | 0x8000, ...) (22-30); INSERT com criado_em = NOW() (32-33); associado_id só recebe int se não vazio (39). **Nenhuma validação de campos obrigatórios, formato de data, quantidade > 0 ou propriedade do produto_id.**
  - getByAssociadoId (77-95): filtra excluido = 0 AND (lp.associado_id = :a OR (lp.associado_id IS NULL AND p.associado_id = :a)), resolvendo o dono via produtos -> lavouras -> propriedades (81-83).
  - getAtividadesByLoteId (136-152): JOIN produtos p ON lp.produto_id = p.id JOIN atividades_diarias ad ON p.lavoura_id = ad.lavoura_id WHERE lp.id = ? AND ad.excluido = 0, retornando nome, data_atividade, tipo_atividade, descricao, hora_inicio, hora_fim, status (138-143).
  - getInsumosByLoteId (159-175): DISTINCT ad.insumo_utilisado, ad.data_atividade, ad.tipo_atividade, com filtro insumo_utilisado IS NOT NULL E != '' (161-166) — a coluna tem o typo "insumo_utilisado" no schema (migration.sql:401).
  - delete (207-216): UPDATE lotes_producao SET excluido = 1 (soft delete).
- **Tabelas SQL usadas**:
  - create (32-33): INSERT INTO lotes_producao (uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, criado_em) VALUES (..., NOW()).
  - getAll (58-62): SELECT lp.id, lp.uuid, lp.nome_lote, lp.data_producao, lp.quantidade, lp.unidade_medida, p.nome AS produto_nome FROM lotes_producao lp LEFT JOIN produtos p ON lp.produto_id = p.id WHERE lp.excluido = 0 ORDER BY lp.data_producao DESC — **sem escopo por associado**.
  - getByAssociadoId (79-86): + LEFT JOIN lavouras l ON pr.lavoura_id = l.id LEFT JOIN propriedades p ON l.propriedade_id = p.id.
  - getByUuid (104) / getById (121): SELECT * FROM lotes_producao WHERE (uuid|id) = ... AND excluido = 0.
  - getAtividadesByLoteId (138-143) e getInsumosByLoteId (161-166): lotes_producao + produtos + atividades_diarias.
  - update (186): UPDATE lotes_producao SET nome_lote=?, produto_id=?, data_producao=?, quantidade=?, unidade_medida=?, observacoes=? WHERE id = ?.
  - delete (209): UPDATE lotes_producao SET excluido = 1.
- **Ações/POST/redirects**: a classe não redireciona. O chamador cadastrar.php:41-47 apenas exibe mensagem (não há redirect); a exclusão é feita por outro arquivo (modulos/caderno_campo/lotes_producao/excluir.php:29-31, POST + CSRF conforme listar.php:73).
- **Riscos/gaps (parte produção/estoque)**:
  1. **Nenhum tratamento de estoque**: não há ocorrência da palavra "estoque" em LoteProducao.php (verificado por grep). As tabelas de movimentação **lote_insumo** (migration.sql:1030) e **lote_atividade** (migration.sql:1020) não são usadas por esta classe — lote_insumo aparece apenas em api/relatorios/movimentacoes.php:40. As colunas **lotes_producao.atividades_id** e **lotes_producao.insumos_id** (migration.sql:1054-1055) nunca são preenchidas por create/update. O "estoque" do sistema é tratado em insumos (insumos.estoq_anterior, quant_recebida, quantidade_estoque — migration.sql:953-955), sem baixa automática a partir do lote/produção.
  2. **Sem checagem de posse em create/update**: produto_id vem do POST sem validação de que pertence ao associado (create 38; update 189) — IDOR de escrita; update e delete (183, 207) também não verificam dono (o anti-IDOR existe apenas no consumidor modulos/caderno_campo/lotes_producao/rastrear.php:28).
  3. **getAll() sem escopo por associado** (56-70) — se usado em tela de usuário comum, expõe lotes de terceiros; os fluxos atuais usam getByAssociadoId (verificar cada chamador).
  4. **UUID com mt_rand** (23-29): não é CSPRNG; a coluna tem UNIQUE (migration.sql:1058) e o UUID é usado como identificador de URL (index.php:367-375), portanto previsibilidade/colisão são relevantes.
  5. **Sem validação de campos obrigatórios** (nome_lote, produto_id, data_producao, quantidade são NOT NULL no banco): falha vira PDOException (capturada em 46-49) retornando false — o usuário vê apenas "Erro ao cadastrar lote de produção." (cadastrar.php:45).
  6. **Dupla codificação HTML**: o chamador aplica htmlspecialchars(strip_tags(trim(...))) a todo o POST (cadastrar.php:33) e a classe aplica strip_tags novamente (19) — entidades HTML (&amp;, &lt;) ficam gravadas literalmente no banco quando o usuário digita tais caracteres.
  7. **getAtividadesByLoteId/getInsumosByLoteId ignoram ad.lote_id**: existe a coluna atividades_diarias.lote_id (migration.sql:406), mas a classe liga atividade ao lote por produto -> lavoura (141, 164), misturando atividades de todos os lotes da mesma lavoura.
  8. **Estoque/status divergentes do runtime**: a coluna lotes_producao.status_lote referenciada em api/relatorios/visao-geral.php:30 **não existe** no banco real (confirmado por information_schema no banco sisagro) — consultas de relatório desse tipo falham; e o banco real tem lotes_producao.atualizado_em, ausente do baseline.
  9. Sem AuditLog em create/update/delete da classe (só no excluir.php do módulo, linha 31).

### classes/Agroindustria.php

- **Rota/acesso**: modulos/agroindustria/{minhas_listar:15, minhas_cadastrar:40, minhas_editar:21, setores_cadastrar:24, setores_editar:29, ordens_cadastrar:69}; rotas /agroindustria/minhas, /minha/nova, /minha/<id>/editar, /setores, /setor/... (index.php:920-937).
- **Propósito de negócio**: CRUD da agroindústria, gestão de membros (compartilhamento entre associados) e resolução de escopo (quais agroindústrias/associados o usuário enxerga). **Dashboard/relatórios: não encontrado no código nesta classe** — não há método de agregação, indicador, série temporal ou relatório; a classe expõe apenas getAll (17-33), getById (35-40), countByAssociado (42-47), getMembros (53-65), getAgroindustriasPorAssociado (71-88), getAssociadosDaMesmaAgroindustria (95-112), adicionarMembro (118-128), removerMembro (134-142), create (149-199), update (201-244), softDelete (246-250), marcarAssociadoComAgroindustria (252-263), soDigitos (265-268), formatarCnpj (270-278), validarCnpj (280-306). O dashboard do perfil usa User::temAgroindustria (modulos/dashboard/dashboard_agroindustria.php:10; classes/User.php:177-200), não esta classe; nenhum relatório em relatorios/ referencia a classe (grep).
- **Campos/formulário (contrato de entrada)**: create/update usam associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes, criado_por (171-189, 218-234). adicionarMembro/removerMembro usam (agroindustriaId, associadoId, criadoPor).
- **Regras/validações/cálculos**:
  - create (149-199): Sanitizer::sanitizeArray (151); CNPJ normalizado para dígitos (153) e **validado com validarCnpj** (154-156); **associado_id obrigatório > 0** (158-161); INSERT com 17 colunas (164-170); CNPJ formatado antes de gravar (175); status normalizado para 'Ativa'/'Inativa' (186); marca associados.tem_agroindustria = 1 (192, 252-263).
  - update (201-244): mesma validação de CNPJ (205-208); UPDATE de 15 colunas WHERE id = ? AND excluido = 0 (211-216); chama marcarAssociadoComAgroindustria((int)($dados['associado_id'] ?? 0)) na linha 237 — porém **associado_id não está no SET** (212-215), logo a chamada só funciona se o POST trouxer associado_id, sem alterar o dono da agroindústria (inconsistência).
  - getAssociadosDaMesmaAgroindustria (95-112): obtém os ids via getAgroindustriasPorAssociado (100), monta **IN ($in) com implode(',', array_map('intval', $agros))** (104-107) — interpolação direta mas neutralizada por intval; se não houver agroindústria retorna [(int)$associadoId] (101-103); sempre anexa o próprio associado e deduplica (110-111).
  - getMembros (53-65): JOIN associados com m.excluido = 0 AND a.status = 'ativo' (56-60).
  - adicionarMembro (118-128): **INSERT IGNORE** (121) e retorno **$stmt->rowCount() > 0 || true** (123) — sempre true, inclusive em erro de duplicidade; falha real só em PDOException (124-127).
  - removerMembro (134-142): UPDATE ... SET excluido = 1 com **AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0)** (137-140) — protege o proprietário.
  - validarCnpj (280-306): exige 14 dígitos (283-285); rejeita repetição /^(\d)\1+$/ (286-288); pesos [5,4,3,2,9,8,7,6,5,4,3,2] para 12 e [6,5,4,3,2,9,8,7,6,5,4,3,2] para 13 (291-293); soma % 11, dígito = resto < 2 ? 0 : 11 - resto (294-299); confere cnpj[12] com calc(12) (302-304) e cnpj[13] com calc(13) (305).
  - formatarCnpj (270-278) e soDigitos (265-268) — normalização/padronização de CNPJ.
- **Tabelas SQL usadas**: **agroindustrias** (19-23, 37, 44, 78-84, 139, 164-170, 211-217, 248), **associados** (20, 56-58, 258), **agroindustria_membros** (56-59, 81-83, 106-107, 121, 137-140).
- **Ações/POST/redirects**: a classe não redireciona. O chamador minhas_cadastrar.php:41 usa o retorno (int = id ou string = erro) e minhas_editar.php:76/51/63 faz o mesmo para membros; minhas_listar.php:22-24 faz getById + softDelete.
- **Riscos/gaps**:
  1. **Validação de CNPJ não é aplicada aos fornecedores** (esta é a única validação de documento do sistema, e Fornecedor.php não a utiliza) — inconsistência de política de dados.
  2. **adicionarMembro sempre retorna true** (123) — o chamador (minhas_editar.php:51) não consegue distinguir sucesso de falha lógica.
  3. **Escopo de membros sem validação de posse**: adicionarMembro/removerMembro (118-142) não verificam se o solicitante administra a agroindústria; a checagem fica no chamador (minhas_editar.php:45-66).
  4. **Interpolação de IN** em 104-107 (mitigada por intval) e em pontos análogos de outros módulos — padrão de risco a manter sob controle.
  5. **update não altera associado_id** (212-215) mas chama marcarAssociadoComAgroindustria com o valor do POST (237): permite marcar outro associado como tem_agroindustria = 1 sem transferir a titularidade.
  6. **Soft delete sem verificação de dependências** (246-250): agroindústrias excluídas continuam referenciadas por fornecedor_unidades (nome cai para "Unidade #id" em Fornecedor.php:218 e o vínculo é perdido no próximo salvamento), por produtos (produtos.unidade_tipo/unidade_id, migration.sql:1365-1366) e por receitas/ordens (associado_id).
  7. **Sem AuditLog** na classe (nem em create/update/softDelete/membros).
  8. **Dashboard/relatórios: não encontrado no código** — o módulo de dashboard (dashboard_agroindustria.php) não consulta esta classe; não existem métodos de contagem/agregação além de countByAssociado (42-47).

## Síntese do grupo — classes

**Fluxos ponta-a-ponta**

1. **Fornecedor -> insumo -> ordem -> rastreabilidade**
   - Cadastro do fornecedor (quebrado, ver Grupo 1) grava em fornecedores; o vínculo com unidades vai para fornecedor_unidades (Fornecedor.php:248-250).
   - O insumo referencia o fornecedor por **insumos.fornecedor_id** (Insumo.php:119/126 e 173/188), escolhido na tela entre os fornecedores visíveis (modulos/caderno_campo/insumos/cadastrar.php:38-44; 183-190).
   - A ordem de produção da agroindústria (OrdemProducao::create, 101-153) grava o cabeçalho em ordens_producao e cada insumo utilizado em rastreabilidade_insumos_processo (insumo_id + numero_nf + quantidade_utilizada), calculando o status de auditoria em calcularBalanco (64-94, tolerância 10%).
   - A rastreabilidade do fornecedor aparece por join indireto: **insumos.fornecedor_id -> fornecedores** (OrdemProducao.php:52; modulos/agroindustria/rastreabilidade.php:34; classes/ReportConformidade.php:324/390; classes/Relatorio.php:153).
   - **Elo ausente**: ingredientes_receita não referencia insumo nem fornecedor (migration.sql:931-943) — a cadeia fornecedor -> insumo -> receita é textual, sem integridade referencial.
   - **Elo sem escopo**: nenhum dos joins de rastreabilidade filtra o fornecedor pelas unidades visíveis do associado; a visibilidade do fornecedor existe apenas nas telas de listagem/seleção.

2. **Higienização -> Ordem de produção (RF08)**
   - controle_higienizacao criado por higienizacao_cadastrar.php (validação do sanitizante contra insumos_organicos_aprovados categoria='Higienização' permitido=1, 29 registros reais).
   - A OP só é criada depois de passar pelo bloco de ordens_cadastrar.php:149-170, que usa haHigienizacaoValida com o literal 'Qualquer linha' (nunca encontrado, logo sempre false) e depois revalida a janela de 24h em PHP; o parâmetro $associadoId de haHigienizacaoValida é ignorado (ControleHigienizacao.php:38-50) — **higienização de outro associado satisfaz a trava** (comprovado em runtime).
   - O docblock de OrdemProducao::create (96-100) promete a trava dentro da classe, mas ela não existe lá.

3. **Receita -> selo orgânico -> OP**
   - ReceitaAgroindustria::create/update calculam percentual_organico_calculado e selo_autorizado (89-99: >=95% PRODUTO_ORGANICO_BRASIL, >=70% INGREDIENTES_ORGANICOS, senão SEM_SELO) a partir dos pesos convertidos por fatorConversao (47-59).
   - A OP copia esses dois valores da receita (ordens_cadastrar.php:175-176) e os grava em ordens_producao.percentual_organico/selo_autorizado — sem recálculo a partir dos insumos efetivamente usados.

4. **Lote -> produção/estoque**
   - lotes_producao é criado/atualizado por LoteProducao (create 17-50, update 183-200) com UUID aleatório (mt_rand), produto, data e quantidade; o consumo de insumos é derivado das atividades_diarias da lavoura do produto (136-175).
   - **Não há baixa/entrada de estoque**: as tabelas lote_insumo/lote_atividade e as colunas lotes_producao.atividades_id/insumos_id não são usadas pela classe; o estoque permanece em insumos.quantidade_estoque (migration.sql:955), mantido manualmente.

5. **Escopo/permissão (denominador comum)**
   - Níveis: NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3 (config.php:23-25); semântica nivel <= required, admin sempre true (User.php:114-141).
   - Fornecedor: leitura escopada por unidade (Fornecedor.php:152-202), escrita de cadastro liberada a qualquer logado (cadastrar.php:18) e de edição liberada com anti-IDOR (editar.php:37-49); exclusão só Gestor (excluir.php:17) — porém por GET sem CSRF.
   - Agroindústria/Receita/Higienização/Ordem/Lote: **nenhuma checagem de posse nas classes**; dependem integralmente de validação nos chamadores, que em vários casos também não existe (OrdemProducao associa associado_id do POST; Receita update/softDelete e Lote create/update/delete sem dono).
   - CSRF: o módulo fornecedor imprime token mas não valida (cadastrar.php:153) e editar.php/excluir.php não têm token; nos módulos agroindustria o padrão correto existe (ex.: minhas_cadastrar.php:24, higienizacao_* e ordens_* são a exceção).

**Tabelas consolidadas do grupo**

| Tabela | Colunas relevantes (uso no código) | Chaves/índices confirmados |
|---|---|---|
| fornecedores | id, nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, abrangencia, data_cadastro (**sem coluna excluido**) | PK id; UNIQUE cnpj, UNIQUE email (verificado no banco) |
| fornecedor_unidades | fornecedor_id, unidade_tipo, unidade_id, associado_id, excluido, criado_por, criado_em | UNIQUE (fornecedor_id, unidade_tipo, unidade_id); idx associado_id e (unidade_tipo, unidade_id) |
| insumos | id, nome, lote, tipo_insumo, fornecedor_id, quantidade_estoque, estoq_anterior, quant_recebida, certificacao_insumo, insumo_aprovado_id, excluido | UNIQUE uuid; idx aprovado_organico/tipo_insumo |
| insumos_organicos_aprovados | categoria (enum com 'Higienização'), permitido, nome, subcategoria, restricoes, normativa | 250 linhas; 29 com categoria='Higienização' e permitido=1 |
| receitas_agroindustria | nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, excluido, criado_por | status default 'Rascunho' no banco |
| ingredientes_receita | receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem (recebe peso convertido), observacoes | sem FK com insumos/fornecedores |
| ordens_producao | receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria (APROVADO/ALERTA_FRAUDE/EM_ANALISE), percentual_organico, selo_autorizado, associado_id, excluido, criado_por | idx fk_op_higienizacao, idx_op_receita |
| rastreabilidade_insumos_processo | ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida | idx_rast_op; sem FK declarada |
| controle_higienizacao | data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, excluido, criado_por | só PK (sem índice em equipamento_linha/data_hora_fim) |
| lotes_producao | uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, atividades_id, insumos_id, excluido (+atualizado_em no banco real) | UNIQUE uuid (idx_lotes_uuid) |
| agroindustrias | associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes, excluido | idx associado_id, idx status |
| agroindustria_membros | agroindustria_id, associado_id, excluido, criado_por | núcleo do compartilhamento |
| produtos / lavouras / propriedades | produtos.lavoura_id, produtos.associado_id, produtos.unidade_tipo/unidade_id; lavouras.propriedade_id; propriedades.associado_id (sem excluido) | usados na resolução de lote -> dono |
| atividades_diarias | lavoura_id, lote_id, insumo_utilisado (typo do schema), data_atividade, tipo_atividade, status, excluido | insumo_utilisado é o único vínculo "insumo" do caderno de campo |

**Bugs/riscos críticos consolidados (prioridade de correção)**

1. cadastrar.php:70 chama Fornecedor::vincularUnidades(), método inexistente (fatal error garantido no caminho de sucesso).
2. cadastrar.php:83/115 usa action /fornecedor/cadastrar, rota inexistente no roteador (index.php:739-751) -> 404.
3. Sem validação de CSRF no módulo fornecedor (cadastrar.php só imprime o token; editar.php e excluir.php não têm token).
4. excluir.php:32 faz DELETE físico por GET sem CSRF e sem limpar fornecedor_unidades; UI diz que a exclusão está desabilitada.
5. Fornecedor::substituirUnidades (244-250) soft-deleta vínculos de todos os associados e reescreve associado_id com o do editor.
6. Campo cidade ausente no cadastro (Fornecedor.php:39 x cadastrar.php:90-111) e select cidade disabled no editar (219) pode NULL-ar o valor.
7. Cidade/estado sem FK formal (fornecedores.cidade -> cidades.id, fornecedores.estado -> estados.id) no baseline — integridade só por convenção.
8. Nenhuma validação de CNPJ no fluxo de fornecedor (dados inválidos já gravados: '98765432000100', '45454654546541') e UNIQUE(cnpj) com string vazia gera mensagem enganosa no segundo cadastro sem documento.
9. ControleHigienizacao::haHigienizacaoValida ignora $associadoId (38-50) — cross-associado; 'Qualquer linha' em ordens_cadastrar.php:149 nunca casa.
10. OrdemProducao::create não valida posse de receita/associado e seu docblock promete um bloqueio RF08 que não implementa.
11. ReceitaAgroindustria::update/softDelete, LoteProducao::create/update/delete e ControleHigienizacao::getById/softDelete sem verificação de dono (IDOR de escrita).
12. LoteProducao não movimenta estoque algum; lote_insumo/lote_atividade e lotes_producao.atividades_id/insumos_id não são usados; api/relatorios/visao-geral.php:30 referencia coluna inexistente (status_lote).
13. XSS armazenado potencial em listar.php:117 (json_encode em <script> sem HEX_TAG) e em cadastrar.php:270 (nome de unidade concatenado em HTML via JS).
14. Agroindustria::adicionarMembro sempre retorna true (123); update não altera associado_id mas marca tem_agroindustria do associado do POST (237).
15. Sem trilha de auditoria em todo o módulo fornecedor e nas classes analisadas (contra o padrão AuditLog usado em agroindustria/ordens_cadastrar.php:179 e higienizacao_cadastrar.php:42).
16. Duplicação de SQL de fornecedores na tela (editar.php:104-117) sem método correspondente na classe — regra de negócio em dois lugares.
