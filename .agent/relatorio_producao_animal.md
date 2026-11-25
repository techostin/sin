# Engenharia Reversa — Produção Animal + Manejo Animal (SINPORG / PHP puro + MySQL)

## 0. Contexto transversal (roteamento, guard, CSRF, schema)

### Cadeia de acesso (verificada)
- `.htaccess:16-24` — qualquer URL que não seja arquivo/diretório existente cai em `index.php?url=$1` (RewriteBase /app/).
- `index.php:6-8` — o roteador só entra com `$rawUrl !== ''` e faz `$parts = explode('/', $rawUrl)`.
- Rotas de Produção Animal: `index.php:993-1044`. Rotas de Manejo Animal: `index.php:1046-1058`.
- Rotas de Lote de Produção (vegetal, classe LoteProducao): `index.php:366-383` e `index.php:385-401`.
- Menu lateral: `includes/menu.php:139-158` (bloco "Produção Animal"), exibido só quando `in_array('animal', $areaProducaoAtual)` (`includes/menu.php:16-19`).
- **Não há guard de módulo no roteador**: nenhuma linha de `index.php:993-1058` verifica nível ou área de produção antes do `require`. Cada tela repete apenas `isLoggedIn()`.

### Guard de sessão padrão (idêntico nas 21 telas internas)
```php
$user = new User();
if (!$user->isLoggedIn()) { header("Location: " . SITE_URL . "/login"); exit; }
```
- `classes/User.php:106-108` (isLoggedIn = isset($_SESSION['user_id'])).
- `classes/User.php:114-141` (hasPermission: rejeita nível não numérico/≤0; Admin=1 tudo; senão `$nivel <= $required_level`).
- `classes/User.php:147-149` (isGestorOuAdmin() = hasPermission(NIVEL_GESTOR)).
- `classes/User.php:259-272` (getAssociadoId() — pode retornar **null** quando não há associado_id).
- `classes/User.php:299-311` (getAssociadoIdResolvido() — cobre 4 formas de vínculo; **retorna 0** se nada).
- Constantes: `config/config.php:23-25` (NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3).
- Bootstrap: `config/config.php:102` (session_start()); `classes/Database.php:2` faz require config/config.php, logo SITE_URL existe mesmo nas telas que não requerem config diretamente (ex.: painel.php).

### CSRF
- Infra existe: `config/config.php:47` (CSRF_TOKEN_NAME), `classes/CSRFProtection.php`, `window.csrfHeaders()` em `includes/header.php:104`.
- **Nenhuma das 21 telas de produção/manejo animal usa CSRF**: grep `csrf` em `modulos/producao_animal` = 0 ocorrências; em `modulos/manejo_animal` = 0 ocorrências. Todos os POST e todas as exclusões por GET são forjáveis.

### Auditoria
- `classes/AuditLog.php:13-27` — registrar($acao, $tabela, $registro_id, $descricao) → INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip).

---

## 1. modulos/producao_animal/

### modulos/producao_animal/painel.php
- **Rota/acesso**: `index.php:994-996` (/producao-animal → painel.php); menu em `includes/menu.php:145`. Guard: apenas isLoggedIn() (`painel.php:8-11`). Sem hasPermission para exibir; NIVEL_GESTOR só define o escopo dos números (`painel.php:13-18`).
- **Propósito de negócio**: dashboard do rebanho e checklist de conformidade com a Portaria MAPA 52/2021 (Título III).
- **Campos/formulário**: nenhum (tela somente leitura).
- **Regras/validações/cálculos**: `$isGestor ? null : $assoc_id` (`painel.php:15-18`) — se getAssociadoId() retornar null, estatisticas(null) soma **todos** os produtores. 8 cards montados em `painel.php:20-29`: ativos, total, lotes, cabeças em lotes, sanidade, reprodução, "Sendo orgânicos", convencionais. Cards de conformidade em `painel.php:60-62`: nutrição 15% MS ruminantes / 20% não ruminantes; ambiente arts. 38-44; sanidade carência dobrada ≥96h e máx. 2 tratamentos/ano.
- **Tabelas SQL usadas**: via ProducaoAnimal::estatisticas() (`classes/ProducaoAnimal.php:451-502`): animais (460-468), animal_lotes (470-472), controle_sanidade_animal (474-476), controle_reproducao_animal (478-480), manejo_animal_registros JOIN animais (482-487).
- **Ações/POST/redirects**: nenhuma; links para /manejo-animal, /producao-animal/lotes, /sanidade, /reproducao e botões rápidos `painel.php:71-76`.
- **Riscos/gaps**: (a) card **"Sendo orgânicos" é hardcoded '—'** (`painel.php:27`) enquanto o valor existe no banco — o indicador nunca é calculado; (b) `$where = " WHERE excluido = 0"` é reutilizado em 4 tabelas diferentes (`classes/ProducaoAnimal.php:453-480`) — funciona porque todas têm excluido/associado_id, mas acopla o filtro de associado ao esquema; (c) contagem de espécies por LIKE '%bovino%' em campo de texto livre (`462-464`) — animal gravado como "Bovinos" ou "BOI" não conta; (d) escopo por getAssociadoId() (null ⇒ global) é vazamento de indicadores entre produtores.

### modulos/producao_animal/lotes_cadastrar.php
- **Rota/acesso**: `index.php:1000-1002` (/producao-animal/lote/novo); link em `lotes_listar.php:36` e `painel.php:72`. Guard: isLoggedIn() `lotes_cadastrar.php:10-13`. Sem nível, sem CSRF.
- **Propósito de negócio**: criar lote de animais (animal_lotes), usado como agrupador de manejo e como unidade de rastreabilidade (etiqueta QR).
- **Campos/formulário**: nome_lote text **required** (`57`); especie select com ['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA'] (`61-66`); categoria text livre, placeholder "recria, terminação, matrizes" (`70`); quantidade number min=0 (`74`); descricao textarea (`78`).
- **Regras/validações/cálculos**: só `trim($dados['nome_lote']) === ''` (`22-24`). associado_id e criado_por são **forçados da sessão** (`26-27`).
- **Tabelas SQL usadas**: INSERT INTO animal_lotes (associado_id, nome_lote, especie, categoria, quantidade, descricao, criado_por) — `classes/ProducaoAnimal.php:41-55`.
- **Ações/POST/redirects**: POST → AuditLog::registrar('PA_LOTE_CADASTRADO','animal_lotes',$id,'Lote: '.$nome) (`29`) → redirect /producao-animal/lotes?cadastrado=1 (`30-31`).
- **Riscos/gaps**: sem CSRF; sem verificação de nível (qualquer usuário logado, inclusive nível 3 de outro produtor, cria lotes); para gestor/admin getAssociadoId() pode ser null ⇒ lote órfão (associado_id NULL, coluna nullable em `sinporg/prisma/migrations/0_baseline/migration.sql:216`); quantidade é digitada à mão e **não é sincronizada** com o número real de animais do lote (a listagem mostra a contagem via subquery, não a coluna).

### modulos/producao_animal/lotes_editar.php
- **Rota/acesso**: `index.php:1003-1005` (/producao-animal/lote/{id}/editar, $_GET['id'] injetado pelo roteador); link `lotes_listar.php:65`. Guard: isLoggedIn() `10-13`; id<=0 → volta (`15-19`); lote inexistente → volta (`22-26`).
- **Guard anti-IDOR (presente)**: `28-36` — isGestorOuAdmin() OU (int)$lote['associado_id'] === (int)$user->getAssociadoIdResolvido() (exige donoLote > 0).
- **Campos/formulário**: idênticos ao cadastro — nome_lote required (`75`), especie (`79-84`), categoria (`88`), quantidade (`92`), descricao (`96`).
- **Regras/validações/cálculos**: nome obrigatório (`42-44`).
- **Tabelas SQL usadas**: UPDATE animal_lotes SET nome_lote, especie, categoria, quantidade, descricao WHERE id — `classes/ProducaoAnimal.php:59-70`. **Não altera associado_id** (dono imutável) nem excluido.
- **Ações/POST/redirects**: POST → audit PA_LOTE_ATUALIZADO (`46`) → redirect ?atualizado=1 (`47`); em erro, `$lote = array_merge($lote, $_POST)` para reexibir (`53`).
- **Riscos/gaps**: sem CSRF; $_POST inteiro é repassado ao atualizarLote (campos extras ignorados pelo SQL, sem whitelist explícita); a checagem de posse usa getAssociadoIdResolvido() enquanto as listagens usam getAssociadoId() — critérios divergentes.

