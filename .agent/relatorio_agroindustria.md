# Relatório de Engenharia Reversa — Módulo AGROINDÚSTRIA (PHP legado / XAMPP / MySQL)

Convenções: todas as referências são 'caminho:linha' ou 'caminho:linha-linha', verificadas por leitura integral dos arquivos. Quando algo não existe no código, está marcado explicitamente como NÃO ENCONTRADO NO CÓDIGO.

---

# 0. Contexto transversal (roteamento, guards, helpers) — verificado no código

## 0.1 Roteamento (roteador único 'index.php', URL amigável via $_GET['url'])

| Rota | Arquivo | Linhas |
|---|---|---|
| /agroindustria/minhas | modulos/agroindustria/minhas_listar.php | index.php:920-922 |
| /agroindustria/minha/nova | modulos/agroindustria/minhas_cadastrar.php | index.php:923-925 |
| /agroindustria/minha/{id}/editar | modulos/agroindustria/minhas_editar.php ($_GET['id']=parts[2]) | index.php:926-928 |
| /agroindustria/setores | modulos/agroindustria/setores_listar.php | index.php:929-931 |
| /agroindustria/setor/novo | modulos/agroindustria/setores_cadastrar.php | index.php:932-934 |
| /agroindustria/setor/{id}/editar | modulos/agroindustria/setores_editar.php | index.php:935-937 |
| /agroindustria/categorias | modulos/agroindustria/categorias_listar.php | index.php:938-940 |
| /agroindustria/categoria/novo | modulos/agroindustria/categorias_cadastrar.php | index.php:941-943 |
| /agroindustria/categoria/{id}/editar | modulos/agroindustria/categorias_editar.php | index.php:944-946 |
| /agroindustria/atividades | modulos/agroindustria/atividades_listar.php | index.php:947-949 |
| /agroindustria/atividade/novo | modulos/agroindustria/atividades_cadastrar.php | index.php:950-952 |
| /agroindustria/atividade/{id}/editar | modulos/agroindustria/atividades_editar.php | index.php:953-955 |
| /agroindustria/ocorrencias | modulos/agroindustria/ocorrencias_listar.php | index.php:956-958 |
| /agroindustria/ocorrencia/novo | modulos/agroindustria/ocorrencias_cadastrar.php | index.php:959-961 |
| /agroindustria/ocorrencia/{id}/editar | modulos/agroindustria/ocorrencias_editar.php | index.php:962-964 |
| /agroindustria/receitas | modulos/agroindustria/receitas_listar.php | index.php:965-967 |
| /agroindustria/receitas/nova | modulos/agroindustria/receitas_cadastrar.php | index.php:968-970 |
| /agroindustria/receitas/{id}/editar | modulos/agroindustria/receitas_editar.php | index.php:971-973 |
| /agroindustria/higienizacao | modulos/agroindustria/higienizacao_listar.php | index.php:974-976 |
| /agroindustria/higienizacao/nova | modulos/agroindustria/higienizacao_cadastrar.php | index.php:977-979 |
| /agroindustria/ordens | modulos/agroindustria/ordens_listar.php | index.php:980-982 |
| /agroindustria/ordens/nova | modulos/agroindustria/ordens_cadastrar.php | index.php:983-985 |
| /agroindustria/ordens/{id} | modulos/agroindustria/ordens_visualizar.php | index.php:986-988 |
| /agroindustria/rastreabilidade[...] | modulos/agroindustria/rastreabilidade.php | index.php:989-991 |

Rotas do módulo NÃO passam por nenhum middleware de permissão no roteador: cada arquivo implementa o próprio guard (index.php:919-991).
Menu lateral (visível apenas se a área de produção incluir 'agroindustria'): includes/menu.php:118-137 (guarda 'temAreaAgro' em menu.php:19); links das telas: menu.php:125 (minhas), 127 (setores), 128 (categorias), 129 (atividades), 130 (ocorrências), 132 (receitas), 133 (higienização), 134 (ordens), 135 (rastreabilidade).

## 0.2 Bootstrap de sessão e permissão

- config: NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3 (config/config.php:23-25).
- includes/header.php:2-5 -> require config + User, instancia '$user = new User()'.
- includes/header.php:13-19 -> se não logado (e sem flag de exceção) redireciona para SITE_URL/login e encerra. Isso significa que qualquer arquivo que já incluiu header.php tem o guard de sessão cumprido dentro do header.
- User::isLoggedIn() -> isset($_SESSION['user_id']) (classes/User.php:106-108).
- User::hasPermission($nivel) -> exige 'user_nivel' numérico >0; admin (1) passa sempre; retorna $nivel <= $required_level (classes/User.php:114-141).
- User::isGestorOuAdmin() (User.php:147-149).
- User::temAgroindustria() -> true para ADMIN/GESTOR; true se tipo_usuario in ('produtor_agroindustria','agroindustria'); true se associados.tem_agroindustria=1; senão COUNT(*) em agroindustrias por associado_id (User.php:177-200).
- User::getAssociadoId() -> id do associado logado (tabela associados) ou currentUser['associado_id'] (User.php:259-272).
- User::getAssociadoIdResolvido() -> int, cobre sessão do portal, tabela associados, usuarios.associado_id e associados.user_id (User.php:299-329).
- Já existia correção de escalonamento de privilégio em hasPermission (comentário B-04 em User.php:119-133).

## 0.3 Helpers usados pelos models

- classes/Sanitizer.php:15-21 sanitizeString = strip_tags + trim + remoção de caracteres de controle; sanitizeArray:119-131 aplica em cada escalar. Usado por Agroindustria, AgroSetor, AgroCategoriaAtividade, AgroAtividadeDiaria, AgroOcorrencia. NÃO usado por ReceitaAgroindustria, OrdemProducao e ControleHigienizacao (entrada crua do POST).
- classes/CSRFProtection.php:15 getTokenField(); :24 validatePostRequest().
- classes/AuditLog.php:13 registrar($acao, $tabela=null, $registro_id=null, $descricao=null).
- classes/Database.php:10-19 conexão PDO mysql, ERRMODE_EXCEPTION; ATTR_EMULATE_PREPARES NÃO é definido (default do driver mysql é emular) — relevante porque AgroSetor::getSetoresDoAssociado:55-57 repete o placeholder nomeado ':assoc' duas vezes.

## 0.4 Regra de negócio/regulatória referenciada no código

- modulos/TODO_Agroind.md:56-66 (documento de especificação, não é código em execução): RF07 receitas, RF08 higienização prévia obrigatória, RF09 percentual orgânico (>=95% selo "Produto Orgânico Brasil"; 70-95% frase "ingredientes orgânicos"; <70% bloqueia menção a orgânico), RF10 vínculo campo-agroindústria, RF11 balanço de massas com alerta de fraude, RF12 QR Code de lote.
- A portaria citada no código é a Portaria 52/2021 (e Portaria SDA 811/2023) em classes/ControleHigienizacao.php:52-57 e modulos/agroindustria/higienizacao_cadastrar.php:38,88-89.

---

# 1. modulos/agroindustria/

### modulos/agroindustria/setores_listar.php
- **Rota/acesso**: GET /agroindustria/setores (index.php:929-931); link de menu em includes/menu.php:127. Guard: require config+User (3-4); isLoggedIn -> redirect /login (10-13); temAgroindustria -> alerta e exit (15-19). 'AgroSetor' e 'AuditLog' requeridos (5-6).
- **Propósito de negócio**: listar os setores da agroindústria que substituem as lavouras do Caderno de Campo (texto explicativo em 46: recebimento, processamento, embalagem, expedição).
- **Campos/formulário**: nenhum formulário. Única entrada é 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: filtro da listagem = 'null' para gestor, senão getAssociadoId() (32). Sem paginação e sem filtro por agroindústria/unidade na tela (o model suporta associado_id apenas).
- **Tabelas SQL usadas** (via classes/AgroSetor.php): SELECT * FROM agro_setores WHERE excluido = 0 [AND associado_id = ?] ORDER BY nome ASC (AgroSetor.php:14-22); UPDATE agro_setores SET excluido = 1 WHERE id = :id (AgroSetor.php:110).
- **Ações/POST/redirects**: exclusão por GET -> softDelete + AuditLog::registrar('AGRO_SETOR_EXCLUIDO','agro_setores',$id,...) (26) -> header Location /agroindustria/setores (28).
- **Riscos/gaps**: (1) IDOR destrutivo — a exclusão em 23-30 não verifica dono, nível nem vínculo com o usuário: qualquer sessão autenticada com temAgroindustria() apaga setor de qualquer associado; (2) exclusão por GET sem CSRF e sem confirmação server-side (a confirmação é só 'onclick=confirm' em 69); (3) o retorno de softDelete é checado (25) mas AuditLog é registrado em caso de sucesso sem informar qual produtor era dono; (4) não há bloqueio de exclusão quando existem agro_atividades_diarias/agro_ocorrencias apontando para o setor (tabelas sem FK).

### modulos/agroindustria/setores_cadastrar.php
- **Rota/acesso**: GET /agroindustria/setor/novo (index.php:932-934); link em setores_listar.php:41. Guard: isLoggedIn (12-15); temAgroindustria (17-21). Requires: AgroSetor, Agroindustria, Associado, AuditLog (5-8).
- **Propósito de negócio**: cadastrar um setor e, opcionalmente, vinculá-lo a uma unidade (agroindústria) e a um associado.
- **Campos/formulário**: 'nome' (text, required) linha 71; 'associado_id' (select) 75-82 — populado com todos os associados (gestor) ou apenas o próprio (30); 'agroindustria_id' (select, opcional) 86-93 — de Agroindustria::getAll(gestor?null:associadoId) (29); 'descricao' (textarea) 98.
- **Regras/validações/cálculos**: nome obrigatório (37-39) -> 'Informe o nome do setor.'; 'associado_id' = POST se preenchido, senão o da sessão (41); nenhuma validação de que 'agroindustria_id' pertence ao associado informado.
- **Tabelas SQL usadas**: INSERT INTO agro_setores (uuid, associado_id, agroindustria_id, nome, descricao, criado_em) VALUES (:uuid,...,NOW()) (AgroSetor.php:75-83); SELECT * FROM agroindustrias ... (Agroindustria.php:19-32); associados via Associado::getAll/getById (30).
- **Ações/POST/redirects**: POST -> create (42) -> AuditLog 'AGRO_SETOR_CADASTRADO' com registro_id null (43) -> redirect /agroindustria/setores?cadastrado=1 (44).
- **Riscos/gaps**: (1) 'associado_id' vem do POST (41) -> escrita cross-tenant: um usuário nível 3 pode criar setor atribuído a outro associado; (2) 'agroindustria_id' aceito sem validação de posse (não há FK em agro_setores — dev-utils/migrate_agro_producao.php:65-76 define apenas índices); (3) AuditLog sem ID do registro criado (43) porque AgroSetor::create retorna bool (AgroSetor.php:84) — rastro de auditoria fraco; (4) AgroSetor::create usa bindParam(':nome'/'descricao') sem null-coalesce (AgroSetor.php:82-83) -> POST forjado sem essas chaves gera warning PHP 8 e grava NULL em coluna NOT NULL (nome); (5) sem CSRF; (6) htmlspecialchars($ag['nome_fantasia']) em 90 sem ?? -> nome_fantasia NULL (permitido: Agroindustria.php:174) gera deprecation no PHP 8.1 e opção vazia.

### modulos/agroindustria/setores_editar.php
- **Rota/acesso**: GET /agroindustria/setor/{id}/editar (index.php:935-937). Guards: isLoggedIn (11-14), temAgroindustria (16-20), id>0 (22-26), existência do setor (33-37), anti-IDOR 39-43: se não gestor e setor.associado_id != associadoId -> mensagem 'Você não tem permissão para editar este setor.' e exit.
- **Propósito de negócio**: alterar nome, unidade vinculada e descrição do setor.
- **Campos/formulário**: 'nome' (text, required) 84; 'agroindustria_id' (select) 88-95; 'descricao' (textarea) 99. O formulário NÃO envia associado_id.
- **Regras/validações/cálculos**: nome obrigatório (51-53); sem validação de posse da agroindústria selecionada; após erro, '$setor = array_merge($setor,$_POST)' (62) apenas para repopular.
- **Tabelas SQL usadas**: SELECT * FROM agro_setores WHERE id=:id AND excluido=0 (AgroSetor.php:32); UPDATE agro_setores SET agroindustria_id=:agroindustria_id, nome=:nome, descricao=:descricao WHERE id=:id AND excluido=0 (AgroSetor.php:95) — não altera associado_id nem uuid.
- **Ações/POST/redirects**: POST -> update (54) -> AuditLog 'AGRO_SETOR_ATUALIZADO' (55) -> redirect /agroindustria/setores?atualizado=1 (56).
- **Riscos/gaps**: sem CSRF; bindParam sem ?? em AgroSetor.php:98-99 (nome vazio é barrado na tela, mas descricao pode vir ausente); comparação frouxa '==' em 91; o anti-IDOR usa getAssociadoId() enquanto o módulo de ordens usa getAssociadoIdResolvido() (User.php:299) — critérios diferentes de dono no mesmo módulo.

