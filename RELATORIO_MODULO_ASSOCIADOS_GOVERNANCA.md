# Relatório Técnico — Módulo ASSOCIADOS / ASSOCIAÇÕES / USUÁRIOS / GOVERNANÇA (SINPORG)

Workspace: C:/xampp/htdocs/app  •  Banco: **sisagro** (MySQL/MariaDB)  •  PHP 8.x  •  Router único em **index.php** via **.htaccess** (RewriteRule ^(.*)$ index.php?url=$1 [QSA,L] — .htaccess:24).

> Método: leitura integral dos arquivos listados (contagem do campo totalLines do leitor). Toda afirmação tem citação arquivo:linha. Nada foi inventado.

---

## 1. Sumário executivo (achados críticos)

| # | Severidade | Achado | Evidência |
|---|-----------|--------|-----------|
| 1 | CRÍTICO | **As telas de carteirinha estão quebradas ou inalcançáveis.** O router decodifica o id com decodeId() (index.php:644,649,654,659), mas os links reais enviam o id numérico cru (carteirinha_listar.php:135,137,180; modulos/portal/area.php:252,260). decodeId('5') devolve 0 (config/config.php:68-81) → ApiAuth::negar(400) (carteirinha_pdf.php:19-21; carteirinha_impressa.php:17-19). O único arquivo que usa encodeId() corretamente (ficha.php:75-85) **não tem rota**. |
| 2 | CRÍTICO | **modulos/associados/ficha.php é órfã** (sem rota em index.php) e é destino de 7 redirecionamentos (carteirinha_gerar.php:27,39; carteirinha_enviar.php:25,30,38,46,53,84) → todos caem em 404.php. |
| 3 | CRÍTICO | **fichas_completas.php tem formulários de Aprovar/Recusar sem processamento POST** (fichas_completas.php:176-192; nenhum bloco POST no arquivo — só filtro GET em 28-50). Os botões não fazem nada. |
| 4 | ALTO | **Semântica dupla em associados.estado (CHAR(2))**: os formulários gravam o **ID numérico** do estado (cadastrar.php:188; editar.php:199-206; meu_perfil.php:419-431), a aprovação da ficha grava a **UF** (FichaAdesao.php:901) e o relatório filtra por **'23'** (ativos_municipio.php:21,52). Base inconsistente e relatórios que não fecham. |
| 5 | ALTO | **modulos/associados/cadastrar.php cria identidade duplicada**: INSERT em associados e INSERT em usuarios (cadastrar.php:91-103). O registro em usuarios nasce com status default **'ativo'** (migration.sql:1494) → a pessoa loga imediatamente mesmo com associados.status = 'pendente', contornando a máquina de estados de aprovação. |
| 6 | ALTO | **modulos/eventos/editar.php não tem CSRF** (form 76-101 sem token; handler POST 43-70 sem validatePostRequest()) e o mesmo POST grava presenças. |
| 7 | ALTO | **A votação de assembleia exige sessão do Portal** ($_SESSION['associado_id'] — assembleia/visualizar.php:46-47,69,103). Quem entrou pelo login principal (user_table = 'associados', login.php:108-111) não consegue votar. |
| 8 | MÉDIO | **tipo_votacao = 'ponderada' não existe na prática**: assembleia_votos.peso tem DEFAULT 1.00 e nunca é preenchido (visualizar.php:121-130); o resultado só soma votos (resultado.php:78-134). |
| 9 | MÉDIO | **O token da carteirinha é enviado a terceiro** (https://api.qrserver.com — CarteirinhaImpressa.php:65-68; carteirinha_pdf.php:39-43). O token é a credencial pública de validação e sai do domínio via GET. |
| 10 | MÉDIO | **Menu x guarda em Cadastros Pendentes**: o menu mostra para toda a Diretoria (menu.php:33, via podeAcessarDiretoria()), mas a tela aceita apenas Admin ou Diretoria **Executiva** (cadastros_pendentes.php:20-24; fichas_completas.php:19-23), negando Conselho Fiscal/Comissões. |

---

## 2. Arquitetura de acesso (vale para todas as telas)

### 2.1 Roteamento
- **index.php** é o router; só entra no bloco de rotas quando $_GET['url'] não é vazio (index.php:6-7). Sem 'url', a raiz redireciona logados para o dashboard (index.php:1182-1190).
- Não há guarda global no router: o guard efetivo é **includes/header.php:16-19** (redireciona para /login quando !isLoggedIn()), com exceções declaradas pela página: PERMITIR_ACESSO_ANONIMO e PERMITIR_SESSAO_PORTAL (header.php:11-14).
- Rotas deste escopo: associados 615-699; associações 702-721; usuários 724-736; eventos 754-780; assembleia 1110-1133; relatórios da diretoria 1147-1155; portal 906-917; ficha de adesão 624-637; carteirinhas 639-668; meu-perfil 679-682. Fallback 404.php em 1175-1177.
- APIs são chamadas por caminho de arquivo (ex.: /api/ficha_adesao.php), pois o rewrite só atua quando o arquivo não existe (.htaccess:16-24). O diretório /classes é bloqueado por classes/.htaccess:1-13.

### 2.2 Níveis, perfis e governança
- **config/config.php:23-25**: NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3 (menor = mais poder).
- **User::hasPermission()** (User.php:114-141): rejeita nível não numérico (125-128), exige > 0 (131-133), Admin passa sempre (136-138), senão compara nivel <= required (140).
- **User::tabelaUsuarioSegura()** (User.php:31-52): whitelist ['associados','usuarios'] (13-14) antes de interpolar $_SESSION['user_table'] em SQL.
- **Diretoria**: TIPOS_VINCULO_DIRETORIA (User.php:278-287) = diretor, secretario, tesoureiro, comissao_educativa, comissao_etica, conselho_fiscal, presidente, vice-presidente. getAssociacoesDiretoria() 337-354; isMembroDiretoria() 363-370; podeAcessarDiretoria() 380-383 (Admin **ou** membro); isDiretoriaExecutiva() 392-414 (Admin **ou** presidente/vice/diretor/secretário/tesoureiro — **exclui** conselho fiscal e comissões).
- **ApiAuth** (classes/ApiAuth.php): nivel() 50-68 (sessão do portal conta como nível 3); ehStaff() 84-88; exigirLogin/exigirStaff/exigirNivel/exigirProprioAssociado 133-183; negar() 186-194.
- **CSRF** (classes/CSRFProtection.php): geração 6-13; getTokenField 15-22; validatePostRequest 24-38; tokenFromRequest 77-109 (header X-CSRF-Token, POST, GET, JSON). includes/header.php:100-132 injeta window.CSRF_TOKEN e um wrapper global de fetch que adiciona o header em POST/PUT/DELETE/PATCH.
- **getAssociadoIdResolvido()** (User.php:299-329) resolve o associado da sessão por 4 caminhos: $_SESSION['associado_id'] (302-304), user_table = 'associados' (312-314), usuarios.associado_id (317-319), associados.user_id (322-328).

### 2.3 Menu (como se chega a cada tela)
- Associados → menu.php:32; Cadastros Pendentes (com badge de ficha_adesao pendentes, menu.php:39-44) → menu.php:33; Todas as Fichas (Admin/Diretoria Executiva) → menu.php:46-60; Carteirinhas → menu.php:61; Associações → menu.php:62 e 88; Eventos → menu.php:69 e 89; Relatórios Diretoria → menu.php:74-76; Assembleia → menu.php:80; Usuários (Gestor+) → menu.php:213-215; Pendentes de Ativação (Admin, badge menu.php:222) → menu.php:226-231; Todas as Fichas de Adesão (Admin) → menu.php:232-237; Meu Perfil → menu.php:240; Portal do Associado → menu.php:23.
- Para quem não é Diretoria, o menu expõe apenas Associações e Eventos (menu.php:82-91).

---

## 3. Modelo de dados relevante (DDLs lidas)

- **associados** (sinporg/prisma/migrations/0_baseline/migration.sql:322-383): status ENUM('ativo','inativo','pendente') DEFAULT 'pendente' (345); tipo ENUM('Admin','Gestor','Usuario') (346); tipo_usuario (347); area_producao (349); **cidade VARCHAR(100)** (335) e **estado CHAR(2)** (336), ambos sem FK; vinculo/vinculo_base/associacao_id legados (354-356); user_id UNIQUE (357,379); tipo_associado ENUM(...) (360); status_associado ENUM('Ativo','Inadimplente','Afastado','Desligado','Falecido') (361); profissao (362); data_admissao/data_desligamento (363-364); flags de ficha ficha_enviada/ficha_aprovada/ficha_aprovada_por/ficha_aprovada_em (367-370); notificacoes_ativas/dias_antecedencia_evento (372-373). As colunas de CRM vieram de sql/alter_associados_associacao.sql:6-14.
- **usuarios** (migration.sql:1475-1516): **nivel INT DEFAULT 1** (1493 — INSERT que esqueça nivel cria Admin); **status DEFAULT 'ativo'** (1494); tipo ENUM('admin','gestor','usuario') (1496); associado_id UNIQUE (1502,1513). Colunas de confirmação de acesso em sql/migracao_confirmacao_acesso.sql:12-20; tem_agroindustria/area_producao em sql/migracao_ficha_adesao_usuario.sql:34-36.
- **associacoes** (migration.sql:285-308): cidade VARCHAR(100) (293), estado VARCHAR(50) (294), sem FK; UNIQUE em nome, sigla, email (304-306).
- **associado_associacao** (migration.sql:311-319 + sql/criar_tabela_associado_associacao.sql:6-14): PK composta, data_vinculo, tipo_vinculo ENUM(9 cargos) (315).
- **ficha_adesao** (sql/create_ficha_adesao.sql:16-104): associado_id UNIQUE + FK ON DELETE CASCADE (101-103); status ENUM('rascunho','pendente','aprovada','recusada') DEFAULT 'rascunho' (91); aceite_termos (88); analisado_por (94); parecer_diretoria (95). sql/migracao_ficha_adesao_usuario.sql:18-26 tornou associado_id NULLABLE e criou usuario_id UNIQUE + FK ON DELETE SET NULL. Produtos em **ficha_adesao_produtos** (create_ficha_adesao.sql:107-118).
- **carteirinhas** (sql/create_associacao_financeiro.sql:44-53): associado_id, token VARCHAR(64) UNIQUE, validade DATE, gerada_em — sem FK e sem histórico.
- **eventos** (migration.sql:782-792): data_evento DATETIME, status ENUM('Agendado','Realizado','Cancelado'), confirmados TEXT (JSON).
- **evento_presenca** (migration.sql:767-779): associado_id/usuario_id nullable, status_presenca ENUM('Confirmado','Presente','Ausente','Justificado') — **não existem 'Nenhum' nem 'Não Confirmado'**; sem FK para eventos.
- **assembleias** (migration.sql:265-282): status ENUM('rascunho','ativa','encerrada','cancelada'), tipo_votacao ENUM('simples','ponderada'), quorum_minimo DECIMAL(5,2), created_by. **assembleia_pautas** (231-243): tipo ENUM('aprovacao','eleicao','outros'), ordem, opcoes LONGTEXT (JSON). **assembleia_votos** (246-262): voto LONGTEXT (JSON), peso DECIMAL(10,2) DEFAULT 1.00, UNIQUE unique_voto(assembleia_id,pauta_id,associado_id). FKs em 1576-1585.
- **notificacoes** (migration.sql:1151-1167) e **audit_log** (414-427).

---

## 4. MÓDULO ASSOCIADOS

### 4.1 modulos/associados/listar.php (482 linhas) — GET /associados
- **Rota/menu**: index.php:615-618; menu.php:32.
- **Propósito**: listagem mestre com exportação Excel/PDF/Imprimir via DataTables Buttons (340-365) e acesso à ficha de adesão.
- **Entradas**: GET erro (14-35: permissao_admin, id_nao_informado, registros_vinculados, exclusao, csrf) e msg/nome (36-46).
- **Permissão**: exige sessão (50-54); nível 3 é redirecionado para /associado/<meu id>/editar ou /meu-perfil (57-69).
- **Tabelas**: associados + LEFT JOIN cidades (Associado::getAll, Associado.php:144-165); **ficha_adesao consultada por linha** (listar.php:171 — N+1).
- **Ações**: Adicionar Novo (125); Gerar Relatório (128); Editar/Excluir (202-205, exclusão via form montado em JS com CSRF, 281-302); Visualizar (207, ?view=1); botão de ficha (228-240) que abre modal AJAX.
- **AJAX**: fetch(SITE_URL + '/cadastro-ficha/visualizar?id=' + id + '&modal=1') (452-457).
- **Bugs**: (a) **confusão ficha_id x associado_id** — com ficha passa `$fichaId`, sem ficha passa `$assoc['id']` (229 vs 236), mas a rota espera **id da ficha** (ficha_adesao_visualizar.php:45-53) e autoriza por associado_id/usuario_id da ficha (75-85) → pode abrir a ficha de outra pessoa ou falhar; (b) **arquivo corrompido**: a última linha do .php é o texto literal </create_file> (linha 482); (c) cabeçalho duplica data-label de status (198 vs 200).

### 4.2 modulos/associados/pendentes.php (313 linhas) — GET /associados/pendentes
- **Rota/menu**: index.php:619-622; menu.php:226-231.
- **Propósito**: central de aprovação de acesso. Une associados WHERE status='pendente' com usuarios WHERE status='pendente' por UNION ALL com COLLATE explícito (87-102).
- **Permissão**: **somente Admin** (7-11).
- **POST** (CSRF em 22-25): origem = 'usuario' → confirmar_acesso (nivel_concedido + orgao/setor/especialidade) ou negar_acesso (motivo obrigatório) via AcessoPendente (35-59); origem = 'associado' → ativar/inativar com UPDATE direto em associados.status (60-79).
- **Campos**: id, acao, origem, nivel_concedido, orgao, setor, especialidade, motivo (225-296).
- **Ações**: Confirmar acesso (237-239), Recusar (272-276), Ativar/Rejeitar (279-298), Ver Todos os Associados (127).
- **Tabelas**: associados, usuarios, audit_log (via AcessoPendente), notificacoes.
- **Gaps**: ativar associado pendente **não cria conta** em usuarios, não notifica e não audita; rejeitar associado grava 'inativo' sem motivo; o arquivo depende de CSRFProtection carregado por includes/header.php:99 (sem require local); TESTES.md:141 afirma que aqui não há CSRF — informação desatualizada.

### 4.3 modulos/associados/cadastros_pendentes.php (269 linhas) — GET /cadastros-pendentes
- **Rota/menu**: index.php:624-627; menu.php:33-45.
- **Propósito**: análise das Fichas de Adesão pendentes (DataTable ordenada por data_submissao, 251-258) + últimas 10 avaliadas (54, 173-214).
- **Permissão**: Admin **ou** Diretoria Executiva (20-24).
- **POST** (CSRF 33-35): acao=aprovar → FichaAdesao::aprovar(fichaId, analisadoPor); acao=recusar → recusar(..., motivo) (37-46); analisadoPor = $_SESSION['user_id'] ?? $_SESSION['associado_id'] (38).
- **Campos**: acao, ficha_id, motivo (150-151, 225-226, 237-238).
- **Ações**: Visualizar (143-146), Aprovar (147-155), Recusar (156-161 + modal 220-247 + JS 260-265).
- **Tabelas**: ficha_adesao (FichaAdesao::listarPendentes/listarAvaliadas, FichaAdesao.php:571-616), associados, usuarios, notificacoes, audit_log.
- **Gaps**: sem paginação server-side; a coluna "Associado(a)" usa nome/sobrenome do COALESCE (123) que pode vir vazio para pedido de acesso sem nome completo (FichaAdesao.php:576).

### 4.4 modulos/associados/fichas_completas.php (260 linhas) — GET /fichas-completas
- **Rota/menu**: index.php:629-632; menu.php:47-59 (Diretoria Executiva) e menu.php:232-237 (Admin).
- **Propósito**: todas as fichas, com filtro de status e contadores (28-59, 87-101).
- **Permissão**: Admin ou Diretoria Executiva (19-23).
- **Bugs**: (a) **INNER JOIN associados** (40) → fichas de pedido de acesso (associado_id NULL) **não aparecem** (contraste com LEFT JOIN + COALESCE em FichaAdesao::listarPendentes, 576-584); (b) **Aprovar/Recusar sem handler POST** (176-192, modal 208-235) — botões inertes; (c) DataTable ordena pela coluna 5 (Status, linha 243).

### 4.5 modulos/associados/ficha.php (289 linhas) — SEM ROTA (órfã)
- **Rota**: inexistente em index.php; alcançável só por /modulos/associados/ficha.php?id=N. É destino de 7 redirects (carteirinha_gerar.php:27,39; carteirinha_enviar.php:25,30,38,46,53,84) → 404.php.
- **Permissão**: podeAcessarDiretoria() (12-15); podeGerenciarCarteirinha = hasPermission(NIVEL_GESTOR) (16).
- **Conteúdo**: cartão de identificação (100-124) + 4 abas — Dados (148-172), Financeiro via Mensalidade::getResumoFinanceiro/getByAssociado (175-231), Presença via Associado::getPresencas (234-253), Vínculos via AssociadoAssociacao::getByAssociado (256-281).
- **Ações**: Carteirinha (PDF) 75; Imprimir/PDF Completo 77; Enviar por E-mail (POST + CSRF) 79-82; Gerar/Renovar 85; Editar 87; Gerenciar Vínculos (Admin) 260.
- **Tabelas**: associados, associado_associacao, associacoes, evento_presenca, eventos, mensalidades, carteirinhas.
- **Nota**: é o **único** lugar com links de carteirinha corretos (encodeId) — e é inalcançável. Rotear este arquivo resolve o achado #1.

### 4.6 modulos/associados/cadastrar.php (358 linhas) — GET/POST /associado/novo
- **Rota/menu**: index.php:673-676; listar.php:125.
- **Permissão**: login (17-20) + in_array($userNivel, [NIVEL_ADMIN, NIVEL_GESTOR]) (23-24); POST sem acesso → die('Acesso negado.') (29-31).
- **Validações**: nome, sobrenome, e-mail válido, senha e confirmação (52-66).
- **Fluxo**: beginTransaction (72) → Associado::create (76-83) → INSERT em usuarios com nivel=NIVEL_USUARIO (91-103) → commit e redirect (105-109).
- **Campos** (formFields 132-190): nome, sobrenome, email, data_nascimento, senha, confirmar_senha, cpf, rg, tipo_usuario (agricultor | produtor_agroindustria | agricultor_agroindustria), tipo_associado (Efetivo/Honorario/Benemerito/Dependente/Aspirante), status_associado (Ativo/Inadimplente/Afastado/Desligado/Falecido), data_admissao, profissao, data_desligamento, endereco, numero, complemento, bairro, cep, cidade, estado; checkboxes associacoes[] (283-290) + hidden associacoes_enviado (293).
- **AJAX**: /api/get_cidades.php?estado_id= (323).
- **Associado::create** (Associado.php:39-135): e-mail único (46-52), nivel=NIVEL_USUARIO (62), tipo='Usuario' (63), status='pendente' (64), normaliza tipo_usuario (67-73), valida enums (76-85), vínculos N:M (120-127).
- **Bugs**: (a) identidade duplicada + bypass de aprovação (achado #5) e o INSERT em usuarios não define status (default 'ativo') nem checa e-mail único; (b) **HTML malformado na linha 293** — </small> não fecha, bloco de espaços antes do hidden, hidden dentro do small; **$readonly calculado em 248 nunca é aplicado** (266), então cidade é <input type=text> (185) enquanto o JS trata como select (317-346) → seleção de cidade não funciona, gravando texto livre, mas Associado::getAll faz JOIN por id (Associado.php:162); (c) JS quebra na linha 354 — cidadeSelecionada não existe no escopo (o parâmetro é cidadePreSelecionada, 317); (d) estado grava id numérico em CHAR(2) (188 + Associado.php:103) — achado #4; (e) create não valida CPF duplicado (só e-mail, 46-52) embora cpf seja UNIQUE (migration.sql:376).

### 4.7 modulos/associados/editar.php (501 linhas) — GET/POST /associado/<id>/editar
- **Rotas**: index.php:683-687 (numérica) e 689-692 (legada /associado/editar?id=).
- **Permissão**: isViewMode = ($_SESSION['user_nivel'] != NIVEL_ADMIN) || isset($_GET['view']) (23); nível 3 só o próprio cadastro (28-36, via getAssociadoIdResolvido()) e entra em modo visualização.
- **Campos**: nome, email, cpf, telefone, cep, endereco, estado (select por id, 199-206), cidade (select, 212; carregada por /api/get_cidades.php, 415), status (select só para o "Admin" detectado, 220-224), foto (307; ImageUploader 300x400 e 2MB, 92-97) e, no modal, associacoes[] + cargo[<assoc_id>] com form="formEditarAssociado" (355-372).
- **Bug de status**: linha 85 testa $_SESSION['user_tipo'] !== 'Admin'; para contas de usuarios o valor é 'admin' minúsculo (migration.sql:1496; AcessoPendente.php:43-47) → **o select de status nunca aparece e status é sempre removido do POST (87)**, mesmo para Admin.
- **Tabelas**: associados (update dinâmico com whitelist em Associado.php:251-276; ultima_atualizacao=NOW() 329), associado_associacao (Associado::saveAssociacoes 398-444), cidades, estados.
- **Cargos**: editar.php:48-58 = Associado.php:311.
- **Ações**: Salvar Alterações (317), Cadastrar Propriedade (318-321), Voltar (323), Atribuir Cargo (270-272 → modal 334-383).
- **Gaps**: o form não expõe tipo_associado/status_associado/profissao/data_desligamento previstos no UPDATE (Associado.php:270-275) e citados no TESTES.md:178; gestor é sempre read-only (23 + listar.php:207).

### 4.8 modulos/associados/excluir.php (86 linhas) — POST /modulos/associados/excluir.php (chamado por JS)
- **Permissão**: somente Admin (11-15). **CSRF** obrigatório (18-21).
- **Regras**: existe? (27-35); bloqueia se houver propriedades ou atividades_diarias (38-53); DELETE em transação (56-77).
- **Gaps**: **DELETE físico** (63) — apaga associado_associacao e ficha_adesao por CASCADE (criar_tabela_associado_associacao.sql:12; create_ficha_adesao.sql:103) mas não limpa usuarios.associado_id/associados.user_id, carteirinhas, mensalidades, assembleia_votos e evento_presenca (sem FK); não gera audit_log.

### 4.9 modulos/associados/meu_perfil.php (694 linhas) — GET/POST /meu-perfil
- **Rota/menu**: index.php:679-682; menu.php:240; usado no primeiro acesso (login.php:196-203).
- **Propósito**: editar os próprios dados na tabela correta (User::getCurrentUser, User.php:236-253) com sincronização entre associados e usuarios.
- **Bloqueio**: unset(dados['nivel'], dados['tipo']) (176); status só para Admin (179-181).
- **Dois POSTs**: (1) acao=gerenciar_vinculos (112-161) — Admin gerencia qualquer associado; Gestor/Diretoria só os próprios vínculos e **não pode autoatribuir cargo executivo** (135-152); (2) perfil (166-289) — updateCurrentUser (209), saveAssociacoes (212-214), upload de foto (185-205), sincronização cruzada por vínculo e nunca por e-mail (220-273), primeiro acesso → /caderno-campo/propriedade/novo (276-280).
- **Campos**: nome, sobrenome, email, telefone, cpf, data_nascimento, cep, endereco, numero, bairro, complemento, estado, cidade, status (Admin, 467-478), associacoes[] (451-453), foto; bloco de gestão com gerenciar_associado_id (GET, 558) + associacoes[]/cargo[] (590-598).
- **AJAX**: /api/get_cidades.php (636).
- **Bugs**: (i) **estado convertido de UF para id no save** (427 usa id como value; 425 aceita id ou UF) — ver achado #4; (ii) cidade é select (436) e para registros cujo campo guarda nome o preenchimento casa por nome (644) e o save grava id — conversão silenciosa; (iii) usa getAssociadoId() (69) e não a versão Resolvido; (iv) campos cpf/data_nascimento aparecem mesmo quando a tabela é usuarios (que não os possui) e são ignorados silenciosamente por updateCurrentUser (User.php:533-542).

### 4.10 modulos/associados/form_relatorio.php (74 linhas) — GET/POST /associados/relatorio
- **Rota**: index.php:669-672; link em listar.php:128.
- **Permissão**: sessão + Admin/Gestor (11-20).
- **Campos**: nome_associacao (obrigatório), nome_responsavel; botões formato=pdf|excel (44-62).
- **Destino**: POST para /api/relatorios/relatorio_associados.php em nova aba (44) — endpoint na whitelist ROTAS_API_RELATORIOS_PERMITIDAS (includes/rota_segura.php:30-52).
- **Gap**: o POST não envia CSRF.

### 4.11 Carteirinhas (6 arquivos) — ver seção 12.

### 4.12 modulos/associados/TESTES.md (183 linhas)
- Checklist de QA **desatualizado**: afirma que o primeiro acesso grava em associados com status 'pendente' (20-29) quando api/pre_cadastro.php:136-153 grava em **usuarios**; afirma que pendentes.php não tem CSRF (141) quando tem (pendentes.php:22); a seção de conformidade (145-183) trata de outros módulos.

---

## 5. MÓDULO ASSOCIAÇÕES

### 5.1 modulos/associacoes/listar.php (266 linhas) — GET /associacoes
- **Rota/menu**: index.php:702-705; menu.php:62 e 88.
- **Permissão**: apenas isLoggedIn() (8-12) — o comentário (7) diz Admin/Gestor, mas o código não verifica.
- **Tabelas**: associacoes + LEFT JOIN cidades/estados (Associacao.php:70-77).
- **AJAX**: /api/get_associacao_detalhes.php?id= (142); endpoint exige login (api/get_associacao_detalhes.php:8) e usa getDetalhesCompletos.
- **Ações**: Adicionar Nova (25), Ver Detalhes (51), Editar (52-55, POST com hidden id para /associacao/editar), Excluir **desabilitado** com modal (56, 211-265).
- **Gap**: XSS potencial no modal — innerHTML com nome/sigla/descricao/objetivo/site sem escape (151-176).

### 5.2 modulos/associacoes/cadastrar.php (187 linhas) — GET/POST /associacao/novo
- **Permissão**: só isLoggedIn() (11-15).
- **Campos**: nome*, sigla, descricao, objetivo, data_fundacao, responsavel, cargo_responsavel, telefone, email, site, endereco, estado*, cidade* (select via /api/get_cidades.php, 153), cep.
- **Regras**: Associacao::create (Associacao.php:18-62) — nome único (24-30), status fixo 'ativo' (37), data_cadastro=NOW() (32).
- **Gaps**: sem CSRF (17-33, 45); **dupla sanitização** (htmlspecialchars+strip_tags em 22-24 e Sanitizer no model, 21); quebra se um campo vier como array.

### 5.3 modulos/associacoes/excluir.php (76 linhas) — POST /modulos/associacoes/excluir.php (SEM ROTA)
- **Permissão**: isGestorOuAdmin() (9-13) + POST + CSRF + id numérico (15-19).
- **Regra**: bloqueia se existir associados.vinculo = id (37-50) antes do DELETE (53).
- **Bugs**: não está roteado (código morto na prática, pois a lista usa botão desabilitado); ignora a N:N associado_associacao (o CASCADE fk_aa_associacao apagaria vínculos silenciosamente); ecoa nome sem escape.

### 5.4 modulos/associacoes/editar.php (206 linhas) — POST/GET /associacao/editar
- **Rota**: index.php:710-716 (aceita $_POST['id'] em 711-713).
- **Permissão**: isGestorOuAdmin() (8-12) — aqui há verificação real.
- **Campos**: iguais ao cadastro + status (ativo/inativo, 145-148).
- **Tabelas**: associacoes (Associacao::update, 130-179), estados, cidades.
- **Gaps**: sem CSRF; dupla sanitização (41); data_fundacao sem escape (81); select de cidade sem pré-seleção por id/nome.

### 5.5 modulos/associacoes/associados.php (259 linhas) — GET/POST /associacao/associados
- **Rota**: index.php:718-721; link em ficha.php:260.
- **Permissão**: **somente Admin** (12-20).
- **POST** (43-119, CSRF 44-46): DELETE dos vínculos não mantidos (66-68), INSERT dos novos (75-87), atualização de associados.vinculo quando não há principal (89-99) e de tipo_vinculo (102-109).
- **Campos**: associacao_id, associados[], cargo[<associado_id>] (179-181, 200-201, 238).
- **Tabelas**: associado_associacao, associados, associacoes.
- **Gaps**: **sem transação** (contraste com AssociadoAssociacao::save, 102-124); checkbox "selecionarTodos" oculto e sem handler (194-196); DELETE antes de validar ids; subquery NOT IN pesada (92).

---

## 6. MÓDULO USUÁRIOS

### 6.1 modulos/usuarios/listar.php (199 linhas) — GET /usuarios
- **Rota/menu**: index.php:724-727; menu.php:213-215.
- **Permissão**: sessão (11-15); nível 3 é redirecionado para /usuario/<id>/editar (18-21), rota que **exige Gestor** (usuarios/editar.php:7-11) → beco sem saída.
- **Dados**: SELECT id,nome,email,nivel,status FROM usuarios (35) — **não lista associados**.
- **Ações**: Adicionar Novo (48), Gerar PDF via /api/relatorios/admin_export.php?tipo=pdf&relatorio=usuarios (49-51), Editar (86) com regra Admin/Gestor ou ele mesmo (85), Excluir desabilitado (90 + modal 144-198).

### 6.2 modulos/usuarios/cadastrar.php (205 linhas) — GET/POST /usuario/novo
- **Permissão**: Admin/Gestor (8-12); **só Admin cria Admin** (18-19).
- **Identidade**: Admin pode escolher associado ativo sem conta (30-40); nome/sobrenome/CPF/telefone vêm do cadastro (65-75); vínculo gravado nos dois lados (99-103) com audit IDENTIDADE_VINCULADA.
- **Campos**: associado_id (130-140), nome, email, senha, nivel (1/2/3 conforme permissão, 165-172), status (176-180).
- **Tabelas**: usuarios, associados, audit_log; transação 81-104; erro de PDO exposto (113).
- **JS**: preenche nome/e-mail ao escolher o associado (188-203).

### 6.3 modulos/usuarios/editar.php (267 linhas) — GET/POST /usuario/<id>/editar
- **Permissão**: hasPermission(NIVEL_GESTOR) (7-11); **Gestor não edita contas de equipe (nível <= 2) nem a própria** (40-48); whitelist de nível no POST ([NIVEL_USUARIO] para gestor, 89).
- **Vínculo**: Admin altera associados.user_id/usuarios.associado_id nos dois lados, inclusive desvinculando (139-167), com audit IDENTIDADE_VINCULADA/IDENTIDADE_DESVINCULADA.
- **Campos**: associado_id (Admin, 197-210; opções já vinculadas ficam disabled, 205), nome (readonly se vinculado, 221-222), email, senha (opcional, 240-241), nivel, status.
- **Gaps**: sem auditoria de mudança de nível/senha; erro de PDO exposto (182).

### 6.4 modulos/usuarios/perfil.php (227 linhas) — GET/POST /usuario/perfil
- **Permissão**: apenas logado (6-10).
- **Dados**: SELECT * FROM {user_table} com whitelist (27-33) e fallback (35-49).
- **Campos**: nome, email, senha_atual, nova_senha, confirmar_senha; troca de senha exige senha atual (65-81).
- **Bugs**: **sem CSRF** (57, 109); funcionalidade duplicada com /meu-perfil (menu aponta para /meu-perfil, menu.php:240); UPDATE sem tratar e-mail duplicado (UNIQUE nas duas tabelas) → 1062 mostrado ao usuário (92).

### 6.5 modulos/usuarios/configuracoes.php (212 linhas) — GET/POST /usuario/configuracoes
- **Permissão**: apenas logado (6-10).
- **Propósito**: preferências (tema/idioma) gravadas **só na sessão** ($_SESSION['user_config'], 21-25) + atalhos.
- **Campos**: tema, idioma (98-117); o bloco de notificações faz POST para /usuario/salvar_notificacoes (158-206: notificacoes_ativas, dias_antecedencia, notif_eventos_hoje/proximos/atrasados).
- **Gaps**: form de notificações **sem CSRF** (e o endpoint também não valida); 2FA, sessões, exportar/excluir conta e ajuda são botões disabled (55-63, 137-144).

### 6.6 modulos/usuarios/salvar_notificacoes.php (62 linhas) — POST /usuario/salvar_notificacoes
- **Bug**: faz UPDATE associados SET notificacoes_ativas, dias_antecedencia_evento WHERE id = <user_id> **sempre** (28-34), ignorando $_SESSION['user_table'] — para contas de usuarios atinge 0 linhas e as preferências se perdem; as demais flags ficam só na sessão (38-42). Sem CSRF. Redirect para /usuario/configuracoes?msg=... (53-60).

### 6.7 modulos/usuarios/excluir.php (28 linhas) — POST, SEM ROTA
- **Permissão**: hasPermission(NIVEL_GESTOR) (6-10) → **Gestor pode excluir qualquer usuário, inclusive Admin**.
- POST + CSRF + id (13-17); DELETE FROM usuarios (21); redirect relativo listar.php?msg=excluido (23). Não há rota em index.php.

---

## 7. MÓDULO EVENTOS

### 7.1 modulos/eventos/listar.php (382 linhas) — GET /eventos
- **Rota/menu**: index.php:754-757; menu.php:69 e 89.
- **Permissão**: hasPermission(NIVEL_USUARIO) (32-36) → qualquer logado; botões de gestão só Gestor (73-75, 126-129).
- **Entradas**: msg=excluido (9-15); erro em {permissao, id_nao_informado, exclusao} (17-30).
- **Consulta**: agendados futuros (42-45) + restante (50-53), concatenados (58).
- **Ações**: Presenças em Eventos (70), Adicionar Novo (74), Visualizar (120 → visualizarEvento() em assets/js/eventos.js:2), Acompanhar Presença via AJAX (123, modal 248-381), Editar (127), Excluir desabilitado (128 + modal 192-246).
- **AJAX**: /api/get_presenca_evento.php?evento_id= (278).
- **Gap**: o modal usa data.nao_confirmados_associados.length (321) sem guarda — quebra se a API omitir a chave.

### 7.2 modulos/eventos/cadastrar.php (177 linhas) — GET/POST /evento/novo
- **Permissão**: login (15-18) + hasPermission(NIVEL_GESTOR) (20-26).
- **Validação**: nome e data_evento obrigatórios (42-44).
- **Campos** (68-86): nome*, descricao, data_evento* (datetime-local), local, status (Agendado/Realizado/Cancelado).
- **Tabela**: INSERT direto em eventos (46-47) — não usa o model Evento; CSRF presente (30-32, 128).

### 7.3 modulos/eventos/editar.php (200 linhas) — GET/POST /evento/<id>/editar
- **Permissão**: hasPermission(NIVEL_GESTOR) (8-12).
- **Campos**: nome, data_evento (**type=date**, 83), local, descricao, status (95-99) + radios presenca[<associado_id>] em {Presente, Ausente, Justificado, Nenhum} (139-153).
- **Processamento**: UPDATE do evento + Evento::atualizarPresencas (51-56).
- **Tabelas**: eventos, evento_presenca, associados.
- **Bugs**: (a) **SEM CSRF**; (b) **perda da hora** (input date em DATETIME → 00:00:00); (c) status_atual = 'Confirmado' marca o radio Limpar (151), escondendo a confirmação; (d) link de PDF aponta para caminho de arquivo direto (110).

### 7.4 modulos/eventos/visualizar.php (202 linhas) — AJAX /evento/<id>
- **Rota**: index.php:771-775 (define $_GET['id']).
- **Permissão**: ApiAuth::exigirLogin() (9) e **exige X-Requested-With: XMLHttpRequest** (15-20) → acesso pelo browser devolve 403 JSON.
- **Saída**: JSON com html do modal + titulo (181-189); lê eventos.confirmados (123-125); botão Confirmar/Cancelar quando Agendado (155-167); Editar só staff (171-176).
- **Gap**: dupla fonte de verdade — este endpoint lê o JSON, enquanto presencas.php e o relatório usam evento_presenca.

### 7.5 modulos/eventos/presencas.php (105 linhas) — GET /eventos/presencas e /evento/presencas (index.php:758-766)
- **Permissão**: qualquer logado (7-11).
- **Dados**: se $_SESSION['user_tipo'] em minúsculas = 'usuario' → Evento::getPresencasUsuarioAgendados (Evento.php:132-230, com fallback no JSON); senão Evento::getPresencaEventos.
- **Gap**: o switch de cor (61-78) não tem caso para 'Justificado' (cai em secondary).

### 7.6 modulos/eventos/acompanhar_presenca.php (206 linhas) — SEM ROTA / ÓRFÃO
- Sem rota em index.php e sem link em qualquer arquivo (verificado por grep). Acessível só por /modulos/eventos/acompanhar_presenca.php.
- Mostra, por evento, confirmados (JSON) x pendentes, separando gestores/associados/usuários puros (36-105); "usuário puro" é decidido por CPF do associado vinculado (91-100). N+1: uma query por confirmado e por evento (52-74).

### 7.7 modulos/eventos/relatorio_presenca.php (184 linhas) — via caminho direto (link em editar.php:110)
- **Permissão**: **apenas isset($_SESSION['user_id'])** (8-10) — qualquer nível imprime nome/e-mail de todos os associados.
- **Saída**: PDF mPDF A4 com tabela Nome/E-mail/Presença (137-158) e assinatura de $_SESSION['user_nome'] (163).
- **Gap**: classe calculada por 'bg-' . strtolower(status) (142) com CSS cobrindo presente/ausente/justificado/nenhum (88-91).

### 7.8 modulos/eventos/excluir.php (27 linhas) — POST /modulos/eventos/excluir.php
- **Permissão**: ApiAuth::exigirStaff() (10). POST + CSRF + id (12-16). DELETE em eventos (20) → **presenças ficam órfãs** (sem FK entre evento_presenca e eventos, migration.sql:767-779). Redirects relativos para listar.php (14, 22, 25).

### 7.9 APIs de presença
- **api/toggle_presenca.php (127 linhas)**: AJAX + POST + CSRF (8-33); **dual-write** — JSON em eventos.confirmados (75-77, 92-94) e linha em evento_presenca (113-114), com delete simétrico (80-86).
- **api/get_presenca_evento.php (147 linhas)**: ApiAuth::exigirLogin() (9) + X-Requested-With (13-17); confirmados vêm do JSON, pendentes dos ativos.
- **api/limpar_modal_eventos.php (15 linhas)**: limpa $_SESSION['mostrar_modal_eventos'] (11) com exigirLogin (8).

---

## 8. MÓDULO ASSEMBLEIA

### 8.1 modulos/assembleia/listar.php (181 linhas) — GET /assembleia
- **Rota/menu**: index.php:1110-1112; menu.php:80.
- **Permissão**: hasPermission(NIVEL_GESTOR) **ou** isMembroDiretoria() (12-15) — inclui Conselho Fiscal/Comissões.
- **Entradas**: status em {rascunho, ativa, encerrada, cancelada} e associacao_id (20-43).
- **Consulta**: assembleias + LEFT JOIN associacoes/usuarios + subqueries de pautas e votos (23-49).
- **Ações por status**: rascunho → Editar/Ver/Pautas/Publicar (146-150); ativa → Ver/Resultado/Encerrar (151-153); encerrada → Resultado (154-156).
- **Gap**: o botão Editar aparece para qualquer status (146).

### 8.2 modulos/assembleia/cadastrar.php (144 linhas) — GET/POST /assembleia/novo
- **Permissão**: Gestor ou membro da Diretoria (13-16).
- **Validações**: título/data_inicio/data_fim obrigatórios (38-40); data_fim > data_inicio (41-43); quórum 0-100 (44-46).
- **Campos**: titulo*, descricao, data_inicio*, data_fim*, associacao_id (vazio = todas), tipo_votacao (simples|ponderada), quorum_minimo (default 50.00).
- **Tabela**: INSERT em assembleias com status='rascunho' e created_by = $_SESSION['user_id'] (48-52) → redirect para /assembleia/<id>/pautas (55-57). CSRF presente (26-28, 85).

### 8.3 modulos/assembleia/editar.php (171 linhas) — GET/POST /assembleia/<id>/editar
- **Gaps**: inclui CSRFProtection mas **não valida token** no POST e **não restringe por status**, permitindo editar assembleia ativa/encerrada.

### 8.4 modulos/assembleia/pautas.php (286 linhas) — GET/POST /assembleia/<id>/pautas
- **Permissão**: $_SESSION['user_nivel'] <= 2 **ou** isMembroDiretoria(associacao_id) (16, 38-48); **$isCriador é sempre false** (17-20, 43) — código morto.
- **Regra**: só gerencia em status='rascunho' (50-53).
- **POST** (CSRF 63-66): add_pauta (70-104; título obrigatório 94-96; tipo=eleicao monta opcoes JSON [{label,valor}] 77-92) e delete_pauta (105-112).
- **Campos**: acao, titulo, ordem, descricao, tipo (aprovacao|eleicao|outros), opcao_label[], opcao_valor[]; pauta_id. JS adiciona/remove opções (259-283) e exibe o bloco só em eleição (260-262).
- **Tabelas**: assembleia_pautas, assembleias. Ação "Publicar e Abrir Votação" (127).

### 8.5 modulos/assembleia/publicar.php (121 linhas) — GET/POST /assembleia/<id>/publicar
- **Permissão**: igual a pautas (16, 34-45).
- **Máquina de estados**: exige status='rascunho' (47-50); exige **ao menos 1 pauta** (53-61); POST + CSRF → status='ativa', updated_at=NOW() (63-68) → redirect para a visualização (70-71).
- **Gaps**: $isCriador morto (17); a página avisa que não será possível cancelar (104), mas o ENUM prevê 'cancelada' (migration.sql:272) e **nada no código grava esse status**.

### 8.6 modulos/assembleia/encerrar.php (108 linhas) — GET/POST /assembleia/<id>/encerrar
- **Permissão**: idem (16, 35-45).
- **Máquina de estados**: exige status='ativa' (47-50); POST + CSRF → status='encerrada', updated_at=NOW() (56-57) → redirect para /resultado (59). **Não congela o resultado** — ele é sempre derivado na consulta.

### 8.7 modulos/assembleia/visualizar.php (376 linhas) — GET/POST /assembleia/<id> (tela de votação)
- **Permissão de gestão**: $isGestor || $isCriador || $isGestorAssociacao (43-67, 158).
- **Quem vota**: $isAssociado = isset($_SESSION['associado_id']) (46) — **sessão do Portal**; $podeVotar exige vínculo em associado_associacao quando a assembleia tem associacao_id (69-76).
- **POST (103-143)**: CSRF (104-106); uma linha por pauta em assembleia_votos com voto JSON ({candidato:...} na eleição; {opcao:'sim'|'nao'|'abstencao'} na aprovação), ip e user_agent (110-131); transação (108,133); bloqueia revoto por !$jaVotou (103); mostra "Obrigado por votar" (340-345).
- **Janela temporal**: $ativa = status='ativa' e agora entre inicio e fim (92-98); prazos exibidos em 238-244.
- **Tabelas**: assembleias, assembleia_pautas, assembleia_votos, associado_associacao, associados, usuarios.
- **Gaps**: associado do login principal não vota (achado #7); aceita votar sem responder todas as pautas (120); $isCriador compara $_SESSION['user_id'] inexistente na sessão do Portal (52-58); peso nunca preenchido (ponderada inexistente).

### 8.8 modulos/assembleia/resultado.php (322 linhas) — GET /assembleia/<id>/resultado
- **Permissão**: idem gestão (14-50).
- **Aptos** (64-73): associados.status='ativo' AND status_associado='Ativo' (+ EXISTS em associado_associacao quando há associacao_id).
- **Apuração** (76-135): agrupa por voto (JSON) e decodifica; eleição compara candidato com opcoes[].valor (90-107); aprovação mapeia sim/nao/abstencao (108-126); quorum_atingido = (votos da pauta / aptos)*100 >= quorum_minimo (132).
- **CSV** (137-162) via ?export=csv, antes de qualquer HTML.
- **Gaps**: (i) quórum é avaliado **por pauta** e não há regra de maioria — o relatório não declara aprovado/rejeitado; (ii) voto em texto JSON impede índices/agregação eficiente; (iii) "Votantes Únicos" refaz a query a cada render (195-197).

---

## 9. RELATÓRIOS DA DIRETORIA

### 9.1 modulos/relatorios_diretoria/ativos_municipio.php (269 linhas)
- **Rota/menu**: index.php:1147-1149; menu.php:74.
- **Permissão**: hasPermission(NIVEL_GESTOR) ou isMembroDiretoria() (13-16).
- **Entradas**: estado (default '23', 21), mesorregiao, microrregiao, export=csv (80-90).
- **Consulta** (37-71): associados INNER JOIN cidades (c.id = a.cidade) + microrregiões/mesorregiões; WHERE a.estado = ? AND a.status='ativo' AND a.status_associado NOT IN ('Desligado','Falecido'); conta total/ativos/inadimplentes/afastados (44-47).
- **Tabelas**: associados, cidades, microrregioes_rs, mesorregioes_rs.
- **AJAX**: /api/regionalizacao/microrregioes.php?mesorregiao_id= (256).
- **Bugs**: (i) **INNER JOIN por a.cidade** — registros criados pela aprovação da ficha guardam o **nome** do município (FichaAdesao.php:900) e ficam fora do relatório; (ii) filtro '23' só casa com registros salvos por formulário (id), não com os aprovados por ficha (UF) — achado #4; (iii) % Inadimplência divide por ativos (201) e pode passar de 100%.

### 9.2 modulos/relatorios_diretoria/inadimplentes_micro.php (323 linhas)
- **Rota/menu**: index.php:1150-1152; menu.php:75.
- **Permissão**: Gestor/Diretoria (14-17).
- **Entradas**: mesorregiao, mes (default mês atual), ano (default ano atual), export=csv (83-105).
- **Dados**: Mensalidade::getInadimplentes(null, mes, ano) (31) e depois filtro + agrupamento por microrregião **com uma query por item** (34-75) — N+1 duplo.
- **Ação**: baixa por linha (/mensalidade/<id>/baixa, 290).
- **Bugs**: $local pode ser false quando a cidade não tem microrregião mapeada → acesso a $local['mesorregiao'] gera warning (60-62); sem tratamento de nulos.

### 9.3 modulos/relatorios_diretoria/entradas_saidas.php (440 linhas)
- **Rota/menu**: index.php:1153-1155; menu.php:76.
- **Permissão**: Gestor/Diretoria (13-16).
- **Entradas**: mes, ano, associacao_id, export=csv (21-23, 125-156).
- **Consultas**: entradas = pagamentos (status 'pago', métodos PIX/Boleto/Cartao/Transferencia/Dinheiro) UNION ALL caixa tipo 'Entrada' com categoria <> 'Mensalidade' (41-65); saídas = caixa tipo **'Saida'** agrupado por categoria (68-83); formas de pagamento (91-105); caixa por tipo/categoria (108-122).
- **Cálculos**: totalEntradas/totalSaidas/saldo (86-88); % por forma (378).
- **Bugs**: (i) reutiliza os mesmos named parameters (:ini, :fim, :assoc) no UNION e nas queries seguintes (64, 82, 104, 121) — só funciona com PDO em modo emulado, que é o default aqui (Database.php:10-19 não desativa emulação); (ii) o literal 'Saida' precisa casar com o que caixa grava (acoplamento silencioso); (iii) o % por forma divide por $totalEntradas, que inclui "Outras Entradas" (378) — não fecha 100% com o quadro de pagamentos.

---

## 10. CLASSES DE DOMÍNIO

### 10.1 classes/Associado.php (488 linhas)
- getTiposVinculo (23-32) lê associado_associacao.tipo_vinculo. create (39-135): e-mail único (46-52); password_hash (55); INSERT com 23 colunas (57); nivel 3 / tipo 'Usuario' / status 'pendente' (62-64); normalização de tipo_usuario (67-73); enums validados (76-85); vínculos N:M (120-127). getAll (141-172) com JOIN cidades. update (213-344): unicidade de e-mail (226-234) e CPF (237-245); whitelist de 25 colunas (251-276); vazios viram NULL para datas e campos UNIQUE (282-288); senha opcional (295-298); sentinela associacoes_enviado (302-323); ultima_atualizacao=NOW() (329). saveAssociacoes (398-444): DELETE total e reinserção com tipo_vinculo (410-429), mantendo associados.vinculo = primeira associação (433-437). getPresencas (469-486).

### 10.2 classes/Associacao.php (204 linhas)
- create (18-62, nome único), getAll (68-84, JOIN cidades/estados), getDetalhesCompletos (91-106), getById (113-122), update (130-179), delete (186-202, bloqueio por associados.vinculo).

### 10.3 classes/AssociadoAssociacao.php (144 linhas)
- Versão estática e **transacional** da gestão de vínculos: getByAssociado (15-33), getIdsByAssociado (40-44), getAssociadosByAssociacao (51-70), listAssociacoes (77-87, só status 'ativo'), save (95-133, com ON DUPLICATE KEY UPDATE data_vinculo, transação e atualização de vinculo/updated_at) e removeAll (140-143). **Não grava tipo_vinculo** (contraste com Associado::saveAssociacoes).

### 10.4 classes/User.php (557 linhas) — detalhada em 2.2.

### 10.5 classes/Carteirinha.php (87 linhas) — ver 12.1.

### 10.6 classes/CarteirinhaImpressa.php (292 linhas) — ver 12.3.

### 10.7 classes/Evento.php (232 linhas)
- getAllEventos (16-25); getPresencaEventos (31-52) com LEFT JOIN associados/usuarios; getPresencasByEvento (59-79) devolvendo chaves associado_id e 'u_'+usuario_id; atualizarPresencas (87-122) transacional, **só trata associados** e deleta quando o status é vazio ou 'Nenhum' (97-98); getPresencasUsuarioAgendados (132-230) que detecta dinamicamente a coluna justificativa via SHOW COLUMNS (154-162) e faz **fallback para o JSON eventos.confirmados** (196-214).

### 10.8 classes/Lembrete.php (197 linhas)
- getEventosProximos (17-50), getEventosHoje (56-80), getEventosAtrasados (86-109) — todas filtrando status='Agendado'; verificarLembretes (116-159) devolve 3 blocos; formatarDataEvento (166-183); diasParaEvento (190-195). Loga em error_log a cada consulta (23, 43, 59).

### 10.9 classes/ChecklistPadrao.php (114 linhas)
- Itens fixos com id >= 1000 (24-65), mapa() (71-78) e mesclar() (87-113) que evita duplicatas por texto normalizado. **Não toca banco**; pertence ao módulo de inspeção, sem vínculo direto com associados.

---

## 11. FICHA DE ADESÃO — FLUXO COMPLETO

### 11.1 Máquina de estados (FichaAdesao.php:19-22 + sql/create_ficha_adesao.sql:9-13)

    (inexistente) --salvar(rascunho)--> rascunho
    rascunho --submeter--> pendente
    recusada --salvar/submeter--> pendente (limpa parecer/analise: FichaAdesao.php:499-507)
    pendente --aprovar--> aprovada (terminal: prepararDados bloqueia edição, 417-419)
    pendente --recusar(motivo)--> recusada

Regras: aceite_termos obrigatório para submeter (436-440); obrigatórios na submissão = tipo_associado, nome_completo, data_nascimento, cpf, municipio, estado, telefone_whatsapp (443-448); máximo de **5** produtos (464-466); o modal reaparece a cada acesso até a aprovação (api/ficha_adesao.php:125).

### 11.2 Modal (includes/ficha_adesao_modal.php, 774 linhas)
- Incluído **globalmente** por includes/header.php:155-158; renderiza só se (user_table em {usuarios, associados} e nivel = NIVEL_USUARIO) ou sessão de portal (19-31).
- Formulário #formFichaAdesao (75-412) com os 7 blocos: 1 Identificação (100-162), 2 Endereço (164-218), 3 Contato (220-234), 4 Propriedade (236-284), 5 Produção agroecológica (286-359) com sistemas_producao[] de FichaAdesao::SISTEMAS_PRODUCAO (310-311), 5 produtos em produtos[i][...] (331-341) e canais_comercializacao[] (349-350), 6 Dados bancários (361-383), 7 Observações (385-386) + aceite LGPD (391-395).
- Botões: **Salvar rascunho** (417-419) e **Submeter para análise** (420-422).
- Segundo modal "Em Análise" (#modalFichaAguardando, 434-466) enquanto pendente, com link para visualizar (454-456).
- JS (477-771): máscaras CPF/telefone/CEP (511-541); preencherForm (564-631); normalizarDecimais (633-639); validarSubmissao (641-668) exige 7 campos + 11 dígitos de CPF + aceite; salvarFicha (670-708) faz fetch POST multipart (sem header CSRF explícito — depende do wrapper global de header.php:110-131); GET ?action=status no DOMContentLoaded (734-763) e controle por sessionStorage (481, 710-717, 740-743).

### 11.3 API (api/ficha_adesao.php, 213 linhas)
- **GET ?action=status** (80-131): sem sessão devolve mostrar:false/status:'aprovada' (85-87); resolve a ficha por associado ou por conta (91-93); devolve ficha, produtos e usuario para prefill (97-121).
- **POST ?action=salvar|submeter** (142-191): CSRF obrigatório (138-140); contaDaSessao() (37-71) decide entre usuario_id e associado_id; chama salvarPorUsuario ou salvar (156-158); ao submeter numa ficha nova/rascunho/recusada dispara notificarNovaFichaPendente (161-188).
- **POST ?action=aprovar|recusar** (193-211): exige podeAcessarDiretoria() (195-197) — regra **diferente** da tela ficha_adesao_visualizar.php:31 (Admin/Diretoria Executiva).
- Chamada direta em /api/ficha_adesao.php (não há rota no router).

### 11.4 Persistência (classes/FichaAdesao.php)
- salvar (357-374) → prepararDados (415-471) → gravarFicha (477-566: transação, INSERT/UPDATE dinâmico, regravação dos produtos 530-548).
- Leituras: getByAssociadoId (133-144), getById com produtos (150-164), getByUsuarioId (216-231), statusDoAssociado/Usuario (199-210 / 236-247), associadoIdDoUsuario/usuarioIdDoAssociado (250-269 / 272-291) com join nos dois sentidos.

### 11.5 Aprovação (FichaAdesao::aprovar, 703-799)
1. numero = 'AAES-' + str_pad(id, 5) (714). 2. Transação (717). 3. Ficha de pedido de acesso (associado_id=0 e usuario_id>0) → criarAssociadoDaFicha (723-728). 4. Anti-duplicidade: se o associado já tem outra conta vinculada → MSG_CPF_JA_CADASTRADO (734-740; texto em 115). 5. UPDATE ficha_adesao (status aprovada, numero_cadastro, data_entrada=CURDATE(), data_analise=NOW(), analisado_por, associado_id) 742-754. 6. UPDATE associados (data_admissao, status_associado='Ativo', status='ativo', ficha_enviada=1, ficha_aprovada=1, ficha_aprovada_em/por) 757-771. 7. Vínculo nos dois lados (usuarios.associado_id e associados.user_id) 773-777. 8. Rollback com mensagens de negócio; PDOException não vaza SQL (781-798).
- criarAssociadoDaFicha (810-920): reaproveita cadastro existente por CPF normalizado (821-839); se já existe ficha para o CPF → erro de negócio (832-836); mapas agricultor_familiar/colaborador → Efetivo, honorario → Honorario, institucional → Benemerito (850-855) e ocs→OCS, opac→SPG, auditagem→AUDITORIA, nao_possui→SPG (857-863); e-mail do cadastro só se livre (866-873); INSERT completo copiando nível/senha/área da conta (878-917).
- Recusa (926-954): motivo obrigatório; UPDATE condicionado a status <> 'aprovada'.
- Notificações (635-690): INSERT em notificacoes para Admins ativos (tipo_usuario 'sistema') e para associados ativos com tipo_vinculo de diretoria (tipo_usuario 'associado'), com dados_adicional JSON.

### 11.6 Visualização e PDF (modulos/associados/ficha_adesao_visualizar.php, 731 linhas)
- **Rota**: index.php:634-637 (/cadastro-ficha/visualizar), em modal (?modal=1, 22, 135-143) ou página inteira; suporta ?pdf=1 (23, 123-126).
- **Permissão** (66-85): PortalAccess::podeConsultarOutros() (Admin/Diretoria Executiva) **ou** dono da ficha por associado_id/usuario_id resolvido na sessão; senão "Acesso negado".
- **POST de avaliação** (28-43): exige Admin/Diretoria Executiva + CSRF; chama aprovar/recusar.
- **Render**: cabeçalho AAES/RS (187-191), badge de status (193-205), parecer quando recusada (207-212), 7 seções via helper linha() (110-116), assinaturas e aceite LGPD (354-384), botões Aprovar/Recusar (386-397 e, no modal, 410-419).
- **PDF** (472-726): DomPDF com isRemoteEnabled (477-482), A4 retrato, nome Ficha_Adesao_<numero_cadastro|ID>_<Ymd>.pdf (490-491).
- **Bugs**: (i) includes/footer.php incluído **duas vezes** quando não é modal (464-466 e 729-731); (ii) o avaliador é buscado por analisado_por em associados e, se não achar, em usuarios (92-107) — id ambíguo entre as duas tabelas; (iii) funções gerarPDFFicha/gerarHTMLPDFFicha declaradas no fim e chamadas antes (124, 472-726) — funciona por hoisting, mas gera fatal redeclare se o arquivo for incluído duas vezes.

### 11.7 Entrada do pedido de acesso (api/pre_cadastro.php, 166 linhas)
- Aceita nome, sobrenome, email, senha, confirma_senha, perfil (vegetal|animal|agroindustria|mista|gestor) ou o legado tipo_usuario + area_producao[] (18-60).
- Valida nome/sobrenome/e-mail/senha >= 6/confirmação/perfil (69-79); e-mail único nas **duas** tabelas (89-101).
- **Sempre grava em usuarios**, nunca em associados: gestor → nível 2, tipo 'gestor', status 'pendente' (112-127); demais → nível 3, tipo 'usuario', tem_agroindustria e area_producao, status 'pendente' (136-153), com nivel_solicitado e perfil_solicitado.
- **Gap**: sem CSRF e sem rate-limit em endpoint público.

---

## 12. CARTEIRINHA — GERAÇÃO, PDF, E-MAIL, QR CODE, ROTA PÚBLICA

### 12.1 Modelo (classes/Carteirinha.php)
- getByAssociado (14-24): última carteirinha. getByToken (26-43): JOIN com associados trazendo nome, foto, nascimento, tipo/status, CNPO e CPF. gerar (48-71): validade default +1 ano (52); token bin2hex(random_bytes(24)) (53); **UPDATE na mesma linha** quando já existe (55-59) → **um único token válido por associado**, invalidando QRs antigos silenciosamente. validaToken (73-86): inválida por não encontrada, vencida (79-81) ou status_associado em {Desligado, Falecido} (82-84).

### 12.2 Rotas e telas
| URL | Arquivo | Permissão | Observação |
|---|---|---|---|
| GET /associados/carteirinhas | carteirinha_listar.php | podeAcessarDiretoria() (11-14) | busca por nome/e-mail (24-29); financeiro e carteirinha por associado (34-38) |
| GET /associado/<encoded>/carteirinha-gerar | carteirinha_gerar.php | hasPermission(NIVEL_GESTOR) (12-15) | **gera em GET** (32); valida CSRF só se método POST (24-30) |
| GET /associado/<encoded>/carteirinha-pdf | carteirinha_pdf.php | ApiAuth::exigirLogin + exigirProprioAssociado (17,26) | mPDF formato 100x160 (113-119); QR por api.qrserver.com (39-43) |
| GET /associado/<encoded>/carteirinha-impressa | carteirinha_impressa.php | idem (15,23) | PDF A5 paisagem via CarteirinhaImpressa |
| POST /associado/<encoded>/carteirinha-enviar | carteirinha_enviar.php | hasPermission(NIVEL_GESTOR) (13-16) + POST + CSRF (24-32) | HTML + anexo base64 (57-76) via Mailer::send |
| POST /modulos/associados/carteirinhas_enviar_massa.php | carteirinhas_enviar_massa.php | Gestor (12-15) + POST + CSRF (17-25) | loop por associado (41-91); resumo com sem-e-mail/erros (93-105) |
| GET /carteirinha/<token> | carteirinha_publica.php | **pública** | ver 12.4 |

### 12.3 Geração do PDF completo (classes/CarteirinhaImpressa.php)
- getDados (30-83): busca associado, vínculos, resumo financeiro e histórico; **gera a carteirinha se não existir** (45-48) — efeito colateral de GET; situacaoFinanceira = 'Inadimplente' quando qtd_em_aberto > 0 (51); foto em base64 com mime detectado (55-61); **QR remoto** com SITE_URL + '/carteirinha/' + token (63) baixado de api.qrserver.com (65-68).
- buildHtml (105-269): layout A5 paisagem com faixa verde/amarelo/laranja, foto 30x40mm, dados (CPF, CNPO, RG, nascimento, categoria, profissão, admissão, contato, e-mail, cidade/UF + siglas das associações), bloco de situação (cadastral + financeira + até 3 pendências) e QR com validade (241-259).
- gerarPdf (274-291): mPDF A5-L sem margens, Output('', 'S').
- Riscos: token exposto a serviço externo (65); geração/rotação de token em GET; sem checagem de status antes de emitir.

### 12.4 Rota pública de validação (carteirinha_publica.php, 107 linhas)
- Router: index.php:664-668 (/carteirinha/<token> → $_GET['token']).
- Valida pelo token (15-16); responde HTTP 404 se inválida (18-20) **mas ainda renderiza** o cartão com o motivo (29-30, 49-52).
- Exibe foto, nome, tipo, CNPO, nascimento, badge VÁLIDA/INVÁLIDA e validade (54-86); o QR é gerado **no cliente** com qrcodejs apontando para a própria URL (88-101), sem novo fetch ao servidor.
- Gap/LGPD: página pública expõe nome, foto, CNPO, data de nascimento e situação para quem tiver o link/token.

### 12.5 Envio por e-mail
- Individual (carteirinha_enviar.php): gera o PDF, monta HTML (57-62), envia anexo base64 carteirinha_<id>.pdf (64-76), grava mensagem de sessão (78-82) e redireciona para /associado/<id>/ficha (84) — **rota inexistente** (achado #2).
- Massa (carteirinhas_enviar_massa.php): valida lista (27-32), envia um a um (41-91), agrega resultado (93-105) e volta para /associados/carteirinhas (107).

---

## 13. MÁQUINAS DE ESTADO (consolidado)

### 13.1 Cadastro do associado (associados.status + associados.status_associado)

    [pendente] --ativar (pendentes.php:70)--> [ativo] --inativar--> [inativo]
                  --rejeitar--> [inativo]
    status_associado (independente): Ativo | Inadimplente | Afastado | Desligado | Falecido

- Quem ativa: **somente Admin** em /associados/pendentes (pendentes.php:7-11, 62-77), por UPDATE direto (70) — **sem criar conta**, sem notificar e sem auditar.
- O status de **login** é associados.status = 'ativo' (login.php:99; User.php:68).
- A aprovação da ficha força status='ativo' e status_associado='Ativo' (FichaAdesao.php:757-771): aprovar a ficha também ativa o login.
- status_associado só é editável no formulário de cadastro (cadastrar.php:164-170) e via SQL/model.

### 13.2 Aprovação de acesso (usuarios.status)

    (pré-cadastro público, api/pre_cadastro.php) --> usuarios.status='pendente'
       com nivel_solicitado e perfil_solicitado (pre_cadastro.php:107-153)
    pendente --Admin confirma (AcessoPendente::confirmarPrivilegios)--> 'ativo'
              nivel = nivel_concedido; tipo = admin|gestor|usuario;
              acesso_confirmado_por/em; AUDIT ACESSO_CONFIRMADO (AcessoPendente.php:231)
    pendente --Admin nega (negarSolicitacao, motivo obrigatório)--> 'inativo'
              acesso_negado_motivo; AUDIT ACESSO_NEGADO (AcessoPendente.php:296)

- Somente Admin concede (AcessoPendente.php:81-88, 165-167); níveis válidos 1/2/3 (33); o concedido pode ser **maior** que o solicitado (= menos privilégio) com mensagem explicativa (236-242); rowCount()==0 protege corrida (221-223).
- Também entra em pendente via modulos/associados/cadastrar.php, mas com status default 'ativo' (achado #5).
- Não existe fluxo de reativação de conta 'inativo' pela UI.

### 13.3 Ficha de adesão — ver 11.1.

### 13.4 Evento (eventos.status)

    Agendado --(só por edição manual)--> Realizado | Cancelado

- Não há transição automática; Lembrete trata como "atrasado" todo evento Agendado com data passada (Lembrete.php:86-109).
- Presença: dual-write em eventos.confirmados (JSON) + evento_presenca (api/toggle_presenca.php:92-114); a tabela admite Confirmado/Presente/Ausente/Justificado (migration.sql:772) enquanto o JSON só conhece 'Confirmado'.

### 13.5 Assembleia

    rascunho --(>=1 pauta)--> [publicar: POST+CSRF] --> ativa
    ativa --[encerrar: POST+CSRF]--> encerrada (resultado derivado, não congelado)
    cancelada: previsto no ENUM (migration.sql:272) e no filtro (listar.php:78), mas NENHUM código grava

Elementos: pautas (aprovacao | eleicao | outros) com opções JSON; votos JSON por pauta com UNIQUE por (assembleia, pauta, associado); quórum mínimo por pauta vs. associados aptos; janela temporal como segunda trava (visualizar.php:96).

---

## 14. GAPS, INCONSISTÊNCIAS E BUGS CONSOLIDADOS

### 14.1 Roteamento e navegação
1. ficha.php sem rota e destino de 7 redirects → 404.
2. eventos/acompanhar_presenca.php sem rota e sem link (órfão).
3. associacoes/excluir.php e usuarios/excluir.php sem rota (alcançáveis por POST direto).
4. eventos/relatorio_presenca.php e carteirinha_*.php são chamados por caminho de arquivo, não pelo router.
5. eventos/visualizar.php é endpoint JSON mas tem rota navegável (index.php:771-775) → browser recebe 403.
6. encodeId/decodeId: só ficha.php gera links codificados; os demais usam id cru nas rotas que decodificam (achado #1). A chave default de encodeId é 'troque-esta-chave-em-producao' se não houver env (config/config.php:51-53).

### 14.2 Autorização
7. associacoes/listar.php (8-12) e associacoes/cadastrar.php (11-15) só exigem login, apesar do comentário.
8. eventos/relatorio_presenca.php exige apenas login (8-10) e expõe nome/e-mail de toda a base.
9. usuarios/excluir.php permite que Gestor exclua Admin (6-10).
10. O widget de pendentes do dashboard (index.php:1215-1228) mostra o aviso para Gestor+, mas pendentes.php é Admin-only (7-11).
11. usuarios/listar.php:18-21 redireciona nível 3 para rota que exige Gestor (usuarios/editar.php:7-11) → acesso negado.
12. Menu "Cadastros Pendentes"/"Todas as Fichas" para toda a Diretoria (menu.php:33,46) x guarda restrita à Diretoria Executiva (cadastros_pendentes.php:20; fichas_completas.php:19).
13. Aprovar/recusar ficha tem regras diferentes entre api/ficha_adesao.php:195 (podeAcessarDiretoria, inclui conselho fiscal/comissões) e ficha_adesao_visualizar.php:31 (só Admin/Diretoria Executiva).

### 14.3 CSRF / integridade de escrita
14. Sem CSRF: eventos/editar.php; associacoes/cadastrar.php; associacoes/editar.php; usuarios/perfil.php; usuarios/salvar_notificacoes.php; api/pre_cadastro.php; assembleia/editar.php (tem require mas não valida).
15. carteirinha_gerar.php gera carteirinha em GET (32) e só valida CSRF se o método for POST (24-30); o link da UI é GET (ficha.php:85).

### 14.4 Modelo de dados / semântica
16. associados.estado CHAR(2) recebendo id numérico (formulários), UF (ficha) e filtro '23' (relatório) — achado #4.
17. associados.cidade VARCHAR(100) recebendo id (selects) e nome (ficha) — quebra Associado::getAll (JOIN por id, Associado.php:162) e o relatório por município (ativos_municipio.php:49).
18. usuarios.nivel DEFAULT 1 (migration.sql:1493): INSERT que esqueça nivel cria **Admin**.
19. Duplicação de identidade em associados/cadastrar.php (91-103), contra o modelo "uma pessoa = um cadastro + no máximo uma conta" implementado em FichaAdesao::aprovar (773-777) e usuarios/cadastrar.php (99-103).
20. eventos.confirmados (JSON) e evento_presenca (tabela) como fontes concorrentes de presença.
21. evento_presenca sem FK para eventos (migration.sql:767-779) → exclusão de evento deixa órfãos.
22. carteirinhas sem FK e sem histórico (create_associacao_financeiro.sql:44-53).

### 14.5 Regras de negócio
23. fichas_completas.php sem handler POST (achado #3) e com INNER JOIN que esconde fichas de pedido de acesso.
24. Assembleia "ponderada" inexistente (achado #8); status 'cancelada' inalcançável.
25. Votação restrita à sessão do Portal (achado #7) e sem exigir resposta a todas as pautas.
26. resultado.php não declara aprovado/rejeitado (só quórum + contagens).
27. salvar_notificacoes.php sempre grava em associados (28-34), ignorando user_table.
28. editar.php (associados) testa $_SESSION['user_tipo'] === 'Admin' (85) contra ENUM minúsculo de usuarios → Admin não altera status.
29. meu_perfil.php converte estado de UF para id no save (425, 427).
30. Evento editado perde a hora (input date em DATETIME; editar.php:83).

### 14.6 Performance
31. N+1 em associados/listar.php:171; carteirinha_listar.php:34-37; inadimplentes_micro.php:34-75; acompanhar_presenca.php:52-74.
32. associacoes/associados.php:66-99 sem transação e com subquery NOT IN (92).

### 14.7 Segurança / LGPD
33. Token de carteirinha enviado a api.qrserver.com (CarteirinhaImpressa.php:65-68; carteirinha_pdf.php:39-43).
34. carteirinha_publica.php expõe nome, foto, CNPO, nascimento e situação em rota pública (54-86).
35. XSS potencial em associacoes/listar.php:147-200 (innerHTML sobre dados do banco).
36. Log verboso em produção (Lembrete.php:23,43,59; widget_eventos.php:27-29; eventos/visualizar.php:12-44).
37. Erros de PDO exibidos ao usuário (usuarios/cadastrar.php:113; usuarios/editar.php:182; usuarios/perfil.php:92; associacoes/*).

---

## 15. Tabelas tocadas por tela (índice rápido)

| Tela | Tabelas |
|---|---|
| /associados (listar) | associados, cidades, ficha_adesao |
| /associados/pendentes | associados, usuarios, audit_log, notificacoes |
| /cadastros-pendentes | ficha_adesao, associados, usuarios, notificacoes, audit_log |
| /fichas-completas | ficha_adesao, associados |
| /associado/novo | associados, usuarios, associado_associacao |
| /associado/<id>/editar | associados, associado_associacao, cidades, estados |
| /associado/<id>/excluir (POST) | associados (+cascatas), propriedades, atividades_diarias |
| /meu-perfil | associados, usuarios, associado_associacao, cidades, estados |
| /associados/relatorio | POST para api/relatorios/relatorio_associados.php |
| /associados/carteirinhas | associados, mensalidades, carteirinhas |
| /carteirinha/<token> | carteirinhas, associados |
| /associacoes | associacoes, cidades, estados |
| /associacao/associados | associado_associacao, associados, associacoes |
| /usuarios, /usuario/novo, /usuario/<id>/editar | usuarios, associados, audit_log |
| /usuario/perfil, /usuario/configuracoes, /usuario/salvar_notificacoes | usuarios/associados (perfil), associados (notificações) |
| /eventos, /eventos/presencas | eventos, evento_presenca, associados, usuarios |
| /evento/novo, /evento/<id>/editar, /evento/<id>/excluir | eventos, evento_presenca, associados |
| /evento/<id> (AJAX) | eventos (+confirmados JSON), associados, usuarios |
| /assembleia, novo, editar, pautas, publicar, encerrar | assembleias, assembleia_pautas, usuarios (created_by) |
| /assembleia/<id> (votar) | assembleias, assembleia_pautas, assembleia_votos, associado_associacao |
| /assembleia/<id>/resultado | assembleias, assembleia_pautas, assembleia_votos, associados, associado_associacao |
| /relatorios_diretoria/ativos_municipio | associados, cidades, microrregioes_rs, mesorregioes_rs |
| /relatorios_diretoria/inadimplentes_micro | mensalidades (+associados/cidades), microrregioes_rs, mesorregioes_rs |
| /relatorios_diretoria/entradas_saidas | pagamentos, caixa, associacoes |
| /api/ficha_adesao.php | ficha_adesao, ficha_adesao_produtos, associados, usuarios, notificacoes, audit_log |
| /api/pre_cadastro.php | usuarios |
| /api/toggle_presenca.php | eventos, evento_presenca, usuarios |
| /api/get_presenca_evento.php | eventos, evento_presenca, associados, usuarios |
| /api/get_associacao_detalhes.php | associacoes, cidades, estados |

---

## 16. Correções mínimas recomendadas (ordem de impacto)

1. Rotear /associado/<id>/ficha para modulos/associados/ficha.php (index.php, junto de 683-699) — conserta 7 redirects e a única UI de carteirinha funcional.
2. Padronizar ids de carteirinha: usar encodeId() em carteirinha_listar.php:135,137,180 e portal/area.php:252,260 (ou aceitar id cru no router).
3. Implementar o POST de aprovar/recusar em fichas_completas.php (reutilizando cadastros_pendentes.php:32-51) e trocar INNER JOIN por LEFT JOIN com COALESCE.
4. Unificar estado/cidade (UF + nome ou FK + id), migrar os dados mistos e ajustar o filtro do relatório por município.
5. Remover a criação de usuarios em associados/cadastrar.php (ou torná-la opcional e explícita, com status coerente).
6. Adicionar CSRF em eventos/editar, associacoes/cadastrar|editar, usuarios/perfil, usuarios/salvar_notificacoes, assembleia/editar, api/pre_cadastro; exigir POST em carteirinha_gerar.
7. Permitir votar a partir da sessão user_table='associados' (assembleia/visualizar.php:46) via User::getAssociadoIdResolvido().
8. Alinhar menu x guardas (menu.php:33/46 x cadastros_pendentes.php:20) e a regra de aprovação entre api/ficha_adesao.php:195 e ficha_adesao_visualizar.php:31.
9. Gerar o QR localmente (biblioteca server-side) para não enviar o token a terceiros.
10. Corrigir salvar_notificacoes.php para respeitar $_SESSION['user_table'].