### modulos/producao_animal/lotes_listar.php
- **Rota/acesso**: `index.php:997-999` (/producao-animal/lotes); menu `includes/menu.php:148`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar lotes com contagem de animais, gerar QR de rastreabilidade e imprimir etiqueta.
- **Campos/formulário**: nenhum (tabela + botões).
- **Regras/validações/cálculos**: `$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null)` (`26`) — **se getAssociadoId() for null, o filtro vira null e a listagem retorna lotes de TODOS os produtores**. Código público do lote: 'AL-' . str_pad($id, 6, '0', STR_PAD_LEFT) (`57`, `121`); URL do QR = SITE_URL/producao-animal/rastreabilidade?lote={id} (`55`, `82`).
- **Tabelas SQL usadas**: SELECT l.*, (SELECT COUNT(*) FROM animais a WHERE a.lote_id = l.id AND a.excluido = 0) AS animais_total FROM animal_lotes l WHERE l.excluido = 0 [AND l.associado_id = ?] ORDER BY l.criado_em DESC — `classes/ProducaoAnimal.php:19-29`.
- **Ações/POST/redirects**: exclusão por **GET** `?excluir=id` (`17-24`) → UPDATE animal_lotes SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:75`) → audit PA_LOTE_EXCLUIDO (`20`) → redirect (`22`). Impressão de etiqueta 60×90 mm via window.open + qrcodejs CDN (`77-150`).
- **Riscos/gaps**: **(1) IDOR de exclusão** — o `?excluir=` não verifica nível nem posse (nenhuma comparação com associado_id em `17-24`) ⇒ qualquer usuário logado apaga (soft delete) lotes de qualquer produtor; **(2) sem CSRF** — exclusão por GET é disparável por tag img; (3) QR aponta para ?lote=id sequencial (ver rastreabilidade.php); (4) a etiqueta imprime o selo fixo "ORGÂNICO" (`128`) independentemente de o lote conter animais CONVENCIONAL ou EM_CONVERSAO; (5) data-rastreio-url + innerText no JS (`92-98`) montam a etiqueta sem escapar, mas o conteúdo vem de htmlspecialchars na célula (risco residual baixo).

### modulos/producao_animal/ambientes_cadastrar.php
- **Rota/acesso**: `index.php:1036-1038` (/producao-animal/ambiente/novo); link `ambientes_listar.php:36`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: cadastrar instalação/ambiente de criação (ambiente_criacao_animal) conforme arts. 38-44 (acesso a área externa, sombreamento, densidade máxima).
- **Campos/formulário**: nome_instalacao text **required** (`62`); especie select ENUM 8 valores (`66-71`); sistema select ['EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO'] (`75-79`); tipo_instalacao text (`83`); area_total_m2 number step 0.01 min 0 (`87`); quantidade_animais number min 0 (`91`); densidade_referencia number step 0.01 **min 0** (`95`, texto auxiliar "m²/animal"); tem_sombreamento_abrigo checkbox value=1 (`111`); observacoes textarea (`117`).
- **Regras/validações/cálculos**: só nome_instalacao (`27-29`). **Nenhum cálculo de lotação/ocupação** (não há area_total_m2 / quantidade_animais nem comparação com densidade_referencia em nenhum ponto do arquivo). getDensidades() é chamado em `16-20` para montar a lista de referência normativa (`102-105`) — mas ver risco (a).
- **Tabelas SQL usadas**: INSERT INTO ambiente_criacao_animal (associado_id, nome_instalacao, especie, sistema, tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes, criado_por) — `classes/ProducaoAnimal.php:384-404`; SELECT * FROM densidades_maximas_animais WHERE ativo = 1 [AND especie = ?] ORDER BY especie, fase — `classes/ProducaoAnimal.php:437-446`.
- **Ações/POST/redirects**: POST → audit PA_AMBIENTE_CADASTRADO (`34`) → redirect /producao-animal/ambientes?cadastrado=1 (`35`).
- **Riscos/gaps**: **(a) BUG de ordem de execução**: `16-20` usam $dados mas `$dados = $_POST;` só é atribuído em `24` ⇒ o filtro por espécie nunca aplica (toda a lista é carregada) e em PHP 8 gera warning de variável indefinida; (b) densidades_maximas_animais **não possui seed em nenhum SQL do repositório** (só o DDL em `sinporg/prisma/migrations/0_baseline/migration.sql:727-738`) ⇒ o bloco de referências renderiza lista vazia em produção; (c) sem CSRF; (d) sem cálculo de densidade real; (e) tem_sombreamento_abrigo é a única verificação de bem-estar, e é opcional.

### modulos/producao_animal/ambientes_editar.php
- **Rota/acesso**: `index.php:1039-1041` (/producao-animal/ambiente/{id}/editar); link `ambientes_listar.php:68`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR (presente)**: `28-36` — gestor/admin ou associado_id === getAssociadoIdResolvido() (exige dono > 0).
- **Campos/formulário**: nome_instalacao required (`77`), especie (`81-86`), sistema (`90-94`), tipo_instalacao (`98`), area_total_m2 (`102`), quantidade_animais (`106`), densidade_referencia (`110`), tem_sombreamento_abrigo checkbox (`116`), observacoes (`132`).
- **Regras/validações/cálculos**: nome obrigatório (`44-46`); densidades filtradas pela espécie do ambiente (`38`). Nenhum cálculo de ocupação.
- **Tabelas SQL usadas**: UPDATE ambiente_criacao_animal SET nome_instalacao, especie, sistema, tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes WHERE id — `classes/ProducaoAnimal.php:409-424` (não altera associado_id).
- **Ações/POST/redirects**: audit PA_AMBIENTE_ATUALIZADO (`48`) → ?atualizado=1 (`49`).
- **Riscos/gaps**: sem CSRF; **checkbox desmarcado zera o campo**: `isset($dados['tem_sombreamento_abrigo']) ? (int)... : 0` (`classes/ProducaoAnimal.php:421`) — uma edição que não reenvie o switch desliga o sombreamento silenciosamente; especie pode ser trocada sem reavaliar as densidades já gravadas.

### modulos/producao_animal/ambientes_listar.php
- **Rota/acesso**: `index.php:1033-1035`; menu `includes/menu.php:156`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar instalações com área, nº de animais, densidade de referência e flag de sombra/abrigo.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo em `26` (mesmo padrão de risco: getAssociadoId() null ⇒ lista global). Colunas em `52` e densidade_referencia impressa crua (`65`). Nenhum cálculo de ocupação em % ou m²/animal.
- **Tabelas SQL usadas**: SELECT * FROM ambiente_criacao_animal WHERE excluido = 0 [AND associado_id = ?] ORDER BY criado_em DESC — `classes/ProducaoAnimal.php:363-372`.
- **Ações/POST/redirects**: exclusão por GET `?excluir=id` (`17-24`) → UPDATE ambiente_criacao_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:429`) → audit PA_AMBIENTE_EXCLUIDO → redirect.
- **Riscos/gaps**: IDOR de exclusão (sem posse/nível, `17-24`); sem CSRF; tipo_instalacao exibido sem sanitização adicional (já vem de htmlspecialchars, `60`); sem alerta quando quantidade_animais excede a capacidade implícita (area_total_m2 / densidade_referencia) — a checagem não existe em lugar nenhum.

### modulos/producao_animal/alimentacao_cadastrar.php
- **Rota/acesso**: `index.php:1009-1011` (/producao-animal/alimentacao/novo); link `alimentacao_listar.php:36` e `painel.php:73`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: registro de alimentação (arts. 34-37) por animal ou por lote, com origem orgânica/conversão/exceção.
- **Campos/formulário**: data_alimentacao date **required** (`68`, default date('Y-m-d')); tipo_alimento select **required** ['FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA'] (`72-76`); origem select **required** ORGANICO|EM_CONVERSAO|NAO_ORGANICO_AUTORIZADO (`80-84`); animal_id select (`88-93`); lote_id select (`97-102`); produto_utilizado text (`106`); quantidade_kg number step 0.01 min 0 (`110`); percentual_materia_seca number step 0.01 min 0 **max 100** (`114`); observacoes textarea (`119`).
- **Regras/validações/cálculos**: data obrigatória (`26-28`); **se origem === 'NAO_ORGANICO_AUTORIZADO' e observacoes vazio ⇒ erro** "descreva a escassez/necessidade e a autorização no PMO" (`30-34`). **Nenhum cálculo** de kg/animal/dia, de % de MS não orgânica acumulada no período, de custo ou de consumo de estoque. Os limites 15%/20% MS aparecem apenas como form-text no HTML (`115`).
- **Tabelas SQL usadas**: INSERT INTO controle_alimentacao_animal (associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento, produto_utilizado, quantidade_kg, percentual_materia_seca, origem, observacoes, criado_por) — `classes/ProducaoAnimal.php:138-156`. Listas: getAll (animais, `classes/ManejoAnimal.php:15-28`) e getLotes (`classes/ProducaoAnimal.php:19-29`).
- **Ações/POST/redirects**: audit PA_ALIMENTACAO_CADASTRADA (`39`) → ?cadastrado=1 (`40`).
- **Riscos/gaps**: sem CSRF; **sem validação de posse de animal_id/lote_id** — o select só lista itens do escopo, mas o POST aceita qualquer id (o INSERT não revalida: `classes/ProducaoAnimal.php:145-146`) ⇒ registro de alimentação pode ser vinculado a animal/lote de outro produtor; quantidade_kg é total, não normalizado por cabeça/dia (o foco "kg/animal/dia" **não existe no código**); não há vínculo com insumos/estoque (grep custo|estoque = 0 ocorrências em `modulos/producao_animal` e em `classes/ProducaoAnimal.php`).