### modulos/agroindustria/categorias_listar.php
- **Rota/acesso**: GET /agroindustria/categorias (index.php:938-940); menu.php:128. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: listar categorias usadas para classificar as atividades diárias (46).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: listagem SEM filtro de associado — getAll() (32).
- **Tabelas SQL usadas**: SELECT id, nome, descricao, cor FROM agro_categorias_atividades WHERE excluido=0 ORDER BY nome ASC (AgroCategoriaAtividade.php:14); UPDATE ... SET excluido=1 (76).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'AGRO_CATEGORIA_EXCLUIDA' (26) -> redirect /agroindustria/categorias.
- **Riscos/gaps**: (1) IDOR destrutivo sem checagem (23-30) — permite excluir inclusive as categorias GLOBAIS (associado_id NULL, criadas pelo seed dev-utils/migrate_agro_producao.php:100-112) que o próprio módulo proíbe editar (categorias_editar.php:37-43) -> contradição direta entre guard de edição e de exclusão; (2) 'cor' é gravada sem validação (AgroCategoriaAtividade.php:49) e injetada em style="background-color:..." (66) — htmlspecialchars impede quebra de atributo mas permite CSS arbitrário; (3) sem CSRF; (4) getAll() global expõe categorias criadas por outros produtores.

### modulos/agroindustria/categorias_cadastrar.php
- **Rota/acesso**: GET /agroindustria/categoria/novo (index.php:941-943); link categorias_listar.php:41. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: cadastrar categoria de atividade.
- **Campos/formulário**: 'nome' (text, required) 63; 'cor' (input type=color) 67; 'descricao' (textarea) 71.
- **Regras/validações/cálculos**: nome obrigatório (28-30); 'associado_id' forçado para o da sessão (32) — nem gestor pode criar para outro associado; 'cor' default '#0d6efd' (33).
- **Tabelas SQL usadas**: INSERT INTO agro_categorias_atividades (uuid, associado_id, nome, descricao, cor, criado_em) VALUES (..., NOW()) (AgroCategoriaAtividade.php:42-49).
- **Ações/POST/redirects**: create (34) -> AuditLog 'AGRO_CATEGORIA_CADASTRADA' com id null (35) -> redirect /agroindustria/categorias?cadastrada=1.
- **Riscos/gaps**: 'cor' sem validação de formato (hex) em nenhuma camada; uuid gerado com mt_rand (AgroCategoriaAtividade.php:85-93) — previsível (baixo impacto: o uuid não é usado em nenhuma rota do módulo); sem unicidade de nome (nenhum UNIQUE no schema, dev-utils/migrate_agro_producao.php:87-97); sem CSRF; AuditLog sem ID.

### modulos/agroindustria/categorias_editar.php
- **Rota/acesso**: GET /agroindustria/categoria/{id}/editar (index.php:944-946). Guards: isLoggedIn (10-13), temAgroindustria (15-19), id (21-25), existência (28-32), anti-IDOR 34-43.
- **Propósito de negócio**: editar categoria.
- **Regras/validações/cálculos**: anti-IDOR explícito (comentário 34-36): '$donoCategoria = (int)($categoria['associado_id'] ?? 0)'; nega se '!isGestorOuAdmin()' e ('donoCategoria <= 0' ou 'donoCategoria !== (int)getAssociadoIdResolvido()') -> categorias globais (associado_id NULL/0) só para equipe (37-43). Nome obrigatório (49-51).
- **Campos/formulário**: 'nome' (required) 82; 'cor' (color) 86; 'descricao' 90.
- **Tabelas SQL usadas**: SELECT * FROM agro_categorias_atividades WHERE id=:id AND excluido=0 (AgroCategoriaAtividade.php:25); UPDATE agro_categorias_atividades SET nome=:nome, descricao=:descricao, cor=:cor WHERE id=:id AND excluido=0 (61) — não altera associado_id/uuid.
- **Ações/POST/redirects**: update (52) -> AuditLog 'AGRO_CATEGORIA_ATUALIZADA' (53) -> redirect ?atualizada=1 (54).
- **Riscos/gaps**: o guard de edição não é replicado na exclusão (categorias_listar.php:23-30) — usuário nível 3 não pode editar uma categoria global, mas pode excluí-la; sem CSRF; sem validação de cor.

### modulos/agroindustria/atividades_listar.php
- **Rota/acesso**: GET /agroindustria/atividades (index.php:947-949); menu.php:129. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: listar as atividades diárias de produção da agroindústria (data, atividade, setor, categoria, status).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: filtro por associado para não gestor (32); data formatada com date('d/m/Y', strtotime(...)) (67); janela de horas com substr 0,5 (69); badge de status por comparação exata com 'Concluída'/'Em Andamento' (93).
- **Tabelas SQL usadas** (AgroAtividadeDiaria.php:14-18):
    SELECT ad.*, s.nome AS setor_nome, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM agro_atividades_diarias ad
    LEFT JOIN agro_setores s ON ad.setor_id = s.id
    LEFT JOIN agro_categorias_atividades c ON ad.categoria_id = c.id
    WHERE ad.excluido = 0 [AND ad.associado_id = ?]
    ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC
  Exclusão: UPDATE agro_atividades_diarias SET excluido = 1 WHERE id = :id (AgroAtividadeDiaria.php:112).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'AGRO_ATIVIDADE_EXCLUIDA' (26) -> redirect.
- **Riscos/gaps**: (1) IDOR destrutivo (23-30); (2) os LEFT JOINs não filtram 'excluido' das tabelas relacionadas (16-17) -> setores/categorias já excluídos continuam aparecendo com nome; (3) 'categoria_cor' injetada em style (87) sem validação de cor; (4) sem CSRF; (5) strtotime sem validação de data.

### modulos/agroindustria/atividades_cadastrar.php
- **Rota/acesso**: GET /agroindustria/atividade/novo (index.php:950-952); link atividades_listar.php:41. Guards: isLoggedIn (12-15), temAgroindustria (17-21).
- **Propósito de negócio**: registrar atividade diária da agroindústria (equivalente ao caderno de campo, mas por setor).
- **Campos/formulário**: 'nome' (text, required) 84; 'data_atividade' (date, required, default hoje) 88; 'status' (select: Concluída/Em Andamento/Planejada/Cancelada) 92-96; 'setor_id' (select) 101-108; 'categoria_id' (select) 112-119; 'tipo_atividade' (select dos 8 tipos do mapa 54-63) 123-128; 'hora_inicio' (time) 133; 'hora_fim' (time) 137; 'insumo_utilizado' (text) 141; 'descricao' (textarea) 146; 'observacoes' (textarea) 150.
- **Regras/validações/cálculos**: nome e data obrigatórios (38-40); 'associado_id' = POST || sessão (42); status default 'Concluída' (43); nenhuma validação de intervalo de horas ou de data válida.
- **Tabelas SQL usadas**: INSERT INTO agro_atividades_diarias (uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes, criado_em) VALUES (..., NOW()) (AgroAtividadeDiaria.php:52-72); setores via AgroSetor::getAll (30); categorias via AgroCategoriaAtividade::getAll() SEM filtro (31).
- **Ações/POST/redirects**: create (44) -> AuditLog 'AGRO_ATIVIDADE_CADASTRADA' com id null (45) -> redirect ?cadastrada=1 (46).
- **Riscos/gaps**: (1) 'associado_id' do POST (42) -> cross-tenant; (2) 'setor_id'/'categoria_id' sem validação de posse (tabelas sem FK, dev-utils/migrate_agro_producao.php:122-143); (3) 'insumo_utilizado' é texto livre VARCHAR(255) (migrate_agro_producao.php:135), sem vínculo com a tabela 'insumos' -> o insumo da atividade diária NÃO é rastreável (contraste com a corrente de rastreabilidade das ordens); (4) categorias listadas globalmente (31) expõem categorias de outros produtores; (5) sem CSRF; (6) binds sem null-coalesce em AgroAtividadeDiaria.php:64-72 (descricao/status ausentes -> warning/NULL).

### modulos/agroindustria/atividades_editar.php
- **Rota/acesso**: GET /agroindustria/atividade/{id}/editar (index.php:953-955). Guards: isLoggedIn (12-15), temAgroindustria (17-21), id (23-27), existência (33-37), anti-IDOR 39-43 (associado_id vs sessão, gestor passa).
- **Propósito de negócio**: corrigir atividade lançada.
- **Campos/formulário**: idênticos ao cadastrar (99-165); mapa de tipos duplicado literalmente em 69-78 (duplicação de manutenção em relação a atividades_cadastrar.php:54-63).
- **Regras/validações/cálculos**: nome+data obrigatórios (55-57); status comparado com '===' em 109; setor/categoria com '==' em 119,130.
- **Tabelas SQL usadas**: UPDATE agro_atividades_diarias SET setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes WHERE id=:id AND excluido=0 (AgroAtividadeDiaria.php:84-89) — não altera associado_id.
- **Ações/POST/redirects**: update (58) -> AuditLog 'AGRO_ATIVIDADE_ATUALIZADA' (59) -> redirect ?atualizada=1 (60).
- **Riscos/gaps**: mesmo pacote do cadastrar (sem CSRF, sem validação de posse de setor/categoria, insumo texto livre, sem validação de hora/data); binds sem ?? (AgroAtividadeDiaria.php:93-101).

### modulos/agroindustria/ocorrencias_listar.php
- **Rota/acesso**: GET /agroindustria/ocorrencias (index.php:956-958); menu.php:130. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: listar ocorrências (contaminação, falha de processo, qualidade, higienização, equipamento, rastreabilidade) com severidade e status.
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: filtro por associado (32); mapa de cores de severidade 34-38 (Baixa/Média/Alta); badge de status 92 (Aberta vermelho, Em Tratamento amarelo, senão verde); descrição truncada em 80 chars (77).
- **Tabelas SQL usadas**: SELECT o.*, s.nome AS setor_nome FROM agro_ocorrencias o LEFT JOIN agro_setores s ON o.setor_id = s.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_ocorrencia DESC, o.criado_em DESC (AgroOcorrencia.php:14-23); UPDATE agro_ocorrencias SET excluido=1 (101).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'AGRO_OCORRENCIA_EXCLUIDA' -> redirect.
- **Riscos/gaps**: IDOR destrutivo (23-30); join sem filtro 'excluido' do setor; sem CSRF; a ocorrência não pode ser vinculada a uma ordem/lote (não existe coluna de ordem em agro_ocorrencias — dev-utils/migrate_agro_producao.php:154-171) -> gap de rastreabilidade.

### modulos/agroindustria/ocorrencias_cadastrar.php
- **Rota/acesso**: GET /agroindustria/ocorrencia/novo (index.php:959-961); link ocorrencias_listar.php:47. Guards: isLoggedIn (11-14), temAgroindustria (16-20).
- **Propósito de negócio**: registrar ocorrência e ação de manejo.
- **Campos/formulário**: 'titulo' (text, required) 82; 'data_ocorrencia' (date, required, default hoje) 86; 'tipo' (select, mapa 53-61) 90-94; 'setor_id' (select) 99-106; 'severidade' (select Baixa/Média/Alta) 110-114; 'status' (select Aberta/Em Tratamento/Resolvida) 118-122; 'descricao' (textarea) 127; 'acao_manejo' (textarea) 131.
- **Regras/validações/cálculos**: titulo+data obrigatórios (35-37); defaults por '??' em 40-42 ('Outro','Baixa','Aberta') — '??' só cobre null, não string vazia: POST com 'severidade=' grava '' em ENUM('Baixa','Média','Alta') -> erro MySQL em strict mode, PDOException capturada em AgroOcorrencia.php:68-71 -> retorno false -> mensagem genérica; 'associado_id' = POST || sessão (39).
- **Tabelas SQL usadas**: INSERT INTO agro_ocorrencias (uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status, criado_em) VALUES (..., NOW()) (AgroOcorrencia.php:52-66).
- **Ações/POST/redirects**: create (43) -> AuditLog 'AGRO_OCORRENCIA_CADASTRADA' com id null (44) -> redirect ?cadastrada=1 (45).
- **Riscos/gaps**: 'associado_id' do POST (39); 'setor_id' sem validação de posse; sem CSRF; sem vínculo com ordem/lote/insumo (não existe coluna); binds sem ?? em AgroOcorrencia.php:60-66.

### modulos/agroindustria/ocorrencias_editar.php
- **Rota/acesso**: GET /agroindustria/ocorrencia/{id}/editar (index.php:962-964). Guards: isLoggedIn (11-14), temAgroindustria (16-20), id (22-26), existência (32-36), anti-IDOR 38-42.
- **Propósito de negócio**: atualizar ocorrência/ação de manejo/status.
- **Campos/formulário**: 94-143 (mesmos campos do cadastrar; mapa de tipos duplicado em 65-73).
- **Regras/validações/cálculos**: titulo+data obrigatórios (51-53); comparações '===' com os valores das listas (104,124,132).
- **Tabelas SQL usadas**: UPDATE agro_ocorrencias SET setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status WHERE id=:id AND excluido=0 (AgroOcorrencia.php:78-81).
- **Ações/POST/redirects**: update (54) -> AuditLog 'AGRO_OCORRENCIA_ATUALIZADA' (55) -> redirect ?atualizada=1 (56).
- **Riscos/gaps**: sem CSRF; setor sem validação de posse; 'severidade' POST livre até o ENUM do banco.