### modulos/producao_animal/alimentacao_editar.php
- **Rota/acesso**: `index.php:1012-1014` (/producao-animal/alimentacao/{id}/editar); link `alimentacao_listar.php:75`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR**: **ausente**. getAlimentacaoById($id) não filtra associado_id (`classes/ProducaoAnimal.php:130-131`) e `28-30` apenas carregam listas de escopo, sem comparar dono.
- **Campos/formulário**: mesmos do cadastro — data_alimentacao (`72`), tipo_alimento (`76-80`), origem (`84-88`), animal_id (`92-97`), lote_id (`101-106`), produto_utilizado (`110`), quantidade_kg (`114`), percentual_materia_seca (`118`), observacoes (`123`).
- **Regras/validações/cálculos**: data obrigatória (`36-38`); exceção não orgânica exige observações (`39-41`).
- **Tabelas SQL usadas**: UPDATE controle_alimentacao_animal SET animal_id, lote_id, data_alimentacao, tipo_alimento, produto_utilizado, quantidade_kg, percentual_materia_seca, origem, observacoes WHERE id — `classes/ProducaoAnimal.php:162-177` (não altera associado_id).
- **Ações/POST/redirects**: audit PA_ALIMENTACAO_ATUALIZADA (`43`) → ?atualizado=1 (`44`).
- **Riscos/gaps**: **IDOR de leitura+escrita** (qualquer autenticado edita registro de qualquer produtor); pode-se trocar animal_id/lote_id para ids de terceiros; sem CSRF.

### modulos/producao_animal/alimentacao_listar.php
- **Rota/acesso**: `index.php:1006-1008`; menu `includes/menu.php:150`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar controles de alimentação com origem (badge) e %MS.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo em `26` (null ⇒ global); exibição animal **ou** lote: `$r['nome_animal'] ?? 'Lote: '.$r['nome_lote']` (`62`) — se houver animal e lote, mostra o animal; badges de origem mapeados em `68-72`.
- **Tabelas SQL usadas**: SELECT c.*, a.nome_animal, a.especie AS especie_animal, l.nome_lote FROM controle_alimentacao_animal c LEFT JOIN animais a ON a.id = c.animal_id LEFT JOIN animal_lotes l ON l.id = c.lote_id WHERE c.excluido = 0 [AND c.associado_id = ?] ORDER BY c.data_alimentacao DESC — `classes/ProducaoAnimal.php:112-125`.
- **Ações/POST/redirects**: exclusão por GET `?excluir=` (`17-24`) → UPDATE controle_alimentacao_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:182`).
- **Riscos/gaps**: IDOR de exclusão; sem CSRF; sem custo total, sem somatório de kg/MS por período (não há agregação nenhuma na tela).

### modulos/producao_animal/reproducao_cadastrar.php
- **Rota/acesso**: `index.php:1027-1029` (/producao-animal/reproducao/novo); `reproducao_listar.php:36`, `painel.php:75`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: registrar evento reprodutivo (controle_reproducao_animal): cobertura, inseminação, parto, nascimento, desmame.
- **Campos/formulário**: tipo_evento select **required** ['COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME'] (`75-79`); data_evento date **required** (`83`); animal_id select "Animal (mãe/fêmea)" (`87-92`); macho_reprodutor_id **text** ("Identificação do Reprodutor") (`95-96`); previsao_parto date (`100`); grupo_reprodutivo text (`104`); nascidos_vivos number min 0 (`108`); natimortos number min 0 (`112`); periodo_aleitamento_dias number min 0 (`116`); especie select (só para validação, `121-126`); observacoes textarea (`130`).
- **Regras/validações/cálculos**: data obrigatória (`25-27`). **Regra de aleitamento mínimo** (`29-41`): se tipo_evento === 'DESMAME' e periodo_aleitamento_dias > 0: `$min = 90`; OVINOS|CAPRINOS ⇒ 28; SUINOS ⇒ 45; sem espécie ⇒ `$min = 0` (regra desativada); se aleitamento < $min ⇒ bloqueio com mensagem. **Não existe** validação cruzada de data (previsão de parto vs. data da cobertura), diagnóstico de gestação, intervalo entre partos ou DEL (grep diagnostico|gestacao|intervalo|lacta em `modulos/producao_animal` = 0 ocorrências; em `classes/ProducaoAnimal.php` = 0).
- **Tabelas SQL usadas**: INSERT INTO controle_reproducao_animal (associado_id, animal_id, grupo_reprodutivo, tipo_evento, data_evento, macho_reprodutor_id, previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes, criado_por) — `classes/ProducaoAnimal.php:309-328`. **especie não é persistida** (não consta no INSERT nem no DDL).
- **Ações/POST/redirects**: audit PA_REPRODUCAO_CADASTRADA (`46`) → ?cadastrado=1 (`47`).
- **Riscos/gaps**: **(a) campo "Espécie" é usado só para a validação e descartado** — o dado não é gravado; **(b) incompatibilidade de tipo**: macho_reprodutor_id é INTEGER NULL no banco (`sinporg/prisma/migrations/0_baseline/migration.sql:666`) mas o formulário é texto livre e a classe grava trim($dados['macho_reprodutor_id']) como string (`classes/ProducaoAnimal.php:320`) ⇒ em STRICT_TRANS_TABLES o INSERT falha ("Incorrect integer value") quando o usuário digita um nome; (c) sem CSRF; (d) animal_id sem validação de posse; (e) **não há DIAGNOSTICO_GESTACAO** no ENUM (`migration.sql:664`) nem na UI; (f) o formulário não vincula o evento a lote_id (o DDL não tem a coluna) — ver bug em rastreabilidade.php.

### modulos/producao_animal/reproducao_editar.php
- **Rota/acesso**: `index.php:1030-1032` (/producao-animal/reproducao/{id}/editar); link `reproducao_listar.php:72`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR**: **ausente** — getReproducaoById não filtra associado (`classes/ProducaoAnimal.php:301-302`) e `28-29` só carregam a lista de animais.
- **Campos/formulário**: idênticos ao cadastro (`83-139`).
- **Regras/validações/cálculos**: mesma regra de desmame (`39-50`; mensagem sem os números das espécies, diferente do cadastro, `47`).
- **Tabelas SQL usadas**: UPDATE controle_reproducao_animal SET animal_id, grupo_reprodutivo, tipo_evento, data_evento, macho_reprodutor_id, previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes WHERE id — `classes/ProducaoAnimal.php:334-350`.
- **Ações/POST/redirects**: audit PA_REPRODUCAO_ATUALIZADA (`53`) → ?atualizado=1 (`54`).
- **Riscos/gaps**: IDOR total; sem CSRF; mesmo problema de tipo de macho_reprodutor_id; edição permite transformar um PARTO sem periodo_aleitamento_dias sem qualquer consistência.

### modulos/producao_animal/reproducao_listar.php
- **Rota/acesso**: `index.php:1024-1026`; menu `includes/menu.php:154`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar eventos reprodutivos com vivos/natimortos, aleitamento e previsão de parto.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo em `26`; coluna vivos/natimortos: mostra nascidos_vivos e, se natimortos > 0, também natimortos (`65-67`). Aviso normativo de aleitamento (90/28/45 dias) em `39-42`. **Não há** cálculo de taxa de concepção, intervalo entre partos (IEP), período de serviço ou dias em lactação — "não encontrado no código".
- **Tabelas SQL usadas**: SELECT c.*, a.nome_animal, a.especie AS especie_animal FROM controle_reproducao_animal c LEFT JOIN animais a ON a.id = c.animal_id WHERE c.excluido = 0 [AND c.associado_id = ?] ORDER BY c.data_evento DESC — `classes/ProducaoAnimal.php:284-296`.
- **Ações/POST/redirects**: exclusão por GET (`17-24`) → UPDATE controle_reproducao_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:355`).
- **Riscos/gaps**: IDOR de exclusão; sem CSRF; nenhuma métrica reprodutiva derivada (o foco "intervalo entre partos / dias em lactação" não está implementado); macho_reprodutor_id é exibido cru (`63`) o que mascara o problema de tipo.

### modulos/producao_animal/sanidade_cadastrar.php
- **Rota/acesso**: `index.php:1018-1020` (/producao-animal/sanidade/novo); `sanidade_listar.php:36`, `painel.php:74`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: registro sanitário (arts. 59-63): vacinação, terapêutico, controle parasitário, preventivo, exame; com tratamento excepcional e carência.
- **Campos/formulário**: data_tratamento date **required** (`71`); tipo_tratamento select **required** ['VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL'] (`75-79`); produto_normatizado **checkbox** value=1 "Produto consta do Anexo II" (`84`); produto_utilizado text (`90`); motivo text (`94`); animal_id select (`98-103`); lote_id select (`107-112`); dose_quantidade **text** (`116`); via_aplicacao text (`120`); responsavel text (`124`); periodo_carencia_horas number min 0 (`128`); data_liberacao_organica date (`133`); aprovado_ocs select vazio/1/0 (`137-141`); numero_tratamento number min 1 default 1 (`145`); observacoes textarea (`150`).
- **Regras/validações/cálculos**: `$excepcional = !isset($dados['produto_normatizado']) || (int)$dados['produto_normatizado'] !== 1` (`29`); `$carencia = (int)$dados['periodo_carencia_horas'] ?: 0` (`30`); se excepcional e carência ≤ 0 ⇒ erro (`32-34`); se excepcional e carência < 96 ⇒ erro (`35-37`). **Não há** validação de aprovado_ocs, **não há contagem** de tratamentos por animal/ano e **não há** cálculo de data_liberacao_organica (é digitada manualmente; nenhuma fórmula data_tratamento + carência no arquivo).
- **Tabelas SQL usadas**: INSERT INTO controle_sanidade_animal (associado_id, animal_id, lote_id, data_tratamento, tipo_tratamento, produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento, observacoes, criado_por) — `classes/ProducaoAnimal.php:216-242` (17 colunas / 17 placeholders).
- **Ações/POST/redirects**: audit PA_SANIDADE_CADASTRADA (`42`) → ?cadastrado=1 (`43`).
- **Riscos/gaps**: (a) limite "**Nº do Tratamento** (máx. 2/ano)" é **apenas form-text** (`146`) — não há SELECT COUNT(*) de tratamentos do animal no ano em nenhum lugar; (b) aprovado_ocs não é exigido para tratamento excepcional (só a carência); (c) "carência dobrada" é mencionada na UI (`sanidade_listar.php:41-42`) mas não é calculada — só o piso de 96h é checado; (d) sem CSRF; (e) animal_id/lote_id sem revalidação de posse no INSERT (`classes/ProducaoAnimal.php:225-226`); (f) sem catálogo de vacinas/lote de vacina/validade/reforço — não encontrado no código; (g) carência em horas enquanto a norma é em dias (sem conversão).

### modulos/producao_animal/sanidade_editar.php
- **Rota/acesso**: `index.php:1021-1023` (/producao-animal/sanidade/{id}/editar); link `sanidade_listar.php:75`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR**: **ausente** — getSanidadeById não filtra associado (`classes/ProducaoAnimal.php:208-209`); `28-30` carregam apenas listas.
- **Campos/formulário**: iguais ao cadastro (`77-156`). Checkbox com default diferente: `(int)($registro['produto_normatizado'] ?? 1) === 1` (`90`) — pré-marcado quando NULL.
- **Regras/validações/cálculos**: data obrigatória (`36-38`); excepcional ⇒ carencia < 96 bloqueia (`40-45`). **Nota**: na edição, o teste é só < 96 (o cadastro também valida <= 0, `sanidade_cadastrar.php:32-37`) — assimetria de validação.
- **Tabelas SQL usadas**: UPDATE controle_sanidade_animal SET ... WHERE id — `classes/ProducaoAnimal.php:248-271`.
- **Ações/POST/redirects**: audit PA_SANIDADE_ATUALIZADA (`47`) → ?atualizado=1 (`48`).
- **Riscos/gaps**: IDOR; sem CSRF; desmarcar o switch em edição grava produto_normatizado = 0 (`classes/ProducaoAnimal.php:264`) e converte um registro conforme em "excepcional" sem exigir aprovado_ocs; numero_tratamento segue sem enforcement.