### modulos/agroindustria/receitas_listar.php
- **Rota/acesso**: GET /agroindustria/receitas (index.php:965-967); menu.php:132. Guard: SOMENTE isLoggedIn (10-13) — NÃO exige temAgroindustria(), ao contrário de setores/atividades/ocorrências.
- **Propósito de negócio**: listar receitas (produto final, percentual orgânico, selo autorizado, nº de ingredientes, status).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (18-25).
- **Regras/validações/cálculos**: filtro por associado (27); mapa de rótulos de selo 29-33; cores por faixa de percentual (74: >=95 verde, >=70 amarelo, senão cinza); badges de status 85 (Ativa verde / Rascunho cinza / outros vermelho).
- **Tabelas SQL usadas** (ReceitaAgroindustria.php:15-18): SELECT r.*, (SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes FROM receitas_agroindustria r WHERE r.excluido = 0 [AND r.associado_id = ?] ORDER BY r.criado_em DESC; softDelete: UPDATE receitas_agroindustria SET excluido = 1 WHERE id = ? (196).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'RECEITA_EXCLUIDA' em 'receitas_agroindustria' (21) -> redirect /agroindustria/receitas (23). Não há checagem de retorno nem de dono.
- **Riscos/gaps**: (1) IDOR destrutivo — qualquer sessão autenticada (inclusive perfil sem agroindústria, pois não há guard temAgroindustria) exclui qualquer receita por id (18-25); (2) exclusão permitida mesmo com ordens de produção vinculadas: a FK 'fk_op_receita' não tem ON DELETE (dev-utils/migrate_conformidade.php:118) e a ordem continua válida apontando para receita soft-deleted; (3) a rastreabilidade pública ainda exibe a receita excluída (rastreabilidade.php:18 e :40 não filtram 'excluido'); (4) sem CSRF.

### modulos/agroindustria/receitas_cadastrar.php
- **Rota/acesso**: GET /agroindustria/receitas/nova (index.php:968-970); link receitas_listar.php:42. Guard: SOMENTE isLoggedIn (11-14).
- **Propósito de negócio**: cadastrar receita com ingredientes e calcular automaticamente o percentual orgânico e o selo (RF09).
- **Campos/formulário**: 'nome_produto_final' (text, required) 103; 'associado_id' (select) 107-114; 'status' (select Ativa/Rascunho/Inativa) 118-122; 'descricao' (textarea) 126; ingredientes como arrays paralelos: 'ing_nome[]' (text) 140, 'ing_quantidade[]' (number step=0.001 min=0) 143, 'ing_unidade[]' (select kg/g/mg/l/ml/un) 146-153, 'ing_organico[]' (select 1=Orgânico / 0=Convencional) 156-159, 'ing_obs[]' (text) 162; botões add/remove (134, 165) e card de pré-visualização do percentual (170-184).
- **Regras/validações/cálculos**:
  - Montagem server-side dos ingredientes (28-47): ignora nome vazio (37-39); 'eh_organico' = !empty($organicos[$i]) ? 1 : 0 (42); quantidade = (float) (43); unidade default 'kg' se vazia (44); observações trim (45).
  - Validações (49-55): nome_produto_final obrigatório -> 'Informe o nome do produto final.'; 'empty($ingredientes)' -> 'Adicione ao menos um ingrediente com quantidade maior que zero.' (a exclusão real de quantidade <= 0 só ocorre no model, ReceitaAgroindustria.php:179-181).
  - Pós-POST recalcula o preview: '$percentual = calcularPercentualOrganico($ingredientes)' (70) e '$selo = classificarSelo($percentual)' (71).
  - Defaults de persistência: associado_id = POST || sessão (57); status default 'Ativa' (58); criado_por = (int)$_SESSION['user_id'] (59).
- **Tabelas SQL usadas** (ReceitaAgroindustria.php): INSERT INTO receitas_agroindustria (nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por) VALUES (7 placeholders) (109-112); INSERT INTO ingredientes_receita (receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem, observacoes) (171-173); transação em 106/125/128.
- **Ações/POST/redirects**: create (60) -> AuditLog 'RECEITA_CADASTRADA' com o ID retornado (61) -> redirect /agroindustria/receitas?cadastrada=1 (62); exceção -> mensagem genérica (64-68).
- **Fórmulas literais** (classes/ReceitaAgroindustria.php):
  - fatorConversao (47-59): 'kg'=>1000; 'mg'=>0.001; 't'=>1000000; 'l'=>1000; 'ml'=>1; 'un'/'und'=>1; default 1 (g/mL/un).
  - calcularPercentualOrganico (65-81): '$qtd = (float)($ing['quantidade'] ?? $ing['proporcao_porcentagem'] ?? 0)'; '$peso = $qtd * $this->fatorConversao($ing['unidade_medida'] ?? 'kg')'; '$somaPeso += $peso'; se 'eh_organico' então '$somaOrganico += $peso'; se 'somaPeso <= 0' retorna 0.0; senão 'round(($somaOrganico / $somaPeso) * 100, 2)'.
  - classificarSelo (89-99): '>= 95.0' -> PRODUTO_ORGANICO_BRASIL; '>= 70.0' -> INGREDIENTES_ORGANICOS; senão SEM_SELO.
  - salvarIngredientes (168-192): pula se nome vazio ou '$peso <= 0' (179-181); grava 'proporcao_porcentagem' igual ao PESO em gramas/mL (188) — confirmado por dev-utils/migrate_receita_quantidade.php:16.
  - Espelho JS (196-254): 'fatorConversao' em JS (200-209) com kg/mg/t/l/ml e default 1 (não trata 'g'/'un' explicitamente, cai no default — mesmo resultado); limiar de selo idêntico (228-230); arredondamento JS para 1 casa (221) contra 2 casas do PHP (80) -> divergência de exibição.
- **Riscos/gaps**: (1) escrita cross-tenant via 'associado_id' do POST (57) — não gestor pode gravar receita para outro associado; (2) unidade 'un' convertida para 1 "grama" (fatorConversao:55-56) distorce o percentual orgânico de receitas com itens contados em unidades; (3) mistura massa (kg/g) e volume (l/ml) na mesma base com razão 1:1 (47-59) — assume densidade 1; (4) quantidade negativa é enviada pelo POST e simplesmente descartada no model (179-181), alterando o percentual silenciosamente; (5) sem CSRF; (6) sem guard temAgroindustria; (7) 'status' default 'Ativa' no código (58/119) enquanto o schema tem DEFAULT 'Rascunho' (dev-utils/migrate_conformidade.php:73); (8) nenhum cálculo de custo/rendimento/perda — NÃO ENCONTRADO NO CÓDIGO.

### modulos/agroindustria/receitas_editar.php
- **Rota/acesso**: GET /agroindustria/receitas/{id}/editar (index.php:971-973). Guard: SOMENTE isLoggedIn (11-14). Carrega a receita (16-25); se inexistente, alerta 'Receita não encontrada.' (22).
- **Propósito de negócio**: alterar receita e ingredientes.
- **Campos/formulário**: iguais ao cadastrar (113-137 dados gerais; 148-189 linhas de ingredientes geradas a partir do banco, com fallback de linha vazia em 180-189).
- **Regras/validações/cálculos**: mesmas do cadastrar (56-75); preview recalculado em 90-91.
- **Tabelas SQL usadas** (ReceitaAgroindustria.php): SELECT * FROM receitas_agroindustria WHERE id = ? AND excluido = 0 (32); SELECT * FROM ingredientes_receita WHERE receita_id = ? ORDER BY id ASC (39); UPDATE receitas_agroindustria SET nome_produto_final=?, descricao=?, percentual_organico_calculado=?, selo_autorizado=?, associado_id=?, status=? WHERE id = ? (140-144) — SEM 'AND excluido = 0'; DELETE FROM ingredientes_receita WHERE receita_id = ? (156) seguido de reinserção (158).
- **Ações/POST/redirects**: update (80) -> AuditLog 'RECEITA_EDITADA' (81) -> redirect /agroindustria/receitas?editada=1 (82).
- **Riscos/gaps**: (1) NÃO HÁ verificação de posse — qualquer usuário autenticado edita qualquer receita (IDOR de escrita), ao contrário de setores/atividades/ocorrências que checam associado_id; (2) 'associado_id' do POST (78) permite transferir a receita para outro produtor; (3) o UPDATE ignora 'excluido' (144) -> permite editar receita já soft-deletada; (4) o DELETE+INSERT de ingredientes (156-158) destrói o histórico: uma ordem de produção antiga passa a exibir a receita ATUAL na rastreabilidade pública (rastreabilidade.php:40-46) — não há versionamento de receita; (5) destruição de histórico é agravada porque a ordem não guarda snapshot dos ingredientes (apenas percentual_organico e selo, OrdemProducao.php:110-111); (6) sem CSRF; (7) sem temAgroindustria.

### modulos/agroindustria/ordens_listar.php
- **Rota/acesso**: GET /agroindustria/ordens (index.php:980-982); menu.php:134. Guard: SOMENTE isLoggedIn (10-13).
- **Propósito de negócio**: listar ordens de produção com lote final, produto, data, quantidade, % orgânico e status de auditoria.
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (17-23).
- **Regras/validações/cálculos**: filtro por associado (25); mapa statusBadge 27-31: APROVADO->bg-success/'Aprovado', ALERTA_FRAUDE->bg-danger/'Alerta de Fraude', EM_ANALISE->bg-warning/'Em análise'.
- **Tabelas SQL usadas** (OrdemProducao.php:15-18): SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_processamento DESC; softDelete: UPDATE ordens_producao SET excluido = 1 WHERE id = ? (157).
- **Ações/POST/redirects**: '?excluir=' -> '$opClass->softDelete($id)' com RETORNO IGNORADO (19), AuditLog 'ORDEM_PRODUCAO_EXCLUIDA' sempre registrado (20), redirect (21).
- **Riscos/gaps**: (1) IDOR destrutivo sem qualquer verificação de dono/nível (17-23) — qualquer autenticado apaga qualquer OP; (2) sem CSRF; (3) JOIN em receitas não filtra r.excluido (OrdemProducao.php:17); (4) o AuditLog é gravado mesmo se nada foi excluído (id inexistente).

### modulos/agroindustria/ordens_cadastrar.php
- **Rota/acesso**: GET /agroindustria/ordens/nova (index.php:983-985); link ordens_listar.php:40. Guard: SOMENTE isLoggedIn (14-17). Requires: OrdemProducao, Agroindustria, ReceitaAgroindustria, ControleHigienizacao, Database, AuditLog (5-10).
- **Propósito de negócio**: registrar ordem de produção (RF10/RF11): vincula receita, lote processado, higienização prévia (RF08), insumos consumidos com NF e calcula o balanço de massas.
- **Dados carregados para a tela**:
  - receitas = ReceitaAgroindustria::getAll(filtroAssociado) (24-25).
  - higienizações concluídas nas últimas 24h (28-36), SQL literal (30 e 33):
      SELECT * FROM controle_higienizacao WHERE excluido = 0 AND data_hora_fim IS NOT NULL AND data_hora_fim >= DATE_SUB(NOW(), INTERVAL 24 HOUR) [AND associado_id = ?] ORDER BY data_hora_fim DESC
  - lotes de campo (40-50): SELECT l.id, l.nome_lote, l.quantidade, l.unidade_medida, p.nome AS nome_produto FROM lotes_producao l LEFT JOIN produtos p ON l.produto_id = p.id WHERE l.excluido = 0 AND p.lavoura_id IS NOT NULL AND p.lavoura_id != 0 [AND l.associado_id = :assoc] ORDER BY l.data_producao DESC -> a variável $lotesCampo NÃO é usada em nenhum campo do formulário: código morto / RF10 (vínculo com o lote primário) não implementado na tela.
  - lotes finais (53-63) com '(p.lavoura_id IS NULL OR p.lavoura_id = 0)' -> alimentam o select 'lote_processado_final' (224-231), que envia apenas o NOME do lote como value (227).
  - insumos (67-95): para não gestor, monta a lista de associados da MESMA agroindústria (Agroindustria::getAssociadosDaMesmaAgroindustria, 70) e consulta com 'IN ($in)' concatenado após intval (72,79):
      SELECT i.id, i.nome, i.lote, i.tipo_insumo, i.quantidade_estoque, CONCAT(ass.nome,' ',COALESCE(ass.sobrenome,'')) AS registrado_por, f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj FROM insumos i LEFT JOIN associados ass ON i.associado_id = ass.id LEFT JOIN fornecedores f ON i.fornecedor_id = f.id WHERE i.excluido = 0 [AND i.associado_id IN ($in)] ORDER BY i.nome ASC
    gestor: mesma consulta sem filtro de associado (85-94).
- **Campos/formulário**: 'receita_id' (select, required) 213-220 com data-percentual; 'lote_processado_final' (select, required) 224-231; 'data_processamento' (datetime-local, required, default agora) 238; 'quantidade_final_obtida' (number step=0.01 min=0, required) 242; 'unidade_medida' (select KG/L/UN) 246-250; 'higienizacao_previa_id' (select das higienizações <24h) 254-261; itens em arrays paralelos: 'item_insumo[]' (select, required) 279-288, 'item_quantidade[]' (number) 291, 'item_unidade[]' (text, default KG) 294, 'item_nf[]' (text) 297; 'observacoes' (textarea) 313; botões add/remove de item (272, 300); painel de balanço em tempo real (316-319).
- **Regras/validações/cálculos**:
  - Montagem POST (100-134): campos em 101-111; criado_por = (int)$_SESSION['user_id'] (110); itens em 113-134 ignorando insumo vazio (119-121) e quantidade <= 0 (123-125); 'lote_primario_id' e 'produto_id' gravados SEMPRE como null (128-129).
  - Obrigatoriedade (137-139): receita, lote processado, data e quantidade final; senão 'Preencha os campos obrigatórios...'. Exige ao menos um item (140-142).
  - RF08 (144-170): receita precisa existir (145-148); em seguida
      'elseif (!$higClass->haHigienizacaoValida($dados['data_processamento'], 'Qualquer linha', (int)($dados['associado_id'] ?? 0), 24))' (149)
    e, dentro do bloco, se não houver 'higienizacao_previa_id' -> BLOQUEADO (RF08) (151-153); senão carrega a higienização (155) e valida apenas fim concluído (156-158) e janela temporal:
      '$fim = strtotime($hig['data_hora_fim']); $proc = strtotime($dados['data_processamento']); if ($proc - $fim > 24 * 3600 || $fim > $proc) -> BLOQUEADO' (160-164)
    seguido de '$thisOp = null;' (166-167) — atribuição morta.
  - Snapshot do percentual/selo da receita (175-176) e persistência (177).
- **Tabelas SQL usadas** (OrdemProducao.php): INSERT INTO ordens_producao (receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico, selo_autorizado, associado_id, observacoes, criado_por) VALUES (12 placeholders) (108-127); INSERT INTO rastreabilidade_insumos_processo (ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida) VALUES (7 placeholders) (131-144); transação 105/147/150. Também controle_higienizacao (30/33) e insumos/associados/fornecedores (73-80).
- **Ações/POST/redirects**: create (177) -> getById (178) -> AuditLog 'ORDEM_PRODUCAO_CADASTRADA' com o status_auditoria calculado (179) -> redirect /agroindustria/ordens/{id}?criada=1 (180); exceção -> mensagem genérica (182-186).
- **Cálculo do balanço (JS espelhado, 361-383)**: soma 'item_quantidade[]' de todos os '.item-row' (363-365) = entrada; saída = 'quantidade_final_obtida' (366); 'const div = Math.abs(entrada - saida) / entrada * 100' (371); 'div > 10' -> 'ALERTA FRAUDE' (372-378). Limiar 10% duplicado em dois lugares (aqui e OrdemProducao.php:64).
- **Riscos/gaps**: (1) 'haHigienizacaoValida' é chamada com o literal 'Qualquer linha' (149) e o método compara equipamento_linha = ? exatamente (ControleHigienizacao.php:44): nenhuma higienização real ('Linha de envase', 'Moinho'...) casa, então o primeiro ramo é praticamente sempre falso; (2) o 3º argumento ($associadoId) passado em 149 é ignorado pelo SQL (ControleHigienizacao.php:38-48 não usa o parâmetro) -> não há filtro por produtor na validação; (3) se o ramo 'haHigienizacaoValida' retornar TRUE (registro literal 'Qualquer linha' cadastrado por qualquer produtor), NENHUMA validação da higienização escolhida é executada — a trava some; (4) a higienização escolhida (155-164) não é verificada quanto a associado_id -> uso cross-tenant de registro de higienização de outro produtor (a FK fk_op_higienizacao, dev-utils/migrate_conformidade.php:119, só garante existência); (5) 'associado_id' vem do POST (108); (6) 'data_processamento' chega no formato HTML5 datetime-local com separador 'T' e é gravado sem normalização (também usado em strtotime em 161) — depende da tolerância do MySQL a 'T' em literais DATETIME; (7) a tela exige 'item_insumo' mas o modelo aceita qualquer insumo_id existente (nenhuma checagem de tenant); (8) a quantidade final não é comparada com a capacidade do lote escolhido, e o lote é gravado apenas como TEXTO (não há lote_id em ordens_producao — dev-utils/migrate_conformidade.php:105); (9) $lotesCampo carregado e não usado (código morto); (10) sem CSRF; (11) sem guard temAgroindustria; (12) mensagens e painel usam 'kg' fixo independentemente da unidade escolhida (317).

### modulos/agroindustria/ordens_visualizar.php
- **Rota/acesso**: GET /agroindustria/ordens/{id} (index.php:986-988). ORDEM DE INCLUDES ANÔMALA: header.php e menu.php são incluídos ANTES de qualquer guard (3-4); '$user' é criado dentro de includes/header.php:5, e o header já redireciona não autenticados (includes/header.php:16-19) — logo o 'if (!$user->isLoggedIn())' em 9-12 é CÓDIGO MORTO.
- **Propósito de negócio**: ficha da ordem de produção: dados, balanço de massas RF11, lotes/insumos processados, QR Code e etiqueta de impressão.
- **Guard de permissão**: 23-31, anti-IDOR: '$donoOp = (int)($op['associado_id'] ?? 0); if (!$user->isGestorOuAdmin() && ($donoOp <= 0 || $donoOp !== (int)$user->getAssociadoIdResolvido()))' -> 'Acesso negado'. Membro da agroindústria (não dono) também é negado.
- **Campos/formulário**: nenhum input; consulta por '?id=' (18) e '?criada=1' (60); botões Nova/Voltar/Imprimir (55-56, 140).
- **Regras/validações/cálculos**: '$itens = getItensRastreabilidade($id)' (33) e '$balanco = calcularBalanco($itens, $op['quantidade_final_obtida'])' (34) com tolerância padrão 10.0 (OrdemProducao.php:64) — o status exibido no badge (46) vem da COLUNA 'status_auditoria' gravada, enquanto o bloco RF11 (100-103) exibe o status RECALCULADO no momento da leitura; os rótulos das quantidades são fixos em 'kg' (89, 93).
- **Tabelas SQL usadas** (OrdemProducao.php): SELECT o.*, r.nome_produto_final, r.selo_autorizado AS selo_receita FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.id = ? AND o.excluido = 0 (32-37); SELECT ri.*, lp.nome_lote, p.nome AS nome_produto, ins.nome AS nome_insumo, ins.lote AS lote_insumo, ins.tipo_insumo, f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj FROM rastreabilidade_insumos_processo ri LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id LEFT JOIN produtos p ON ri.produto_id = p.id LEFT JOIN insumos ins ON ri.insumo_id = ins.id LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id WHERE ri.ordem_producao_id = ? (44-54).
- **Ações/redirects**: nenhuma escrita; a única ação é a impressão client-side.
- **Riscos/gaps**: (1) a tabela rotulada 'Lotes Primários Processados (Campo)' (107-130) lista, na prática, INSUMOS: como ordens_cadastrar.php:128 grava lote_primario_id = null sempre, a coluna 'Lote (Campo)' exibe '—' em todos os registros criados por essa tela; (2) selo exibido sempre com 'badge bg-success' (74) mesmo quando SEM_SELO; (3) QR/etiqueta dependem de CDN externo (151, sem SRI) e a etiqueta é montada por document.write em janela nova (162-214), com valores escapados por htmlspecialchars; (4) balanço recalculado pode divergir do status gravado se a tolerância padrão mudar (34 vs registro em ordens_cadastrar.php:179); (5) exibe 'kg' fixo mesmo com unidade L/UN; (6) a higienização vinculada (higienizacao_previa_id) não é lida nem exibida na tela.

### modulos/agroindustria/higienizacao_listar.php
- **Rota/acesso**: GET /agroindustria/higienizacao (index.php:974-976); menu.php:133. Guard: SOMENTE isLoggedIn (10-13).
- **Propósito de negócio**: listar registros de higienização (RF08) por equipamento/linha, com a regra destacada em 39-41: 'nenhuma ordem de produção pode ser lançada sem higienização concluída do equipamento/linha nas últimas 24 horas'.
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (17-23).
- **Regras/validações/cálculos**: filtro por associado (25); status 'Em andamento' quando data_hora_fim é nulo (66).
- **Tabelas SQL usadas**: SELECT * FROM controle_higienizacao WHERE excluido = 0 [AND associado_id = ?] ORDER BY data_hora_inicio DESC (ControleHigienizacao.php:15-21); UPDATE controle_higienizacao SET excluido = 1 WHERE id = ? (91).
- **Ações/POST/redirects**: exclusão GET -> softDelete (retorno ignorado, 19) + AuditLog 'HIGIENIZACAO_EXCLUIDA' (20) -> redirect.
- **Riscos/gaps**: (1) IDOR destrutivo (17-23); (2) sem CSRF; (3) NÃO EXISTE tela de edição no escopo (apenas _cadastrar e _listar) -> erro de digitação só pode ser resolvido por exclusão, comprometendo a trilha de auditoria; (4) excluir um registro referenciado por ordens_producao.higienizacao_previa_id é permitido (FK sem ON DELETE, dev-utils/migrate_conformidade.php:119) e a ordem passa a apontar para registro invisível.

### modulos/agroindustria/higienizacao_cadastrar.php
- **Rota/acesso**: GET /agroindustria/higienizacao/nova (index.php:977-979); link higienizacao_listar.php:34. Guard: SOMENTE isLoggedIn (10-13).
- **Propósito de negócio**: registrar procedimento de higienização com sanitizante restrito à lista permitida para produção orgânica.
- **Campos/formulário**: 'data_hora_inicio' (datetime-local, required, default agora) 67; 'data_hora_fim' (datetime-local, opcional) 71; 'equipamento_linha' (text, required) 75; 'sanitizante_utilizado' (select, required) 79-86, populado por listarSanitizantesPermitidos() (16-17); 'responsavel_nome' (text, required) 94; 'observacoes' (textarea) 98.
- **Regras/validações/cálculos**: presença de início/equipamento/sanitizante/responsável (34-36); whitelist do sanitizante: '!in_array(trim($dados['sanitizante_utilizado']), $sanitizantesNomes, true)' -> mensagem BLOQUEADO (conformidade) citando Anexo I da Portaria 52/2021 com redação da Portaria SDA 811/2023 (37-39); 'associado_id' = POST || sessão (30); criado_por = $_SESSION['user_id'] (31); NÃO valida se data_hora_fim > data_hora_inicio.
- **Tabelas SQL usadas** (ControleHigienizacao.php):
  - listarSanitizantesPermitidos (60-65): SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome ASC.
  - create (71-75): INSERT INTO controle_higienizacao (data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por) VALUES (8 placeholders).
- **Ações/POST/redirects**: create (41) -> AuditLog 'HIGIENIZACAO_REGISTRADA' com o ID retornado (42) -> redirect /agroindustria/higienizacao?registrada=1 (43-44).
- **Riscos/gaps**: (1) dependência dura da base 'insumos_organicos_aprovados': se o ENUM não incluir 'Higienização' (adicionado em sql/seed_insumos_aprovados_animal.sql:18 e sql/seed_insumos_aprovados_processamento.sql:16) ou o seed de sanitizantes não tiver sido executado (sql/seed_insumos_aprovados_animal.sql:149+), a lista vem vazia e TODOS os cadastros são bloqueados em 37-39 — como a criação de OP depende de higienização (ordens_cadastrar.php:149-170), o módulo de produção fica inoperante; (2) o sanitizante é gravado por NOME (VARCHAR(100)) sem FK para insumos_organicos_aprovados (dev-utils/migrate_conformidade.php:93) -> renomear o insumo invalida o histórico; (3) 'responsavel_nome' é texto livre, sem FK para usuarios/associados -> sem responsabilização rastreável; (4) nenhuma coluna de POP/procedimento, concentração, tempo de contato ou temperatura — NÃO ENCONTRADO NO CÓDIGO (schema em dev-utils/migrate_conformidade.php:88-100 e sinporg/prisma/migrations/0_baseline/migration.sql:642-656); (5) 'associado_id' do POST (30); (6) sem CSRF; (7) sem guard temAgroindustria; (8) 'equipamento_linha' é texto livre, sem cadastro de equipamentos/linhas (não há tabela de equipamentos no módulo).

### modulos/agroindustria/rastreabilidade.php
- **Rota/acesso**: /agroindustria/rastreabilidade[...] (index.php:989-991); PÚBLICA: comentário explícito na linha 2 ('Não requer login'); não inclui header/menu. Entrada: '$_GET['lote']' (6).
- **Propósito de negócio**: página pública do consumidor/fiscal (RF12) que, pelo código do lote impresso, apresenta produto final, produtor, percentual orgânico, selo, receita e a origem dos insumos (fornecedor, CNPJ, NF).
- **Campos/formulário**: formulário público de consulta (194-197): 'lote' (text, required) — GET.
- **Regras/validações/cálculos**: ('$lote !== '''') gate (13); se não encontrado e lote vazio mostra o formulário (189-199), se lote informado e não achado mostra 'Lote não localizado' (200-205). Nenhum cálculo próprio: exibe percentual/selo GRAVADOS na ordem.
- **Tabelas SQL usadas**:
  - (14-21): SELECT o.*, r.nome_produto_final, r.descricao AS receita_descricao, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome, a.registro_cnpo FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id LEFT JOIN associados a ON o.associado_id = a.id WHERE o.lote_processado_final = ? AND o.excluido = 0 — com 'fetch()' (23), ou seja, apenas a PRIMEIRA ordem que tiver aquele lote.
  - (26-35): SELECT ri.*, lp.nome_lote, lp.uuid AS lote_uuid, lp.data_producao, p.nome AS nome_produto, ins.nome AS nome_insumo, ins.lote AS lote_insumo, ins.tipo_insumo, f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj FROM rastreabilidade_insumos_processo ri LEFT JOIN lotes_producao lp ... LEFT JOIN insumos ins ... LEFT JOIN fornecedores f ... WHERE ri.ordem_producao_id = ?.
  - (40): SELECT * FROM receitas_agroindustria WHERE id = ? — SEM filtro 'excluido'.
  - (44): SELECT * FROM ingredientes_receita WHERE receita_id = ?.
- **Ações/POST/redirects**: nenhuma escrita; apenas GET e iframe de PDF externo (220-223: /doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf).
- **Riscos/gaps**: (1) exposição pública (sem autenticação, sem rate-limit, sem token) de nome do produtor, nome/CNPJ do fornecedor e números de nota fiscal (148-177) — dados pessoais e comerciais enumeráveis; (2) 'lote_processado_final' é texto livre VARCHAR(50) SEM UNIQUE (dev-utils/migrate_conformidade.php:105; sinporg/prisma/migrations/0_baseline/migration.sql:1203) -> dois produtores podem usar o mesmo código e a consulta devolve a primeira ordem, gerando rastreabilidade ERRADA (falha central de RF12); (3) a receita exibida é sempre a ATUAL e sem filtro de exclusão (18, 40), e os ingredientes são apagados/recriados na edição (ReceitaAgroindustria.php:156-158) -> edição retroativa altera o que o consumidor vê; (4) exibe fallback 'g' para 'proporcao_porcentagem' quando 'quantidade' está vazia (128-132), misturando a semântica de peso-base; (5) 'a.registro_cnpo' é selecionado (16) mas NUNCA renderizado — campo CNPO não é exibido na página pública; (6) CDN externo de Bootstrap (67) e link para PDF externo; (7) não há validação/limite de tamanho em 'lote' (6) — apenas comparação parametrizada (sem SQLi).

### modulos/agroindustria/minhas_listar.php
- **Rota/acesso**: GET /agroindustria/minhas (index.php:920-922); menu.php:125. Guard: SOMENTE isLoggedIn (10-13) — coerente, pois é a tela para cadastrar a primeira unidade.
- **Propósito de negócio**: listar as unidades de agroindústria do produtor (ou todas, para gestor).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (20-29).
- **Regras/validações/cálculos**: '$filtroAssociado = $isGestor ? null : getAssociadoId()' (17); EXCLUSÃO COM ANTI-IDOR (23-25): só exclui se '$isGestor || (int)$item['associado_id'] === (int)$filtroAssociado' — o único '_listar' do módulo com essa verificação; cidade/estado montados em 87-90 (usa '$a['estado']' sem ?? no operador ternário).
- **Tabelas SQL usadas** (Agroindustria.php): getAll (19-23) SELECT a.*, CONCAT(ass.nome,' ',COALESCE(ass.sobrenome,'')) AS associado_nome FROM agroindustrias a JOIN associados ass ON a.associado_id = ass.id WHERE a.excluido = 0 [AND a.associado_id = ?] ORDER BY a.nome_fantasia ASC, a.criado_em DESC; getById (37); softDelete: UPDATE agroindustrias SET excluido = 1 WHERE id = ? (248).
- **Ações/POST/redirects**: exclusão -> softDelete + AuditLog 'AGROINDUSTRIA_EXCLUIDA' em 'agroindustrias' (25) -> redirect (27).
- **Riscos/gaps**: (1) getAll filtra por 'a.associado_id = ?' e IGNORA agroindustria_membros -> o MEMBRO de uma unidade compartilhada (Agroindustria.php:53-65, 71-88) não vê essa unidade em 'Minhas Agroindústrias' nem pode gerenciá-la, contradizendo o modelo de membros; (2) softDelete não cascateia (setores/receitas/ordens continuam ativos apontando para a unidade excluída) e não reseta 'associados.tem_agroindustria' (Agroindustria.php:246-250, 252-263) -> o usuário mantém acesso ao módulo sem nenhuma unidade; (3) status 'Inativa' não bloqueia nenhum fluxo do módulo (nenhuma verificação de status em receitas/ordens); (4) exclusão por GET sem CSRF.

### modulos/agroindustria/minhas_cadastrar.php
- **Rota/acesso**: GET /agroindustria/minha/nova (index.php:923-925); links em minhas_listar.php:40,58. Guard: isLoggedIn (12-15). Requer CSRFProtection (7).
- **Propósito de negócio**: cadastrar unidade de agroindústria (com CNPJ, endereço, RT) e marcar o associado como possuidor de agroindústria.
- **Campos/formulário**: 'associado_id' (select, required, SOMENTE gestor) 87-94; 'nome_fantasia' (text, required) 100; 'razao_social' (text) 106; 'cnpj' (text + máscara JS 205-219) 112; 'inscricao_estadual' (text) 118; 'status' (select Ativa/Inativa) 123-126; 'endereco' 133; 'numero' 138; 'bairro' 143; 'cidade' 148; 'estado' (select com 26 UFs hardcoded, 156) 153-161; 'cep' 165; 'telefone' 171; 'email' (type=email) 177; 'responsavel_tecnico' 185; 'observacoes' 191; token CSRF (81).
- **Regras/validações/cálculos**: CSRF obrigatório (24-26); 'associadoId = $isGestor ? (int)$_POST['associado_id'] : (int)$meuAssociado' (28-30) — não gestor NÃO pode forjar o dono; se <=0 -> 'Selecione o associado dono da agroindústria.' (32-34); criado_por = $_SESSION['user_id'] (38); resultado numérico = sucesso (43-47), string = mensagem de erro do model (48-49).
- **Tabelas SQL usadas**: (Agroindustria.php) INSERT INTO agroindustrias (associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes, criado_por) VALUES (17 placeholders) (165-170) após Sanitizer::sanitizeArray (151) e validação de CNPJ (153-156); UPDATE associados SET tem_agroindustria = 1 WHERE id = ? (258); lista de associados para gestor: SELECT id, nome, sobrenome FROM associados WHERE status = 'ativo' ORDER BY nome ASC (58).
- **Validações de CNPJ** (Agroindustria.php:265-306): soDigitos remove tudo que não for dígito via preg_replace (267); validarCnpj (280-306) exige 14 dígitos, rejeita CNPJ com todos os dígitos repetidos (286-288) e confere os dois dígitos verificadores com pesos [5,4,3,2,9,8,7,6,5,4,3,2] / [6,5,4,3,2,9,8,7,6,5,4,3,2] e a regra 'resto < 2 ? 0 : 11 - resto' (298-299); formatarCnpj (270-278) aplica máscara 00.000.000/0000-00.
- **Ações/POST/redirects**: create (41) -> AuditLog 'AGROINDUSTRIA_CADASTRADA' com o ID (44) -> redirect /agroindustria/minhas?criada=1 (45).
- **Riscos/gaps**: (1) CNPJ sem restrição UNIQUE no schema (dev-utils/migrate_agroindustrias.php:41) -> duplicidade de unidades; (2) nenhuma validação de e-mail (formato), telefone, CEP ou UF além das opções do select; (3) 'status' normalizado apenas para 'Inativa'/'Ativa' (Agroindustria.php:186); (4) o gestor pode criar unidade para qualquer associado ativo (por design, 28-30); (5) htmlspecialchars($a['nome'].' '.$a['sobrenome']) em 91 sem ?? -> deprecation PHP 8.1 quando sobrenome é NULL; (6) mensagens de erro do model exibidas com htmlspecialchars (74) — ok.

### modulos/agroindustria/minhas_editar.php
- **Rota/acesso**: GET /agroindustria/minha/{id}/editar (index.php:926-928). Guards: isLoggedIn (12-15); id (17); existência (22-27); anti-IDOR 28-31 ('!$isGestor && (int)$agro['associado_id'] !== (int)$meuAssociado' -> redirect silencioso para /agroindustria/minhas).
- **Propósito de negócio**: editar a unidade e gerenciar os MEMBROS (associados que compartilham insumos/registros — texto 215).
- **Campos/formulário**: form principal 129-202: 'nome_fantasia' (required), 'razao_social', 'cnpj' (+máscara JS 282-297), 'inscricao_estadual', 'status', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep', 'telefone', 'email', 'responsavel_tecnico', 'observacoes' — valores via helper '$old' (101-103, htmlspecialchars com ENT_QUOTES); token CSRF (124). Form de remover membro (235-240): 'acao_membro=remover', 'associado_membro_id' (hidden) + CSRF (236). Form de adicionar membro (254-271): 'acao_membro=adicionar', 'associado_membro_id' (select, required) + CSRF (255).
- **Regras/validações/cálculos**: CSRF em todo POST (40-42); ramo de membros 45-71 (adicionar/remover); ramo de edição 72-85: '$dados['associado_id'] = $agro['associado_id']' (74) força o dono atual (não há transferência), chama update (76), sucesso exige '$resultado === true' (78); lista de candidatos a membro = TODOS os associados ativos (94) menos os já membros (95-99).
- **Tabelas SQL usadas**:
  - Agroindustria.php: getMembros (55-62) SELECT a.id, a.nome, a.sobrenome, a.tipo_usuario FROM agroindustria_membros m JOIN associados a ON m.associado_id = a.id WHERE m.agroindustria_id = ? AND m.excluido = 0 AND a.status = 'ativo' ORDER BY a.nome ASC; adicionarMembro: INSERT IGNORE INTO agroindustria_membros (agroindustria_id, associado_id, criado_por) VALUES (3 placeholders) (121); removerMembro: UPDATE agroindustria_membros SET excluido = 1 WHERE agroindustria_id = ? AND associado_id = ? AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0) (137-139); update da unidade: UPDATE agroindustrias SET razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes WHERE id = ? AND excluido = 0 (211-217) — NÃO altera associado_id — e em seguida UPDATE associados SET tem_agroindustria = 1 WHERE id = ? usando o associado_id do POST (237, 258).
  - Consulta direta: SELECT id, nome, sobrenome FROM associados WHERE status = 'ativo' ORDER BY nome ASC (94).
- **Ações/POST/redirects**: adicionar membro -> mensagem 'Membro adicionado com sucesso.' (55) e recarga de membros (57); remover -> 'Membro removido.' (64); edição -> AuditLog 'AGROINDUSTRIA_EDITADA' (79) -> redirect /agroindustria/minhas?editada=1 (80); AuditLog de membro adicionado (53) com tabela 'agroindustria_membros' e registro_id = id da AGROINDÚSTRIA (não do vínculo).
- **Riscos/gaps**: (1) Agroindustria::adicionarMembro retorna SEMPRE true — 'return $stmt->rowCount() > 0 || true;' (Agroindustria.php:123) -> a tela informa sucesso mesmo quando o INSERT IGNORE não inseriu nada (duplicado, FK inválida etc.); (2) removerMembro retorna o resultado de execute(), que é true mesmo com 0 linhas afetadas -> 'Membro removido.' é exibido sem remoção real, inclusive no caso da subquery retornar NULL (agroindústria excluída) onde 'associado_id <> NULL' é NULL; (3) a lista de candidatos (94) expõe NOMES DE TODOS OS ASSOCIADOS ATIVOS do sistema a qualquer produtor com agroindústria e permite vinculá-los como membros da própria unidade (46-51) — a tabela agroindustria_membros NÃO tem FK em associado_id (dev-utils/migrate_agro_membros.php:36-46), permitindo vínculo arbitrário/orfandade; (4) o mesmo formulário não valida se o associado alvo está ativo (POST não é conferido contra a lista); (5) $agroClass->getById($id) é chamado de novo dentro do POST (45) — consulta redundante; (6) a remoção de membro não registra log (só a adição, 53).

## Síntese do grupo — modulos/agroindustria

**Fluxos ponta-a-ponta**

1. Unidade: /agroindustria/minhas (minhas_listar.php:31) -> nova/editar (minhas_cadastrar.php:41 / minhas_editar.php:76) -> 'agroindustrias' + 'agroindustria_membros' (proprietário auto-vinculado pelo INSERT IGNORE e pelo backfill de dev-utils/migrate_agro_membros.php:52-62); 'associados.tem_agroindustria' é marcado em Agroindustria.php:258. O cadastro é o ÚNICO ponto do módulo protegido por CSRF.
2. Estrutura produtiva: setores (setores_*.php) -> categorias (categorias_*.php) -> atividades diárias (atividades_*.php), que referenciam 'agro_setores.id' e 'agro_categorias_atividades.id'; ocorrências referenciam 'agro_setores.id'. Todas as quatro tabelas foram criadas SEM FOREIGN KEY (dev-utils/migrate_agro_producao.php:65-76, 87-97, 122-143, 154-171) — a integridade é apenas de aplicação e não é verificada em nenhuma tela.
3. Receita -> percentual/selo: receitas_cadastrar.php:60 -> ReceitaAgroindustria::create (109-127) calcula percentual (calcularPercentualOrganico:65-81) e selo (classificarSelo:89-99) a partir das quantidades convertidas (fatorConversao:47-59) e persiste 'receitas_agroindustria' + 'ingredientes_receita'. A edição (receitas_editar.php:80) recalcula e APAGA/RECRIA os ingredientes (156-158).
4. Higienização -> ordem: higienizacao_cadastrar.php:41 grava 'controle_higienizacao' com sanitizante validado contra 'insumos_organicos_aprovados' (categoria='Higienização', permitido=1); ordens_cadastrar.php:28-36 lista as concluídas <24h; a trava RF08 é aplicada em ordens_cadastrar.php:144-170 (não no model).
5. Ordem de produção -> rastreabilidade: ordens_cadastrar.php:177 -> OrdemProducao::create (108-145) grava 'ordens_producao' (snapshot de percentual/selo da receita em ordens_cadastrar.php:175-176) + N linhas em 'rastreabilidade_insumos_processo'; o balanço é calculado por OrdemProducao::calcularBalanco (64-94) e o status fica em 'ordens_producao.status_auditoria'; ordens_visualizar.php exibe e RECALCULA o balanço (34) e gera QR/link (48) para a página pública.
6. Página pública: rastreabilidade.php resolve a ordem pelo TEXTO do lote (14-23) e mostra receita atual (40-46) e origem dos insumos (26-38) — sem login.

**Tabela consolidada de guards por arquivo**

| Arquivo | Guard de sessão | Guard de módulo (temAgroindustria) | Guard de dono | CSRF |
|---|---|---|---|---|
| setores_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| setores_cadastrar.php | 12-15 | 17-21 | só gestor/não gestor na lista | não |
| setores_editar.php | 11-14 | 16-20 | SIM (39-43) | não |
| categorias_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| categorias_cadastrar.php | 10-13 | 15-19 | força associado da sessão (32) | não |
| categorias_editar.php | 10-13 | 15-19 | SIM (37-43) | não |
| atividades_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| atividades_cadastrar.php | 12-15 | 17-21 | não (associado_id do POST, 42) | não |
| atividades_editar.php | 12-15 | 17-21 | SIM (39-43) | não |
| ocorrencias_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| ocorrencias_cadastrar.php | 11-14 | 16-20 | não (associado_id do POST, 39) | não |
| ocorrencias_editar.php | 11-14 | 16-20 | SIM (38-42) | não |
| receitas_listar.php | 10-13 | NÃO | NÃO (exclusão 18-25) | não |
| receitas_cadastrar.php | 11-14 | NÃO | não (associado_id do POST, 57) | não |
| receitas_editar.php | 11-14 | NÃO | NÃO (IDOR de escrita) | não |
| ordens_listar.php | 10-13 | NÃO | NÃO (exclusão 17-23) | não |
| ordens_cadastrar.php | 14-17 | NÃO | não (associado_id do POST, 108) | não |
| ordens_visualizar.php | header.php:16-19 (o 'if' local em 9-12 é morto) | NÃO | SIM (23-31) | n/a |
| higienizacao_listar.php | 10-13 | NÃO | NÃO (exclusão 17-23) | não |
| higienizacao_cadastrar.php | 10-13 | NÃO | não (associado_id do POST, 30) | não |
| rastreabilidade.php | nenhum (pública, 2) | n/a | n/a | n/a |
| minhas_listar.php | 10-13 | n/a (tela de cadastro) | SIM na exclusão (23) | não |
| minhas_cadastrar.php | 12-15 | n/a | SIM (força o próprio associado, 28-30) | SIM (24) |
| minhas_editar.php | 12-15 | n/a | SIM (28-31) | SIM (40) |

**Tabelas do módulo referenciadas pelos arquivos deste diretório**: agroindustrias, agroindustria_membros, associados, agro_setores, agro_categorias_atividades, agro_atividades_diarias, agro_ocorrencias, receitas_agroindustria, ingredientes_receita, ordens_producao, rastreabilidade_insumos_processo, controle_higienizacao, insumos_organicos_aprovados, insumos, fornecedores, lotes_producao, produtos, usuarios (via $_SESSION apenas).

---

# 2. classes/

### classes/Agroindustria.php
- **Rota/acesso**: não roteável; instanciada por minhas_listar/cadastrar/editar, setores_cadastrar/editar e ordens_cadastrar (6, 24, 29, 69). Requires Database e Sanitizer (2-3).
- **Propósito de negócio**: CRUD da unidade de agroindústria, vínculo de membros e consultas de compartilhamento de insumos entre membros.
- **Campos/formulário**: não possui tela; consome $_POST via 'create'/'update' (149-244).
- **Regras/validações/cálculos**: sanitização de todo o array (151, 203); CNPJ -> dígitos + validarCnpj e mensagem 'CNPJ inválido.' (153-156, 205-208); associado obrigatório '>0' no create (158-161); status normalizado 'Inativa'/'Ativa' (186, 232); após criar/atualizar marca 'associados.tem_agroindustria = 1' (192, 237, 258); retorno heterogêneo (int id | string erro | true em update, 147, 194, 197, 239, 242).
- **Tabelas SQL usadas**:
  - getAll (19-23): SELECT a.*, CONCAT(ass.nome,' ',COALESCE(ass.sobrenome,'')) AS associado_nome FROM agroindustrias a JOIN associados ass ON a.associado_id = ass.id WHERE a.excluido = 0 [AND a.associado_id = ?] ORDER BY a.nome_fantasia ASC, a.criado_em DESC.
  - getById (37): SELECT * FROM agroindustrias WHERE id = ? AND excluido = 0.
  - countByAssociado (44): SELECT COUNT(*) FROM agroindustrias WHERE associado_id = ? AND excluido = 0.
  - getMembros (56-61): SELECT a.id, a.nome, a.sobrenome, a.tipo_usuario FROM agroindustria_membros m JOIN associados a ON m.associado_id = a.id WHERE m.agroindustria_id = ? AND m.excluido = 0 AND a.status = 'ativo' ORDER BY a.nome ASC.
  - getAgroindustriasPorAssociado (76-84): SELECT DISTINCT a.id FROM agroindustrias a WHERE a.excluido = 0 AND ((a.associado_id = ? AND a.associado_id IS NOT NULL) OR EXISTS (SELECT 1 FROM agroindustria_membros m WHERE m.agroindustria_id = a.id AND m.associado_id = ? AND m.excluido = 0)).
  - getAssociadosDaMesmaAgroindustria (104-108): SELECT DISTINCT associado_id FROM agroindustria_membros WHERE excluido = 0 AND agroindustria_id IN ($in) — '$in' montado por implode de array_map('intval', $agros) (104).
  - adicionarMembro (121): INSERT IGNORE INTO agroindustria_membros (agroindustria_id, associado_id, criado_por) VALUES (3 placeholders).
  - removerMembro (137-139): UPDATE agroindustria_membros SET excluido = 1 WHERE agroindustria_id = ? AND associado_id = ? AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0).
  - create (165-170) e update (211-217) conforme 1.x; softDelete (248): UPDATE agroindustrias SET excluido = 1 WHERE id = ?; marcarAssociadoComAgroindustria (258): UPDATE associados SET tem_agroindustria = 1 WHERE id = ?.
- **Ações/redirects**: nenhuma (camada de dados).
- **Riscos/gaps**: (1) concatenação de SQL em 104-107 (mitigada por intval, mas é o padrão de risco do projeto); (2) 'adicionarMembro' sempre retorna true (123) — falha silenciosa; (3) 'removerMembro' sem checagem de linhas afetadas (141) — falso sucesso; (4) 'update' não altera associado_id (212-216) mas marca tem_agroindustria com base no POST (237) — se chamado fora de minhas_editar (que força o valor em 74), marca o associado errado; (5) 'softDelete' sem cascata e sem desmarcar tem_agroindustria (246-263); (6) sem UNIQUE em cnpj (dev-utils/migrate_agroindustrias.php:41) e sem validação de e-mail; (7) getAgroindustriasPorAssociado/getAssociadosDaMesmaAgroindustria (usados por ordens_cadastrar.php:70) são a única base do compartilhamento de insumos entre membros — e o vínculo de membro é auto-serviço (minhas_editar.php:94-99, 46-51), o que permite ampliar o acesso a insumos de terceiros vinculando-se como membro.

### classes/AgroSetor.php
- **Rota/acesso**: instanciada por setores_listar/cadastrar/editar, atividades_cadastrar/editar, ocorrencias_cadastrar/editar e por modulos/caderno_campo/produtos/cadastrar.php:46 e editar.php:65.
- **Propósito de negócio**: CRUD de setores da agroindústria e resolução dos setores acessíveis ao associado (dono ou membro).
- **Campos/formulário**: uuid, associado_id, agroindustria_id, nome, descricao (create 76-83).
- **Regras/validações/cálculos**: uuid gerado se ausente (72-74) com mt_rand (119-127); 'agroindustria_id' vazio => NULL (81, 97); nenhuma validação de nome/unicidade/posse.
- **Tabelas SQL usadas**:
  - getAll (14-22): SELECT * FROM agro_setores WHERE excluido = 0 [AND associado_id = ?] ORDER BY nome ASC.
  - getById (32): SELECT * FROM agro_setores WHERE id = :id AND excluido = 0.
  - getSetoresDoAssociado (49-59): SELECT s.id, s.nome, s.descricao, s.agroindustria_id, COALESCE(NULLIF(ag.nome_fantasia,''), NULLIF(ag.razao_social,''), CONCAT('Agroindústria #', ag.id)) AS agroindustria_nome FROM agro_setores s JOIN agroindustrias ag ON s.agroindustria_id = ag.id AND ag.excluido = 0 WHERE s.excluido = 0 AND (ag.associado_id = :assoc OR EXISTS (SELECT 1 FROM agroindustria_membros am WHERE am.agroindustria_id = ag.id AND am.associado_id = :assoc AND am.excluido = 0)) ORDER BY ag.id ASC, s.nome ASC.
  - create (76-77): INSERT INTO agro_setores (uuid, associado_id, agroindustria_id, nome, descricao, criado_em) VALUES (:uuid,:associado_id,:agroindustria_id,:nome,:descricao,NOW()).
  - update (95): UPDATE agro_setores SET agroindustria_id = :agroindustria_id, nome = :nome, descricao = :descricao WHERE id = :id AND excluido = 0.
  - softDelete (110): UPDATE agro_setores SET excluido = 1 WHERE id = :id.
- **Riscos/gaps**: (1) a query de getSetoresDoAssociado repete ':assoc' (55-57) — só funciona porque o PDO do projeto não desabilita prepared emulados (classes/Database.php:10-19 não define ATTR_EMULATE_PREPARES); se alguém setar 'false', todas as chamadas quebram com HY093; (2) 'agro_setores' sem NENHUMA FK (dev-utils/migrate_agro_producao.php:65-76) -> associado_id/agroindustria_id podem referenciar registros inexistentes e/ou de outro tenant; (3) create grava associado_id = 0 se ausente (80) apesar de a coluna ser NOT NULL (68) — setor órfão; (4) todos os métodos engolem PDOException retornando [] ou false (24-27, 36-39, 63-66, 85-88, 102-105, 113-116), transformando erro de banco em 'lista vazia'/'falha genérica'; (5) bindParam sem null-coalesce em 82-83 e 98-99 (chaves ausentes => warning PHP 8 e possível violação de NOT NULL em 'nome').

### classes/AgroCategoriaAtividade.php
- **Propósito de negócio**: CRUD das categorias de atividade da agroindústria (categorias padrão globais criadas pelo seed dev-utils/migrate_agro_producao.php:100-112).
- **Campos/formulário**: uuid, associado_id (nullable), nome, descricao, cor.
- **Tabelas SQL usadas**: SELECT id, nome, descricao, cor FROM agro_categorias_atividades WHERE excluido = 0 ORDER BY nome ASC (14) — SEM filtro de associado; SELECT * ... WHERE id = :id AND excluido = 0 (25); INSERT INTO agro_categorias_atividades (uuid, associado_id, nome, descricao, cor, criado_em) VALUES (..., NOW()) (42-43); UPDATE ... SET nome, descricao, cor WHERE id AND excluido = 0 (61) — não altera associado_id/uuid; UPDATE ... SET excluido = 1 (76).
- **Regras/validações/cálculos**: nenhuma (sem validação de cor hex, sem unicidade, sem checagem de tenant no SQL); uuid mt_rand (85-93).
- **Riscos/gaps**: getAll global (14) enquanto as categorias criadas recebem dono (46) -> o modelo de tenancy é incoerente: a listagem (categorias_listar.php:32) e o select de atividades (atividades_cadastrar.php:31) expõem categorias de outros produtores, e a exclusão (categorias_listar.php:23-30) não é protegida; catálogo de cores sem validação ('cor' VARCHAR(7), dev-utils/migrate_agro_producao.php:93); exceções silenciadas (17-20, 29-32, 51-54, 68-71, 79-82).

### classes/AgroAtividadeDiaria.php
- **Propósito de negócio**: CRUD das atividades diárias da agroindústria (setor + categoria + tipo + insumo em texto + horários).
- **Campos/formulário**: uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes.
- **Tabelas SQL usadas**: (14-18) join com agro_setores e agro_categorias_atividades conforme 1.x; getById (36) SELECT * ... WHERE id = :id AND excluido = 0; create (53-58) INSERT INTO agro_atividades_diarias (uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes, criado_em) VALUES (..., NOW()); update (84-89) UPDATE ... WHERE id = :id AND excluido = 0; softDelete (112) UPDATE ... SET excluido = 1.
- **Regras/validações/cálculos**: nenhuma validação de intervalo de horas, de data ou de posse de setor_id/categoria_id; 'hora_inicio'/'hora_fim'/'tipo_atividade'/'insumo_utilizado'/'observacoes' viram NULL quando vazios (66-72, 95-101).
- **Riscos/gaps**: unidade de tempo/insumo não rastreável ('insumo_utilizado' VARCHAR(255), dev-utils/migrate_agro_producao.php:135); joins sem filtro de 'excluido'; binds sem ?? em 64-72 e 93-101; exceções silenciadas (28-31, 40-43, 74-77, 104-107).

### classes/AgroOcorrencia.php
- **Propósito de negócio**: CRUD das ocorrências da agroindústria com severidade e ação de manejo.
- **Campos/formulário**: uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade ('Baixa','Média','Alta'), descricao, acao_manejo, status.
- **Tabelas SQL usadas**: getAll (14-17) SELECT o.*, s.nome AS setor_nome FROM agro_ocorrencias o LEFT JOIN agro_setores s ON o.setor_id = s.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_ocorrencia DESC, o.criado_em DESC; getById (35); create (52-55) INSERT INTO agro_ocorrencias (uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status, criado_em) VALUES (..., NOW()); update (78-81); softDelete (101).
- **Regras/validações/cálculos**: nenhuma validação de enum no PHP (severidade/status vão direto para o banco; erros de ENUM viram 'false' genérico).
- **Riscos/gaps**: não existe vínculo com ordens de produção/lotes (a tabela não possui ordem_id/produto_id/lote_id — dev-utils/migrate_agro_producao.php:154-171), o que impede relacionar uma não conformidade ao lote afetado (requisito prático de rastreabilidade); joins sem filtro 'excluido'; binds sem ?? em 60-66; exceções silenciadas.

### classes/ReceitaAgroindustria.php
- **Propósito de negócio**: CRUD de receitas e ingredientes + cálculo do percentual orgânico e do selo (RF07/RF09).
- **Campos/formulário**: nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por (109-111); ingredientes: nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem (peso em g/mL), observacoes (171-172).
- **Regras/validações/cálculos** (fórmulas literais):
  - fatorConversao (47-59): kg=1000 | mg=0.001 | t=1000000 | l=1000 | ml=1 | un/und=1 | default=1.
  - calcularPercentualOrganico (65-81): peso_i = quantidade_i * fator(unidade_i); percentual = round(sum(peso dos orgânicos)/sum(peso total)*100, 2); 0.0 se soma <= 0.
  - classificarSelo (89-99): >=95 PRODUTO_ORGANICO_BRASIL; >=70 INGREDIENTES_ORGANICOS; <70 SEM_SELO.
  - salvarIngredientes (168-192): descarta nome vazio ou peso <= 0; grava 'proporcao_porcentagem' = peso convertido (188).
- **Tabelas SQL usadas**: getAll (15-18) com subquery COUNT(*) FROM ingredientes_receita (SEM filtro de receita/excluido na subquery); getById (32); getIngredientes (39); INSERT receitas_agroindustria (109-112); UPDATE receitas_agroindustria ... WHERE id = ? (141-144, sem excluido); DELETE FROM ingredientes_receita WHERE receita_id = ? (156); INSERT ingredientes_receita (171-173); UPDATE receitas_agroindustria SET excluido = 1 (196). Transações: beginTransaction 106/138, commit 125/160, rollBack 128/163.
- **Riscos/gaps**: (1) NÃO usa Sanitizer — entrada crua do POST em nome/descrição/observações; a segurança depende exclusivamente do htmlspecialchars das telas; (2) update apaga e recria ingredientes (156-158) -> perda de histórico e alteração retroativa da rastreabilidade pública; (3) o UPDATE de receita não filtra 'excluido' (144) -> edição de registro excluído; (4) 'associado_id' aceito do chamador (118, 151) — as telas repassam o POST; (5) fatorConversao trata 'un' como 1 g (55-56) e mistura volume com massa 1:1 — distorce o percentual; (6) create assume status 'Ativa' (119) contra DEFAULT 'Rascunho' do schema (dev-utils/migrate_conformidade.php:73); (7) FK existente: 'fk_ing_receita' com ON DELETE CASCADE (dev-utils/migrate_conformidade.php:86; baseline migration.sql:941) — mas o soft delete de receitas NÃO apaga ingredientes, então ingredientes de receitas excluídas continuam acessíveis pela página pública (rastreabilidade.php:44); (8) sem limite de número de ingredientes.

### classes/OrdemProducao.php
- **Propósito de negócio**: CRUD parcial da ordem de produção, carregamento da rastreabilidade de insumos e cálculo do BALANÇO DE MASSAS (RF11).
- **Campos/formulário**: receita_id, higienizacao_previa_id, lote_processado_final (texto), data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico, selo_autorizado, associado_id, observacoes, criado_por (108-111); itens: ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida (131-132).
- **Regras/validações/cálculos** (fórmulas literais de calcularBalanco, 64-94):
  - totalEntrada = soma de (float)$item['quantidade_utilizada'] de TODOS os itens (66-69).
  - se totalEntrada <= 0 -> status 'EM_ANALISE', divergencia_pct 0, mensagem 'Nenhum lote primário informado para o balanço.' (72-80).
  - divergenciaPct = round((abs(totalEntrada - quantidadeFinal) / totalEntrada) * 100, 2) (82).
  - status = divergenciaPct > toleranciaPct (default 10.0) ? 'ALERTA_FRAUDE' : 'APROVADO' (83).
  - mensagens (90-92) com interpolação PHP: 'Divergência de {${divergenciaPct}}% entre insumos processados ({${totalEntrada}} kg) e produto final ({${quantidadeFinal}} kg). Verificar auditoria.' / 'Balanço de massas dentro da tolerância ({${divergenciaPct}}%).' — unidade 'kg' fixa.
- **Tabelas SQL usadas**: getAll (15-18) SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_processamento DESC; getById (32-37) acrescenta r.selo_autorizado AS selo_receita; getItensRastreabilidade (44-54) com LEFT JOINs em lotes_producao, produtos, insumos, fornecedores (fornecedor vem de 'ins.fornecedor_id'); create (108-127) INSERT ordens_producao (12 colunas); (131-133) INSERT rastreabilidade_insumos_processo (7 colunas); softDelete (157) UPDATE ordens_producao SET excluido = 1 WHERE id = ?. Transação 105/147/150.
- **Cardinalidades/FKs**: ordens_producao.receita_id -> receitas_agroindustria.id (fk_op_receita, SEM ON DELETE); ordens_producao.higienizacao_previa_id -> controle_higienizacao.id (fk_op_higienizacao, SEM ON DELETE); rastreabilidade_insumos_processo.ordem_producao_id -> ordens_producao.id (fk_rast_op, ON DELETE CASCADE) — todas em dev-utils/migrate_conformidade.php:117-129 e replicadas em sinporg/prisma/migrations/0_baseline/migration.sql:1216-1218 (apenas índices, sem cláusulas de FK no baseline).
- **Riscos/gaps**: (1) o docblock de create (96-100) afirma 'Bloqueia se não houver higienização válida nas últimas 24h (RF08)' — NÃO IMPLEMENTADO: create (101-153) não faz nenhuma verificação de higienização, a trava existe só na tela (ordens_cadastrar.php:144-170) e é contornável chamando o model ou explorando o ramo morto de 'Qualquer linha'; (2) o balanço soma quantidades de unidades heterogêneas (KG, L, UN conforme 'unidade_medida' do item, default 'KG' em 120 e 143) sem normalização; (3) usa abs() (82) — ganho de massa (produzir mais do que entrou) não é distinguido de perda, apenas a magnitude; (4) 'totalEntrada <= 0' (72-80) é inalcançável pela tela, que exige item com quantidade > 0 (ordens_cadastrar.php:140-142) — ramo morto; (5) create não valida receita_id > 0 (115) -> violação de FK propagada como exception; (6) NÃO há baixa de estoque: nenhum UPDATE em 'insumos.quantidade_estoque' nem em 'lotes_producao.quantidade' -> o consumo é apenas declarativo; (7) não existe coluna de custo/valor em nenhuma das tabelas do fluxo (dev-utils/migrate_conformidade.php:101-130) — cálculo de custo NÃO ENCONTRADO NO CÓDIGO; (8) não há campo de perdas/rendimento: a única métrica é divergencia_pct (82); (9) o lote final é gravado como STRING (117) sem FK para lotes_producao -> a rastreabilidade depende de igualdade textual (rastreabilidade.php:20) e não há UNIQUE no código do lote; (10) 'lote_primario_id'/'produto_id' gravados como null pela tela (ordens_cadastrar.php:128-129) tornam as colunas e os LEFT JOINs de lotes_producao/produtos inúteis nessa prática — RF10 (link campo-agroindústria, modulos/TODO_Agroind.md:64) NÃO implementado de fato; (11) tolerância 10.0 duplicada no JS (ordens_cadastrar.php:371-378).

### classes/ControleHigienizacao.php
- **Propósito de negócio**: CRUD dos registros de higienização (RF08) e resolução da lista oficial de sanitizantes permitidos em sistemas orgânicos.
- **Campos/formulário**: data_hora_inicio, data_hora_fim (nullable), equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por (72-74).
- **Regras/validações/cálculos**: haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24) (38-50): conta registros 'excluido = 0 AND data_hora_fim IS NOT NULL AND equipamento_linha = ? AND data_hora_fim <= ? AND data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)' e retorna COUNT(*) > 0 (49). O parâmetro $associadoId NÃO é usado na query.
- **Tabelas SQL usadas**: getAll (15) SELECT * FROM controle_higienizacao WHERE excluido = 0 [AND associado_id = ?] ORDER BY data_hora_inicio DESC; getById (29); haHigienizacaoValida (41-46); listarSanitizantesPermitidos (61-64) SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome ASC; create (72-74) INSERT INTO controle_higienizacao (data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por) VALUES (8 placeholders); softDelete (91) UPDATE controle_higienizacao SET excluido = 1 WHERE id = ?.
- **Ações/redirects**: nenhuma (camada de dados).
- **Riscos/gaps**: (1) O FILTRO POR PRODUTOR EM haHigienizacaoValida NÃO EXISTE — $associadoId é declarado (38) e ignorado (41-48): a validação (se fosse usada com um equipamento real) aceitaria a higienização de QUALQUER produtor; (2) a comparação literal de 'equipamento_linha' combinada com a chamada de ordens_cadastrar.php:149 ('Qualquer linha') faz a validação retornar sempre falso para equipamentos reais — a trava RF08 efetivamente nunca é satisfeita por esse ramo; (3) create não valida data_hora_fim > data_hora_inicio (76-85) -> durações negativas/bloqueios espúrios; (4) trim em chaves que podem não existir (79-81) -> warning PHP 8 se o model for chamado sem essas chaves; (5) 'sanitizante_utilizado' é o NOME do insumo (VARCHAR 100) sem FK -> renomeação/exclusão do insumo invalida o histórico e a whitelist; (6) a lista de sanitizantes depende de 'insumos_organicos_aprovados' com ENUM contendo 'Higienização' (sql/seed_insumos_aprovados_animal.sql:18; sql/seed_insumos_aprovados_processamento.sql:16) e do seed de linhas (sql/seed_insumos_aprovados_animal.sql:149+); sem isso o cadastro fica totalmente bloqueado (higienizacao_cadastrar.php:37-39); (7) POP/produto químico normalizado/concentração/tempo de contato/temperatura/EPI: NÃO ENCONTRADO NO CÓDIGO nas colunas da tabela (dev-utils/migrate_conformidade.php:88-100; sinporg/prisma/migrations/0_baseline/migration.sql:642-656 — só observacoes TEXT); (8) não há verificação de vínculo entre 'equipamento_linha' e qualquer cadastro de equipamentos (inexistente no módulo).

## Síntese do grupo — classes/

**Responsabilidades e acoplamento**
- Camada de dados sem ORM: cada classe faz 'new PDO' via singleton Database::getInstance()->getConnection() (padrão idêntico em todas as 8 classes, ex.: Agroindustria.php:9-12, OrdemProducao.php:8-11).
- Dois estilos de tratamento de erro: (a) try/catch que engole PDOException e retorna [] / false (AgroSetor, AgroCategoriaAtividade, AgroAtividadeDiaria, AgroOcorrencia; Agroindustria apenas em membros/CNPJ); (b) exceções propagadas e tratadas na tela (ReceitaAgroindustria, OrdemProducao, ControleHigienizacao). O estilo (a) transforma falha de banco em 'lista vazia'/'erro genérico' e esconde diagnósticos.
- Cinco classes usam Sanitizer (Agroindustria:151,203; AgroSetor:71,93; AgroCategoriaAtividade:37,59; AgroAtividadeDiaria:48,82; AgroOcorrencia:47,76) e três NÃO usam (ReceitaAgroindustria, OrdemProducao, ControleHigienizacao) — assimetria de defesa.
- Somente 3 classes usam transação: ReceitaAgroindustria (106/125/128, 138/160/163) e OrdemProducao (105/147/150).

**Cálculos consolidados (únicos existentes no módulo)**
| Cálculo | Fórmula literal | Arquivo:linhas |
|---|---|---|
| Fator de conversão (base massas/volume) | kg=1000, mg=0.001, t=1000000, l=1000, ml=1, un/und=1, default=1 | classes/ReceitaAgroindustria.php:47-59 |
| Percentual orgânico | round((soma das quantidades orgânicas convertidas / soma das quantidades convertidas) * 100, 2); 0 se soma <= 0 | classes/ReceitaAgroindustria.php:65-81 |
| Selo (RF09) | >=95 PRODUTO_ORGANICO_BRASIL; >=70 INGREDIENTES_ORGANICOS; senão SEM_SELO | classes/ReceitaAgroindustria.php:89-99 |
| Peso de ingrediente persistido | peso = quantidade * fator(unidade) gravado em 'proporcao_porcentagem' | classes/ReceitaAgroindustria.php:178-188 |
| Balanço de massas (RF11) | divergência% = round(abs(soma quantidade_utilizada - quantidade_final) / soma quantidade_utilizada * 100, 2); status >10% => ALERTA_FRAUDE | classes/OrdemProducao.php:64-94 |
| Balanço no cliente (espelho) | Math.abs(entrada - saida)/entrada*100 > 10 => 'ALERTA FRAUDE' | modulos/agroindustria/ordens_cadastrar.php:361-383 |
| Validação CNPJ | 14 dígitos + 2 DV com pesos [5,4,3,2,9,8,7,6,5,4,3,2]/[6,5,4,3,2,9,8,7,6,5,4,3,2], resto<2=>0 senão 11-resto | classes/Agroindustria.php:280-306 |
| Higienização válida (RF08) | COUNT(*) > 0 com equipamento_linha = ?, fim <= data e fim >= DATE_SUB(data, INTERVAL 24 HOUR) | classes/ControleHigienizacao.php:38-50 |
| Rendimento / perdas / custo | NÃO ENCONTRADO NO CÓDIGO (nenhuma ocorrência de custo/rendimento/perda nos modulos/agroindustria nem em OrdemProducao.php) | — |

**Dicionário consolidado das tabelas do módulo** (colunas confirmadas nos DDLs de dev-utils/migrate_conformidade.php:64-131, dev-utils/migrate_agroindustrias.php:34-68, dev-utils/migrate_agro_membros.php:36-46, dev-utils/migrate_agro_producao.php:65-171 e sinporg/prisma/migrations/0_baseline/migration.sql:642-656, 931-943, 1199-1219, 1421-1449)

| Tabela | Colunas principais | Chaves/Índices | FK existentes |
|---|---|---|---|
| agroindustrias | id, associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status ENUM(Ativa,Inativa), observacoes, excluido, criado_por, criado_em, atualizado_em | PK id; INDEX associado_id, status | FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE (migrate_agroindustrias.php:65) |
| agroindustria_membros | id, agroindustria_id, associado_id, excluido, criado_por, criado_em | UNIQUE (agroindustria_id, associado_id); INDEX associado_id | FK agroindustria_id -> agroindustrias(id) ON DELETE CASCADE; SEM FK em associado_id (migrate_agro_membros.php:36-46) |
| agro_setores | id, uuid, associado_id, agroindustria_id, nome, descricao, excluido, criado_em | INDEX associado_id, agroindustria_id | NENHUMA |
| agro_categorias_atividades | id, uuid, associado_id (NULL), nome, descricao, cor, excluido, criado_em | INDEX associado_id | NENHUMA |
| agro_atividades_diarias | id, uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes, excluido, criado_em | INDEX associado_id, setor_id, categoria_id, data_atividade | NENHUMA |
| agro_ocorrencias | id, uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade ENUM(Baixa,Média,Alta), descricao, acao_manejo, status, excluido, criado_em | INDEX associado_id, setor_id, data_ocorrencia | NENHUMA |
| receitas_agroindustria | id, nome_produto_final, descricao, percentual_organico_calculado DECIMAL(5,2), selo_autorizado ENUM(PRODUTO_ORGANICO_BRASIL,INGREDIENTES_ORGANICOS,SEM_SELO), associado_id, status ENUM(Rascunho,Ativa,Inativa), excluido, criado_por, criado_em | PK id | NENHUMA (migrate_conformidade.php:66-77) |
| ingredientes_receita | id, receita_id, nome_ingrediente, eh_organico, quantidade DECIMAL(10,3) NULL, unidade_medida VARCHAR(10) DEFAULT 'kg', proporcao_porcentagem DECIMAL(12,3) (peso em g/mL), observacoes | INDEX receita_id | FK receita_id -> receitas_agroindustria(id) ON DELETE CASCADE (migrate_conformidade.php:86; migrate_receita_quantidade.php:12-17 alterou quantidade/unidade/proporcao) |
| controle_higienizacao | id, data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, excluido, criado_por, criado_em | PK id | NENHUMA |
| ordens_producao | id, receita_id, higienizacao_previa_id, lote_processado_final VARCHAR(50), data_processamento, quantidade_final_obtida DECIMAL(10,2), unidade_medida, status_auditoria ENUM(APROVADO,ALERTA_FRAUDE,EM_ANALISE), percentual_organico DECIMAL(5,2), selo_autorizado VARCHAR(30), associado_id, observacoes, excluido, criado_por, criado_em | INDEX receita_id, fk_op_higienizacao | FK receita_id -> receitas_agroindustria(id) e FK higienizacao_previa_id -> controle_higienizacao(id), ambos SEM ON DELETE (migrate_conformidade.php:117-119) |
| rastreabilidade_insumos_processo | id, ordem_producao_id, lote_primario_id (NULL), produto_id (NULL), insumo_id (NULL, add migrate_agro_membros.php:70), numero_nf VARCHAR(30) (add migrate_agro_membros.php:76), quantidade_utilizada DECIMAL(10,2), unidade_medida | INDEX ordem_producao_id | FK ordem_producao_id -> ordens_producao(id) ON DELETE CASCADE (migrate_conformidade.php:129) |
| insumos_organicos_aprovados | id, nome, nome_comercial, fabricante, categoria ENUM(...'Higienização'...), tipo, subcategoria, permitido, restricoes, normativa, data_inclusao_normativa, composicao, principio_ativo, observacoes | PK id | NENHUMA (sql/schema_organico_sprint1.sql:316-340; ENUM ampliado em sql/seed_insumos_aprovados_animal.sql:18) |
| insumos (origem dos insumos usados na OP) | id, uuid, associado_id, nome, descricao, fornecedor_id, estoq_anterior, quant_recebida, quantidade_estoque, tipo_insumo ENUM(Orgânico,Em Conversão,Convencional,Não Permitido), lote VARCHAR(100) | — | fornecedor_id -> fornecedores (sinporg/prisma/migrations/0_baseline/migration.sql:946-963) |
| lotes_producao | id, uuid, nome_lote, produto_id, associado_id (add migrate_agro_producao.php:42), quantidade, unidade_medida, data_producao, excluido | — | produto_id -> produtos; associado_id backfilled por produto->lavoura->propriedade (migrate_agro_producao.php:48-58) |

**Relações do módulo (confirmadas em código)**
- ordens_producao.receita_id -> receitas_agroindustria.id (OrdemProducao.php:17, 35; migrate_conformidade.php:118).
- ordens_producao.higienizacao_previa_id -> controle_higienizacao.id (OrdemProducao.php:116; migrate_conformidade.php:119).
- rastreabilidade_insumos_processo.ordem_producao_id -> ordens_producao.id (OrdemProducao.php:131-133; migrate_conformidade.php:129).
- rastreabilidade_insumos_processo.insumo_id -> insumos.id (OrdemProducao.php:51; migrate_agro_membros.php:70).
- rastreabilidade_insumos_processo.lote_primario_id -> lotes_producao.id e .produto_id -> produtos.id (OrdemProducao.php:49-50) — COLUNAS SEMPRE GRAVADAS COMO NULL pela única tela que cria (ordens_cadastrar.php:128-129).
- agroindustria_membros.agroindustria_id -> agroindustrias.id; agroindustria_membros.associado_id SEM FK.
- agro_setores.agroindustria_id / agro_setores.associado_id SEM FK; agro_atividades_diarias.setor_id/categoria_id SEM FK; agro_ocorrencias.setor_id SEM FK.
- Ordens <-> setores <-> atividades: NÃO EXISTE LIGAÇÃO no código. ordens_producao não possui setor_id nem atividade_id; agro_atividades_diarias não possui ordem_id; a única ponte entre o 'chão de fábrica' (setores/atividades) e o processamento (ordens) é inexistente em colunas — portanto a relação 'ordens de produção <-> receitas <-> setores <-> atividades' está implementada apenas para RECEITAS (FK real); setores e atividades ficam ilhados (sem FK nem referência a partir das ordens).
- Balanço de massas <-> rastreabilidade: o balanço usa exclusivamente 'rastreabilidade_insumos_processo.quantidade_utilizada' (OrdemProducao.php:66-69) e 'ordens_producao.quantidade_final_obtida'; a origem dos insumos (fornecedor/CNPJ/NF) vem de 'insumos' -> 'fornecedores' (OrdemProducao.php:52) e é o que a página pública exibe (rastreabilidade.php:26-38, 148-177).

**Riscos transversais priorizados (todos com citação)**
1. Exclusão por GET sem posse em 7 telas de listagem: setores_listar.php:23-30, categorias_listar.php:23-30, atividades_listar.php:23-30, ocorrencias_listar.php:23-30, receitas_listar.php:18-25, ordens_listar.php:17-23, higienizacao_listar.php:17-23 (somente minhas_listar.php:23-25 valida). Combinado com a ausência de CSRF em todo o módulo (apenas minhas_cadastrar.php:24 e minhas_editar.php:40 usam CSRFProtection), permite destruição cross-tenant por visita a URL ou por tag de imagem com src apontando para a URL.
2. IDOR de escrita em receitas_editar.php (sem guard de dono) e escrita cross-tenant por 'associado_id' vindo do POST em setores_cadastrar.php:41, atividades_cadastrar.php:42, ocorrencias_cadastrar.php:39, receitas_cadastrar.php:57, receitas_editar.php:78, ordens_cadastrar.php:108 e higienizacao_cadastrar.php:30.
3. RF08 inoperante/contornável: ControleHigienizacao::haHigienizacaoValida ignora o produtor (38-48) e é chamada com o literal 'Qualquer linha' (ordens_cadastrar.php:149); a higienização efetivamente escolhida não é validada quanto a dono (ordens_cadastrar.php:155-164); o docblock de OrdemProducao::create:96-100 promete uma trava que não existe no código.
4. Rastreabilidade por string: 'lote_processado_final' VARCHAR(50) sem UNIQUE (migrate_conformidade.php:105; baseline migration.sql:1203) e sem FK para lotes_producao; a página pública pega a PRIMEIRA ordem com o texto (rastreabilidade.php:14-23) -> QR Code pode apontar para o produto errado (falha direta de RF12).
5. Receita sem versionamento: ReceitaAgroindustria::update apaga e recria ingredientes (156-158) e a rastreabilidade pública lê a receita atual sem filtro de exclusão (rastreabilidade.php:18, 40) -> o histórico publicado pode ser reescrito; a ordem guarda apenas o snapshot de percentual/selo (ordens_cadastrar.php:175-176).
6. Balanço de massas frágil: soma unidades heterogêneas (OrdemProducao.php:66-69, 143), usa abs() (82), exibe 'kg' fixo (ordens_visualizar.php:89-93; OrdemProducao.php:91-92) e não registra perdas nem custo (não encontrados).
7. Membro de agroindústria é auto-serviço e irrestrito: minhas_editar.php:94 lista todos os associados ativos e :46-51 vincula qualquer id; Agroindustria::adicionarMembro sempre retorna true (123) e a tabela não tem FK em associado_id (migrate_agro_membros.php:36-46); isso amplia o conjunto de insumos visíveis na criação de OP (Agroindustria.php:95-112 -> ordens_cadastrar.php:70-83).
8. Sanitizantes: whitelist por NOME sem FK (ControleHigienizacao.php:58-67; higienizacao_cadastrar.php:37-39) e dependência de seed/ENUM (sql/seed_insumos_aprovados_animal.sql:18,149+) — quebra total do fluxo de produção se ausente.
9. Ausência total de cálculo de custo, rendimento e perdas no módulo: NÃO ENCONTRADO NO CÓDIGO (nenhuma coluna de valor/custo nas tabelas de dev-utils/migrate_conformidade.php:64-131 e nenhuma ocorrência dos termos nos arquivos do escopo).
10. Auditoria incompleta: AuditLog sem registro_id em setores_cadastrar.php:43, categorias_cadastrar.php:35, atividades_cadastrar.php:45, ocorrencias_cadastrar.php:44 (porque os models retornam bool) e registro de exclusão mesmo quando nada foi excluído (ordens_listar.php:20).