### modulos/producao_animal/sanidade_listar.php
- **Rota/acesso**: `index.php:1015-1017`; menu `includes/menu.php:152`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar tratamentos com marcação Anexo II × Excepcional, carência e data de liberação orgânica.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo (`26`); badge produto_normatizado → "Anexo II" / "Excepcional" (`65-71`). Aviso normativo com "carência dobrada, mínimo 96 horas, máx. 2 tratamentos/ano" (`40-43`).
- **Tabelas SQL usadas**: SELECT c.*, a.nome_animal, a.especie AS especie_animal, l.nome_lote FROM controle_sanidade_animal c LEFT JOIN animais a ON a.id = c.animal_id LEFT JOIN animal_lotes l ON l.id = c.lote_id WHERE c.excluido = 0 [AND c.associado_id = ?] ORDER BY c.data_tratamento DESC — `classes/ProducaoAnimal.php:190-203`.
- **Ações/POST/redirects**: exclusão por GET (`17-24`) → UPDATE controle_sanidade_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:276`).
- **Riscos/gaps**: IDOR de exclusão; sem CSRF; **não exibe** dose_quantidade, via_aplicacao, responsavel, numero_tratamento nem aprovado_ocs (8 colunas no total, `53`) — dados de prescrição existem no banco mas não aparecem na lista; nenhum alerta de carência vigente (não há comparação data_liberacao_organica vs. hoje em nenhum arquivo).

### modulos/producao_animal/rastreabilidade.php
- **Rota/acesso**: `index.php:1042-1044` (/producao-animal/rastreabilidade). **Página pública**: `rastreabilidade.php:2` comenta "Não requer login" e o arquivo **não** instancia User nem checa sessão (só config e Database, `3-8`). Acessada pelo QR impresso (`lotes_listar.php:82`) e pelo formulário GET interno (`295-298`).
- **Propósito de negócio**: consulta pública de origem/rastreabilidade do lote animal: produtor (CNPO), composição, alimentação, sanidade e reprodução.
- **Campos/formulário**: GET lote (int, `6`); GET uuid (texto, `7`) — o "código de rastreabilidade" é comparado com CONCAT('AL-', LPAD(al.id, 6, '0')) (`19-24`), ou seja **não é um UUID**: é o ID numérico formatado e sequencial; formulário público name="uuid" com placeholder "Ex.: AL-000001" e required autofocus (`296`).
- **Regras/validações/cálculos**: se $uuid !== '' busca por código (`17-26`); senão, se $lote_id > 0, busca por id (`27-35`); rótulos tipoEntradaLabels (`78-83`), origemLabels (`84-88`), tipoAlimentoLabels (`89-96`); exibição "Quantidade de Animais" = coluna animal_lotes.quantidade digitada à mão (`144`), **não** a contagem real (COUNT(animais)); selo fixo "Orgânico"/"Em conformidade orgânica" (`123`, `148`) sem nenhuma verificação de origem_conversao dos animais; data/hora de emissão fixa no rodapé (`167`).
- **Tabelas SQL usadas**: animal_lotes LEFT JOIN associados (`19-23` e `28-32`, traz registro_cnpo); animais WHERE lote_id = ? AND (excluido IS NULL OR excluido = 0) (`41-42`); controle_alimentacao_animal WHERE lote_id = ? (`49-51`); controle_sanidade_animal WHERE lote_id = ? (`59-61`); controle_reproducao_animal WHERE lote_id = ? (`69-71`).
- **Ações/POST/redirects**: nenhuma (somente GET); modal com iframe do PDF /doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf (`321`, `324`); Bootstrap via CDN (`104`, `331`).
- **Riscos/gaps**: **(1) BUG confirmado**: controle_reproducao_animal **não possui coluna lote_id** (`sinporg/prisma/migrations/0_baseline/migration.sql:659-679`) ⇒ a query de `rastreabilidade.php:69-72` lança PDOException, engolida pelo catch (Exception $e) vazio (`75`) ⇒ **a seção "Reprodução" nunca é exibida** e o erro fica silencioso (o mesmo padrão de catch mudo ocorre em `45`, `55`, `65`, mascarando qualquer falha de esquema); **(2) exposição pública enumerável**: ?lote=1..N revela nome do produtor, registro_cnpo, espécie/categoria e histórico sanitário/nutricional sem autenticação, sem rate-limit e sem token — o "código" AL-00000N é derivado do ID; (3) **sem SISBOV/NIF**: grep sisbov e NIF em *.sql e `classes/*.php` = 0 ocorrências; o único identificador animal é animais.identificacao ("brinco/tag", `185`), sem unicidade no DDL (`migration.sql:193`); (4) **sem genealogia**: pai_id/mae_id/genealog* = 0 ocorrências; (5) **sem movimentações** de animais entre lotes (não há tabela/histórico de movimentação; animais.lote_id é apenas o vínculo atual); (6) não exibe produto_normatizado, numero_tratamento, dose_quantidade, via_aplicacao nem aprovado_ocs embora os selecione (`59-61`) — dados de prescrição/carência incompletos no comprovante público.

### Síntese do grupo (modulos/producao_animal)
**Fluxo ponta-a-ponta**
1. Login → `index.php:11-14` → $_SESSION['user_id']/['user_nivel'] (`classes/User.php:95-98`) → menu filtra por areaProducao() (`includes/menu.php:16-19`) mas o roteador não filtra: acesso direto por URL só depende de isLoggedIn().
2. Painel (`index.php:994-996`) soma KPIs via ProducaoAnimal::estatisticas() (`classes/ProducaoAnimal.php:451-502`) e entrega os atalhos para lote/animal/alimentação/sanidade/reprodução/ambiente (`painel.php:71-76`).
3. Animal (manejo-animal) → opcionalmente vinculado a um lote (animal_lotes, `classes/ManejoAnimal.php:76-79`) → lotes agregam a contagem real por subquery (`classes/ProducaoAnimal.php:19`).
4. Lote → QR AL-0000NN (`lotes_listar.php:57`) → URL pública /producao-animal/rastreabilidade?lote=id (`lotes_listar.php:55`) → rastreabilidade.php consolida animais + alimentação + sanidade do lote (`41-65`); reprodução **quebra** por falta de lote_id (`69-75`).
5. Alimentação/sanidade/reprodução gravam com associado_id da sessão (`alimentacao_cadastrar.php:36`, `sanidade_cadastrar.php:39`, `reproducao_cadastrar.php:43`) e criado_por de $_SESSION['user_id'].
6. Ambientes são cadastrados isoladamente (`ambientes_cadastrar.php`); **não existe vínculo ambiente↔lote/animal** em nenhuma tabela deste escopo (ambiente_criacao_membros existe no DDL mas **não é referenciada por nenhum arquivo PHP de produção animal**).
7. Exclusões são todas soft delete, mas via **GET** e sem posse (lotes, ambientes, alimentação, sanidade, reprodução).

**Tabelas de banco consolidadas**
| Tabela | Colunas-chave | DDL | Uso nas telas |
|---|---|---|---|
| animal_lotes | id, associado_id, nome_lote, especie, categoria, quantidade, descricao | migration.sql:214-228 | lotes_*, rastreabilidade, painel, selects de animal |
| animais | id, associado_id, nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem | migration.sql:187-211 | manejo_animal/*, rastreabilidade, selects |
| ambiente_criacao_animal | id, associado_id, nome_instalacao, especie(ENUM), sistema(ENUM), tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes | migration.sql:152-170 | ambientes_* |
| densidades_maximas_animais | especie, fase, densidade(varchar), referencia, ativo | migration.sql:727-738 | somente leitura em ambientes_* (**sem seed**) |
| controle_alimentacao_animal | associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento(ENUM), produto_utilizado, quantidade_kg, percentual_materia_seca, origem(ENUM), observacoes | migration.sql:619-639 | alimentacao_* |
| controle_sanidade_animal | associado_id, animal_id, lote_id, data_tratamento, tipo_tratamento(ENUM), produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento, observacoes | migration.sql:682-708 | sanidade_* |
| controle_reproducao_animal | associado_id, animal_id, grupo_reprodutivo, tipo_evento(ENUM), data_evento, macho_reprodutor_id(INT), previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes — **sem lote_id, sem especie** | migration.sql:659-679 | reproducao_* (+ query inválida em rastreabilidade) |
| manejo_animal_registros | animal_id, tipo_registro(ENUM de 7), data_registro, descricao, produto_utilizado, dose_quantidade, periodo_carencia_horas, responsavel, observacoes | migration.sql:1063-1079 | animais_visualizar, painel |
| associados | id, nome, sobrenome, registro_cnpo | migration.sql:322+ | rastreabilidade, selects de associado |
| audit_log | user_id, acao, tabela, registro_id, descricao, ip | `classes/AuditLog.php:19-21` | todas as escritas |

---

## 2. modulos/manejo_animal/

### modulos/manejo_animal/animais_cadastrar.php
- **Rota/acesso**: `index.php:1050-1052` (/manejo-animal/novo); links `animais_listar.php:46`, `painel.php:71`. Guard: isLoggedIn() `12-15`. Sem nível, sem CSRF.
- **Propósito de negócio**: cadastrar animal do rebanho (animais) com origem/conversão orgânica, entrada e lote (RF05).
- **Campos/formulário**: nome_animal text **required** (`77`); especie **text required** (`81`, placeholder "Ex.: Bovino, Suíno..."); raca (`85`); identificacao text "brinco/tag" (`89`); sexo select M/F (`93-97`); data_nascimento date (`101`); associado_id select (`105-112`); origem_conversao select ORGANICO|EM_CONVERSAO|CONVENCIONAL (`116-120`); status select ATIVO|VENDIDO|ABATIDO|MORTO (`125-130`); lote_id select (`134-139`); peso_kg number step 0.01 min 0 (`143`); tipo_entrada select NASCIDO_PROPRIEDADE|COMPRADO_ORGANICO|COMPRA_CONVENCIONAL|TRANSFERIDO_OCS (`147-153`); fornecedor_origem (`158`); data_entrada date (`162`); observacoes_origem textarea (`166`).
- **Regras/validações/cálculos**: `empty($dados['nome_animal']) || empty($dados['especie'])` (`47-49`). associado_id = $_POST['associado_id'] ?? getAssociadoId() (`29`); criado_por = (int)$_SESSION['user_id'] (`44`). Nenhum cálculo de conversão (prazos por espécie) apesar do texto de ajuda (`154`).
- **Tabelas SQL usadas**: INSERT INTO animais (associado_id, nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, criado_por) — `classes/ManejoAnimal.php:48-62`; em seguida, se houver lote/peso/tipo_entrada, UPDATE animais SET lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem WHERE id — `classes/ManejoAnimal.php:65-67` + `76-79`.
- **Ações/POST/redirects**: AuditLog::registrar('ANIMAL_CADASTRADO','animais',$id,...) (`52`) → redirect /manejo-animal/{id} (`53`). O audit ocorre **sem verificar** se create() retornou id válido.
- **Riscos/gaps**: **(a) IDOR de escrita na criação**: associado_id vem do POST sem validação (`29`) — um nível 3 pode criar animal em nome de outro produtor (o select só lista o próprio, mas o POST não é revalidado); (b) **especie é texto livre** enquanto animal_lotes.especie/ambiente_criacao_animal.especie são ENUM maiúsculos (`migration.sql:218`, `156`) ⇒ KPIs de espécie por LIKE falham (`classes/ProducaoAnimal.php:462-464`); (c) identificacao sem UNIQUE no DDL (`migration.sql:193`) ⇒ brincos duplicados permitidos; (d) sem CSRF; (e) $_SESSION['user_id'] usado sem ?? (`44`).

### modulos/manejo_animal/animais_editar.php
- **Rota/acesso**: `index.php:1053-1055` (/manejo-animal/{id}/editar); link `animais_listar.php:89`, `animais_visualizar.php:91`. Guard: isLoggedIn() `12-15`; id/existência `17-28`.
- **Guard anti-IDOR**: **ausente** — não há qualquer comparação de associado_id neste arquivo; getById (`classes/ManejoAnimal.php:31-36`) também não filtra.
- **Campos/formulário**: mesmos do cadastro (`75-160`), com associado_id (`103-108`) e status pré-selecionados.
- **Regras/validações/cálculos**: `$dados = array_merge($animal, $_POST)` (`41`); valida nome/especie (`42-44`); ProducaoAnimal::atualizarAnimal (`45`).
- **Tabelas SQL usadas**: UPDATE animais SET nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem WHERE id — `classes/ProducaoAnimal.php:84-105` (14 colunas; **não** atualiza associado_id).
- **Ações/POST/redirects**: audit ANIMAL_ATUALIZADO (`46`) → /manejo-animal/{id}?atualizado=1 (`47`).
- **Riscos/gaps**: **IDOR total** (qualquer usuário autenticado edita qualquer animal por id, inclusive de outros produtores, pois nem a leitura nem a escrita filtram o dono); sem CSRF; array_merge permite sobrescrever colunas do registro com chaves POST arbitrárias antes do UPDATE (as colunas são fixas no SQL, mitigando); sem validação de lote_id pertencente ao mesmo associado.

### modulos/manejo_animal/animais_listar.php
- **Rota/acesso**: `index.php:1047-1049` (/manejo-animal); menu `includes/menu.php:146`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listagem do rebanho (RF05) com lote, origem, status e nº de registros de manejo.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: `$animais = $animalClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null))` (`25`) — mesmo risco do escopo null. Badges origemBadge (`27-31`) e statusBadge (`32-37`); total_registros vem de subquery (`86`).
- **Tabelas SQL usadas**: SELECT a.*, l.nome_lote AS lote_nome, (SELECT COUNT(*) FROM manejo_animal_registros r WHERE r.animal_id = a.id) AS total_registros FROM animais a LEFT JOIN animal_lotes l ON a.lote_id = l.id WHERE a.excluido = 0 [AND a.associado_id = ?] ORDER BY a.criado_em DESC — `classes/ManejoAnimal.php:15-28`.
- **Ações/POST/redirects**: exclusão por **GET** `?excluir=id` (`17-23`) → UPDATE animais SET excluido = 1 WHERE id = ? (`classes/ManejoAnimal.php:93`) → audit ANIMAL_EXCLUIDO (`20`) → redirect (`21`). O resultado do soft delete não é verificado (`19`).
- **Riscos/gaps**: **IDOR de exclusão de animais** (sem posse nem nível, `17-23`); sem CSRF (exclusão por GET); especie e origem_conversao ecoadas em badge cru (`84-85`) — valor fora do mapa cai em bg-secondary, mas o texto é impresso sem htmlspecialchars; subquery de total_registros não filtra excluido/período.

### modulos/manejo_animal/animais_visualizar.php
- **Rota/acesso**: `index.php:1056-1058` (/manejo-animal/{id} numérico); links `animais_listar.php:88`, `animais_cadastrar.php:53`. Guard: isLoggedIn() `10-13`; animal inexistente → mensagem (`17-24`).
- **Guard anti-IDOR (presente, mas inconsistente)**: `27-33` — !hasPermission(NIVEL_GESTOR) && (int)$animal['associado_id'] !== (int)$user->getAssociadoId() ⇒ nega. Usa getAssociadoId() (que pode ser null), enquanto `lotes_editar.php:32` e `ambientes_editar.php:32` usam getAssociadoIdResolvido() ⇒ critérios diferentes para o mesmo tipo de decisão.
- **Propósito de negócio**: ficha do animal + histórico de manejo + lançamento de registros clínicos/vacinação/alimentação.
- **Campos/formulário**: painel-resumo lê especie, raca, identificacao, sexo, data_nascimento, status, lote_nome, peso_kg, tipo_entrada, fornecedor_origem (`99-108`). Formulário de registro: tipo_registro select CLINICO|VACINACAO|ALIMENTACAO **required** (`128-132`); data_registro date **required** default hoje (`136`); descricao (`140`); produto_utilizado (`144`); dose_quantidade (`148`); responsavel (`152`); observacoes (`156`).
- **Regras/validações/cálculos**: `empty($dados['tipo_registro']) || empty($dados['data_registro'])` (`60-62`); criado_por = (int)$_SESSION['user_id'] (`57`). Rótulos/badges de tipo em `73-77` com fallback ?? [$reg['tipo_registro'],'bg-secondary','bi-journal'] (`174`).
- **Tabelas SQL usadas**: INSERT INTO manejo_animal_registros (animal_id, tipo_registro, data_registro, descricao, produto_utilizado, dose_quantidade, responsavel, observacoes, criado_por) — `classes/ManejoAnimal.php:100-113`; SELECT * FROM manejo_animal_registros WHERE animal_id = ? ORDER BY data_registro DESC — `classes/ManejoAnimal.php:40-41`; DELETE FROM manejo_animal_registros WHERE id = ? — `classes/ManejoAnimal.php:119`.
- **Ações/POST/redirects**: (1) `?remover_registro=id` por **GET** (`39-44`) → removerRegistro (**hard delete**) → audit MANEJO_REGISTRO_EXCLUIDO (`41`) → redirect (`42`); (2) POST adicionar registro (`47-69`) → audit MANEJO_REGISTRO_ADICIONADO (`65`) → ?registrado=1 (`66`). Exibição do link de remoção em `180`.
- **Riscos/gaps**: **(1) IDOR de exclusão de registros**: `?remover_registro=N` só valida is_numeric (`39`) e o DELETE (`classes/ManejoAnimal.php:119-120`) não confirma que o registro pertence ao animal/associado verificado ⇒ apaga registros de qualquer produtor; **(2) hard delete** de histórico sanitário (sem soft delete, sem trilha do conteúdo removido); (3) sem CSRF (exclusão por GET); (4) o select de tipo oferece 3 valores, mas o ENUM do banco tem 7 (REPRODUCAO, PARASITARIO, BEM_ESTAR, OBITO, `migration.sql:1066`) — recursos existentes no esquema sem UI; (5) **periodo_carencia_horas existe na tabela (`migration.sql:1071`) mas não há campo no formulário** ⇒ carência não registrável neste fluxo (só em sanidade); (6) a checagem IDOR usa getAssociadoId() podendo negar acesso legítimo de usuários da tabela usuarios sem associado_id direto (`classes/User.php:316-324`).

### Síntese do grupo (modulos/manejo_animal)
**Fluxo ponta-a-ponta**
1. /manejo-animal (`index.php:1047`) → lista rebanho filtrado por getAssociadoId() (`animais_listar.php:25`) com lote e contagem de registros (`classes/ManejoAnimal.php:15-28`).
2. /manejo-animal/novo → cadastro com origem/conversão, lote, peso, tipo de entrada (`animais_cadastrar.php:28-45`) → INSERT animais + UPDATE animais de anexos (`classes/ManejoAnimal.php:45-69`) → ficha do animal.
3. /manejo-animal/{id} (ficha) → histórico + lançamento de registros clínicos/vacinação/alimentação (`animais_visualizar.php:47-69`) → manejo_animal_registros.
4. /manejo-animal/{id}/editar → UPDATE animais por ProducaoAnimal::atualizarAnimal (`animais_editar.php:45`, `classes/ProducaoAnimal.php:81-106`) — **sem guard de posse**.
5. Integração com produção animal: o animal alimenta (a) o lote_id usado em animal_lotes/rastreabilidade; (b) os SELECTs de animal em alimentação/sanidade/reprodução (`alimentacao_cadastrar.php:18`, `sanidade_cadastrar.php:17`, `reproducao_cadastrar.php:17`); (c) a contagem animais_total dos lotes e os KPIs do painel (`classes/ProducaoAnimal.php:19`, `460-468`); (d) manejo_registros no painel (`482-487`). **Não há** vínculo ambiente↔animal, nem histórico de movimentação de lote.

**Tabelas de banco consolidadas**: animais, animal_lotes, manejo_animal_registros, audit_log (mesmos DDL/linhas da tabela consolidada da seção 1). Todas as escritas passam por AuditLog::registrar (`classes/AuditLog.php:13-27`), exceto a falha silenciosa quando create() retorna 0 (`animais_cadastrar.php:51-52`).

---

## 3. classes/

### classes/ManejoAnimal.php
- **Rota/acesso**: classe base (não roteável); consumida por `animais_*.php` e herdada por ProducaoAnimal (`classes/ProducaoAnimal.php:13`).
- **Propósito de negócio**: CRUD de animais e do histórico manejo_animal_registros.
- **Campos/formulário**: n/a (mapeia os campos dos formulários de manejo).
- **Regras/validações/cálculos**: __construct obtém a conexão singleton (`8-11`); create (`45-69`) faz INSERT e, **condicionalmente** (lote_id || peso_kg || tipo_entrada, `65`), o UPDATE de anexos (`74-89`); nenhum cálculo/normalização de unidade.
- **Tabelas SQL usadas**: SELECT a.*, l.nome_lote ... (subquery manejo_animal_registros) FROM animais a LEFT JOIN animal_lotes l ... (`15-28`); SELECT a.*, l.nome_lote ... WHERE a.id = ? AND a.excluido = 0 (`33-34`); SELECT * FROM manejo_animal_registros WHERE animal_id = ? (`40-41`); INSERT INTO animais (...) (`48-62`); UPDATE animais SET lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem WHERE id = ? (`77-78`); UPDATE animais SET excluido = 1 WHERE id = ? (`93`); INSERT INTO manejo_animal_registros (...) (`100-113`); DELETE FROM manejo_animal_registros WHERE id = ? (`119`).
- **Ações/POST/redirects**: n/a (retorna ids/bools).
- **Riscos/gaps**: (a) **nenhum get* filtra por associado_id** (`31-43`) — a defesa contra IDOR depende exclusivamente de cada tela, e três telas não a implementam; (b) getRegistros não valida o dono do animal; (c) removerRegistro é **hard delete** sem escopo (`117-121`); (d) create não valida campos obrigatórios e faz trim($dados['nome_animal'])/especie (`53-54`) — em PHP 8.1+ trim(null) deprecado se ausentes; (e) atualizarAnexos sobrescreve com NULL qualquer campo não reenviado (`81-86`) — perda silenciosa de dados de origem em edição parcial.
- **SQL injection**: não há SQL concatenado nesta classe; todos os valores vão por placeholders (`27-28`, `35`, `41`, `62`, `80-88`, `94`, `114`, `120`).

### classes/ProducaoAnimal.php
- **Rota/acesso**: classe de domínio (não roteável); instanciada em 17 telas de `modulos/producao_animal` e em `modulos/manejo_animal/animais_cadastrar.php:18` / `animais_editar.php:30`.
- **Propósito de negócio**: controles de produção animal orgânica — lotes, alimentação (arts. 34-37), sanidade (arts. 59-63), reprodução, ambiente (arts. 38-44), densidades de referência e estatísticas (Portaria MAPA 52/2021, Título III — comentário `5-12`).
- **Campos/formulário**: define as colunas persistidas de cada formulário (ver seções 1/2).
- **Regras/validações/cálculos**: **a classe não contém regra de negócio normativa** — nenhuma validação de 15%/20% MS, nenhuma contagem de 2 tratamentos/ano, nenhum cálculo de carência/liberação, nenhum cálculo de densidade/ocupação, nenhuma métrica reprodutiva (IEP/DEL). Toda a validação existe apenas nas telas (e parcialmente). Normalizações: `!empty() ? (int)/(float)/trim() : null` em todos os INSERT/UPDATE; defaults 'ATIVO'/'ORGANICO' (`96-97`, `152`, `174`) e produto_normatizado default 1 (`234`, `264`), numero_tratamento default 1 (`238`, `268`).
- **Métodos**: getLotes `17-30`; getLoteById `32-37`; criarLote `39-55`; atualizarLote `57-71`; softDeleteLote `73-77`; atualizarAnimal `81-106`; getAlimentacoes `110-126`; getAlimentacaoById `128-133`; criarAlimentacao `135-157`; atualizarAlimentacao `159-178`; softDeleteAlimentacao `180-184`; getSanidades `188-204`; getSanidadeById `206-211`; criarSanidade `213-243`; atualizarSanidade `245-272`; softDeleteSanidade `274-278`; getReproducoes `282-297`; getReproducaoById `299-304`; criarReproducao `306-329`; atualizarReproducao `331-351`; softDeleteReproducao `353-357`; getAmbientes `361-373`; getAmbienteById `375-380`; criarAmbiente `382-404`; atualizarAmbiente `406-425`; softDeleteAmbiente `427-431`; getDensidades `435-447`; estatisticas `451-502`.
- **Tabelas SQL usadas (consolidado da classe)**: animal_lotes (`19-20`, `34`, `42-43`, `60-61`, `75`, `470`); animais (`19`, `84-87`, `460-466`); controle_alimentacao_animal (`112-116`, `130`, `138-141`, `162-164`, `182`); controle_sanidade_animal (`190-194`, `208`, `216-221`, `248-252`, `276`); controle_reproducao_animal (`284-287`, `301`, `309-312`, `334-337`, `355`); ambiente_criacao_animal (`363`, `377`, `385-388`, `409-411`, `429`); densidades_maximas_animais (`437`); manejo_animal_registros + animais (`482-484`).
- **Ações/POST/redirects**: n/a.
- **Riscos/gaps**: (a) **todos os get*ById ignoram associado_id** (`34`, `130`, `208`, `301`, `377`) — é a raiz dos IDOR de alimentação/sanidade/reprodução; (b) **macho_reprodutor_id gravado como string via trim** (`320`, `343`) contra coluna INTEGER (`migration.sql:666`); (c) **especie não persistida** em reprodução (`309-312`); (d) atualizarLote/atualizarAmbiente/atualizarAnimal/atualizarAlimentacao/atualizarSanidade/atualizarReproducao **nunca alteram associado_id** — positivo para integridade, mas implica que lotes criados por gestor com associado_id NULL (`46`) ficam permanentemente sem dono e inacessíveis ao produtor pela listagem escopada, enquanto o anti-IDOR de `lotes_editar.php:31-32` exige donoLote > 0; (e) estatisticas reutiliza o mesmo $where em quatro tabelas (`453-480`) — acoplamento frágil; (f) softDelete* não recebe/valida usuário nem dono (`75`, `182`, `276`, `355`, `429`); (g) sem SQL concatenado com entrada de usuário ⇒ **sem SQL injection** nesta classe (todos os prepare/execute com placeholders).
- **Inconsistência de campos**: animais.especie texto livre (`migration.sql:191`) × animal_lotes.especie VARCHAR(60) (`218`) × ambiente_criacao_animal.especie ENUM (`156`) — três representações para o mesmo conceito.

### classes/LoteProducao.php
- **Rota/acesso**: **não pertence ao módulo Produção Animal**. É usada por `modulos/caderno_campo/lotes_producao/{cadastrar,editar,listar,rastrear}.php` (`cadastrar.php:5,29`; `editar.php:5,25`; `listar.php:5,15`; `rastrear.php:5,19`) e instanciada no roteador para resolver UUID: `index.php:367-383` (/lote/{id|uuid}) e `index.php:385-401` (/caderno-campo/lote/{id|uuid}).
- **Propósito de negócio**: CRUD do lote de **produção vegetal** (lotes_producao: produto_id, quantidade, unidade_medida) com UUID público de rastreio e recuperação das atividades/insumos da lavoura do produto.
- **Campos/formulário**: uuid (gerado se ausente, `22-30`), nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes (`32-43`, `186-193`).
- **Regras/validações/cálculos**: sanitização integral de entrada por Sanitizer::sanitizeArray (`19`, `185`; implementação em `classes/Sanitizer.php:119-131` → sanitizeString remove tags/trim/remove caracteres de controle, `15-21`); geração de UUID v4 com mt_rand (`23-29`); associado_id convertido para int ou NULL (`39`); nenhum cálculo de quantidade/unidade ou de rendimento.
- **Tabelas SQL usadas**: INSERT INTO lotes_producao (uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, criado_em) (`32-33`); SELECT ... FROM lotes_producao lp LEFT JOIN produtos p ON lp.produto_id = p.id WHERE lp.excluido = 0 (`58-62` — getAll **sem filtro de associado**); getByAssociadoId com LEFT JOIN produtos → lavouras → propriedades e regra `lp.associado_id = :id OR (lp.associado_id IS NULL AND p.associado_id = :id)` (`79-86`); getByUuid (`104`); getById (`121`); getAtividadesByLoteId JOIN atividades_diarias (`138-143`); getInsumosByLoteId (ad.insumo_utilisado, `161-166`); UPDATE lotes_producao SET nome_lote, produto_id, data_producao, quantidade, unidade_medida, observacoes WHERE id (`186`); UPDATE lotes_producao SET excluido = 1 WHERE id (`209`).
- **Ações/POST/redirects**: n/a na classe (retorna bool/array); catch (PDOException) em todos os métodos faz error_log e devolve false/[] (`46-49`, `66-69`, `91-94`, `148-151`, `171-174`, `196-199`, `212-215`).
- **Riscos/gaps**: (a) **nenhum método valida a posse pelo usuário logado** — getById/getByUuid/update/delete aceitam qualquer id/uuid; o filtro por associado existe apenas em getByAssociadoId (`77-95`) e não em getAll (`56-70`); (b) UUID gerado com mt_rand (não CSPRNG) (`23-29`) e usado como identificador público de rastreio (getByUuid, `102-112`; rotas `index.php:367-372`, `385-391`) ⇒ previsibilidade teórica/enumerabilidade; (c) create acessa chaves do array sem verificar existência (ex.: $dados_sanitizados['nome_lote'], `37`) ⇒ warning/erro se o formulário não enviar o campo; (d) falhas são silenciosas para o chamador (false) sem distinguir validação de erro de banco; (e) o comentário final confirma que **não existem** métodos para adicionar/remover atividades e insumos a um lote (`218`) — lotes_producao.atividades_id/insumos_id (`migration.sql:1054-1055`) não são manipulados por esta classe; (f) getInsumosByLoteId depende da coluna grafada insumo_utilisado (`161`) — nome com erro de digitação que precisa existir em atividades_diarias; (g) sem SQL concatenado ⇒ sem SQL injection; (h) **escopo**: esta classe não é referenciada por nenhuma tela de producao_animal — "lote" aqui é lote de produto vegetal, não lote de animais.

### Síntese do grupo (classes/)
**Fluxos ponta-a-ponta**
1. ManejoAnimal (base) → CRUD de animais + manejo_animal_registros; não impõe escopo de associado.
2. ProducaoAnimal extends ManejoAnimal (`classes/ProducaoAnimal.php:13`) → herda getAll/getById/getRegistros/create/atualizarAnexos/softDelete/adicionarRegistro/removerRegistro e adiciona lotes, alimentação, sanidade, reprodução, ambientes, densidades e estatísticas. **A herança é o único ponto de integração entre os dois módulos** (as telas de alimentação/sanidade/reprodução chamam getAll() herdado para popular os selects de animal, `alimentacao_cadastrar.php:18`, `sanidade_cadastrar.php:17`, `reproducao_cadastrar.php:17`).
3. LoteProducao é uma classe **independente e não relacionada** (sem herança, sem require de ManejoAnimal/ProducaoAnimal) — pertence ao caderno de campo (produção vegetal) e compartilha apenas o singleton Database e o Sanitizer.
4. Todas as três usam exclusivamente PDO com prepare/execute; LoteProducao é a única que sanitiza entrada na camada de classe (Sanitizer::sanitizeArray).

**Tabelas de banco consolidadas (classes)**
| Tabela | Classe que escreve | Classe que lê |
|---|---|---|
| animais | ManejoAnimal (`48-62`, `77-78`, `93`), ProducaoAnimal (`84-87`) | ManejoAnimal (`15-28`, `33`), ProducaoAnimal (`19`, `460-466`, `482-484`) |
| manejo_animal_registros | ManejoAnimal (`100-113`, `119`) | ManejoAnimal (`16`, `40-41`), ProducaoAnimal (`482-484`) |
| animal_lotes | ProducaoAnimal (`42-43`, `60-61`, `75`) | ProducaoAnimal (`19-20`, `34`, `470`) |
| controle_alimentacao_animal | ProducaoAnimal (`138-141`, `162-164`, `182`) | ProducaoAnimal (`112-116`, `130`) |
| controle_sanidade_animal | ProducaoAnimal (`216-221`, `248-252`, `276`) | ProducaoAnimal (`190-194`, `208`, `474`) |
| controle_reproducao_animal | ProducaoAnimal (`309-312`, `334-337`, `355`) | ProducaoAnimal (`284-287`, `301`, `478`) |
| ambiente_criacao_animal | ProducaoAnimal (`385-388`, `409-411`, `429`) | ProducaoAnimal (`363`, `377`) |
| densidades_maximas_animais | — | ProducaoAnimal (`437`) |
| lotes_producao | LoteProducao (`32-33`, `186`, `209`) | LoteProducao (`58-62`, `79-86`, `104`, `121`) |
| produtos, lavouras, propriedades, atividades_diarias | — | LoteProducao (`60`, `81-83`, `140-141`, `163-164`) |
| audit_log | — | escritas por AuditLog (`classes/AuditLog.php:19-21`) a partir das telas |

---

## 4. Achados consolidados (bugs, segurança, inconsistências)

### Segurança
1. **Ausência total de CSRF** nas 21 telas de produção/manejo animal (grep csrf = 0 em `modulos/producao_animal` e `modulos/manejo_animal`), apesar de CSRFProtection existir (`classes/CSRFProtection.php`) e ser usado em outros módulos (ex.: `modulos/associados/excluir.php:19`, `api/certificadoras.php:25-26`).
2. **IDOR de exclusão por GET** em: `lotes_listar.php:17-24`, `ambientes_listar.php:17-24`, `alimentacao_listar.php:17-24`, `sanidade_listar.php:17-24`, `reproducao_listar.php:17-24`, `animais_listar.php:17-23`. Nenhuma verifica associado_id/nível antes do soft delete.
3. **IDOR de edição** em: `alimentacao_editar.php` (sem guard em 15-56), `sanidade_editar.php` (15-56), `reproducao_editar.php` (15-62), `animais_editar.php` (17-54).
4. **IDOR de exclusão de registros de manejo**: `animais_visualizar.php:39-44` + `classes/ManejoAnimal.php:117-121` (DELETE físico sem escopo).
5. **Exposição pública enumerável**: `rastreabilidade.php` (sem login, `2-8`) com id sequencial ?lote=N e "código" AL-%06d derivado do ID (`19-24`), expondo nome do produtor e registro_cnpo (`132-134`).
6. **Escopo por getAssociadoId() que pode ser null** → consultas sem filtro de associado e leitura global do rebanho/lotes/ambientes/registros: `lotes_listar.php:26`, `ambientes_listar.php:26`, `alimentacao_listar.php:26`, `sanidade_listar.php:26`, `reproducao_listar.php:26`, `animais_listar.php:25`, `painel.php:18`, `classes/LoteProducao.php:56-70` (getAll sem filtro).
7. **Vínculo cruzado de animal/lote no POST**: `classes/ProducaoAnimal.php:145-146`, `225-226`, `316`, `animais_cadastrar.php:29` — ids aceitos sem revalidação de posse.
8. **UUID de rastreio gerado com mt_rand**: `classes/LoteProducao.php:23-29`.
9. **Sem SQL injection identificado**: todas as queries das três classes e das telas usam prepare/execute com placeholders (verificado em `classes/ProducaoAnimal.php`, `classes/ManejoAnimal.php`, `classes/LoteProducao.php`, `rastreabilidade.php:19-71`). O único ponto de interpolação dinâmica é o $where de `classes/ProducaoAnimal.php:453-458` (composto por literais + placeholder, sem entrada de usuário).

### Bugs / defeitos funcionais confirmados
1. `ambientes_cadastrar.php:16-20` usa $dados antes de `$dados = $_POST;` (`24`) → variável indefinida; filtro de densidade por espécie nunca aplica.
2. `rastreabilidade.php:69-72` consulta controle_reproducao_animal.lote_id, **coluna inexistente** (`sinporg/prisma/migrations/0_baseline/migration.sql:659-679`) → seção "Reprodução" nunca renderiza; exceção engolida por `catch (Exception $e) {}` em `75` (padrão repetido em `45`, `55`, `65`).
3. `classes/ProducaoAnimal.php:320` e `343` gravam macho_reprodutor_id como string em coluna INTEGER (`migration.sql:666`) enquanto o formulário é texto livre (`reproducao_cadastrar.php:95-96`) → falha em MySQL estrito.
4. `reproducao_cadastrar.php:121-126` coleta especie que **não é persistida** (`classes/ProducaoAnimal.php:309-328`; coluna inexistente no DDL).
5. `painel.php:27` exibe "Sendo orgânicos" fixo como "—" — indicador não implementado.
6. densidades_maximas_animais **sem seed** no repositório (só DDL `migration.sql:727-738`) → bloco de referências vazio em `ambientes_cadastrar.php:102-105` e `ambientes_editar.php:124-127`.
7. Checkbox desmarcado em edição zera tem_sombreamento_abrigo (`classes/ProducaoAnimal.php:421`) e produto_normatizado (`264`).
8. `animais_cadastrar.php:51-52` registra auditoria de criação mesmo se create() falhar/retornar 0.
9. Assimetria de validação de carência: cadastro exige > 0 e >= 96 (`sanidade_cadastrar.php:32-37`); edição exige apenas >= 96 (`sanidade_editar.php:43`) — permite gravar carência 0 quando excepcional já marcado no banco.
10. manejo_animal_registros.tipo_registro tem 7 valores no ENUM (`migration.sql:1066`) mas a UI oferece 3 (`animais_visualizar.php:128-132`); periodo_carencia_horas existe na tabela (`1071`) e não é coletado.

### Gaps de escopo normativo (não encontrado no código)
- **kg/animal/dia** e composição de dieta: só quantidade_kg total + produto_utilizado texto livre (controle_alimentacao_animal, `migration.sql:619-639`); nenhum cálculo.
- **Custo de alimentação e baixa de estoque**: 0 ocorrências de custo/estoque em `classes/ProducaoAnimal.php` e `modulos/producao_animal`; a tabela insumos (`migration.sql:946-969`) e sql/seed_insumos_aprovados_animal.sql não são referenciadas pelo módulo.
- **Limites 15%/20% MS**: apenas form-text (`alimentacao_cadastrar.php:115`); nenhum somatório de MS não orgânica por período/espécie.
- **Capacidade/ocupação de ambientes**: nenhum cálculo área ÷ densidade nem alerta de superlotação; ambiente_criacao_membros (`migration.sql:173-180`) não é usada por nenhum PHP.
- **Vacinas**: sem catálogo, lote de vacina, validade ou dose de reforço — controle_sanidade_animal guarda texto livre.
- **Prescrição**: dose_quantidade TEXT/VARCHAR(60) e via_aplicacao VARCHAR(40) (`migration.sql:691-692`) — sem estrutura de prescrição veterinária nem responsável técnico com CRMV.
- **Carência**: sem conversão para dias, sem bloqueio de venda/abate, sem verificação de data_liberacao_organica contra a data corrente.
- **2 tratamentos/ano**: nenhuma contagem em nenhum arquivo (somente texto de ajuda, `sanidade_cadastrar.php:146`, `sanidade_listar.php:42`).
- **Diagnóstico de gestação**: ausente do ENUM e da UI (`migration.sql:664`, `reproducao_cadastrar.php:76`).
- **Intervalo entre partos / dias em lactação / produção de leite**: 0 ocorrências (intervalo, lacta, producao_leite, dias_em_lactacao) em *.sql e em `classes/*.php`.
- **SISBOV/NIF**: 0 ocorrências de sisbov/NIF em *.sql e `classes/*.php`; único identificador é animais.identificacao (brinco/tag, sem UNIQUE — `migration.sql:193`).
- **Genealogia**: 0 ocorrências de pai_id/mae_id/genealog* — não há árvore genealógica.
- **Movimentações de animais**: não existe tabela de histórico de movimentação; apenas animais.lote_id (estado atual) — sem GTA/trânsito/documento sanitário.
