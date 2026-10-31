# Relatório — Correção de IDOR e ausência de autenticação em `modulos/`

**Data:** 2026-02
**Escopo:** 17 arquivos de `modulos/` (lista da missão). O diretório `api/` **não** foi alterado (outro subagente).
**Ambiente:** PHP 8.2 / XAMPP, `http://localhost/app`, DB `sisagro` (root, sem senha).
**Método:** leitura integral de cada arquivo, identificação de (i) exigência de login, (ii) natureza do dado (por associado × global) e (iii) origem do `id`; aplicação da guarda central (`classes/ApiAuth.php`, `classes/PortalAccess.php`, `classes/User.php`); `php -l` em todos; teste HTTP com fixtures `Teste@123`.

## Fixtures e registros usados (somente SELECT — nenhum dado alterado)

| Usuário | Nível | Associado | Observação |
|---|---|---|---|
| teste.admin@sinporg.local (`usuarios` 114) | 1 | — | Admin |
| teste.gestor@sinporg.local (`usuarios` 116) | 2 | — | Gestor |
| teste.comum@sinporg.local (`associados` 86) | 3 | 86 | Agricultor comum |
| teste.diretoria@sinporg.local (`associados` 88) | 3 | 88 | Diretor (cargo, não é staff) |

Registros de terceiros usados como alvo de IDOR: `carteirinhas`→assoc 13; `categorias_atividades`→id 6 (assoc 13) e id 8 (global NULL); `lotes_producao`→id 9 (assoc 13); `certificacao_participativa`→id 5 (assoc 31); `agro_categorias_atividades`→id 1 (global NULL); `ordens_producao`→id 3 (assoc 38); `ambiente_criacao_animal`→id 5 (assoc 13); `animal_lotes`→id 9 (assoc 28); `visitas_tecnicas`→id 17 (assoc 38, Agendada) e id 6 (assoc 34, Realizada); `associacoes`→id 3; `eventos`→id 9.
Testes de exclusão usaram `id=999999` (inexistente) para nunca apagar dados reais.

## Resumo (17 arquivos)

| # | Arquivo | Diagnóstico | Correção | Evidência | Status |
|---|---|---|---|---|---|
| 1 | modulos/associados/carteirinha_pdf.php | Sem `ApiAuth`; guarda própria `podeAcessarDiretoria()` deixava **nível 3 com cargo de diretoria** baixar o PDF (foto/CNPO) de **qualquer** associado; negava com `die()` e HTTP 200 | `ApiAuth::exigirLogin(false)` + `exigirProprioAssociado($id, false)` (o `id` é o `associado_id`); `400`/`403` em vez de conteúdo | antes: comum→`Acesso negado.` 200 / diretoria→`Carteirinha não encontrada` (passava a guarda). depois: comum 13→**403** `Acesso negado: você só pode acessar os seus próprios dados.`, diretoria→**403**, admin/gestor→liberado, anon→401 | **corrigido** |
| 2 | modulos/associados/carteirinha_impressa.php | Mesmo caso do item 1 (PDF binário; `id` = associado) | `ApiAuth::exigirLogin(false)` + `exigirProprioAssociado($id, false)` | comum→**403**; admin→liberado (`Associado não encontrado`) | **corrigido** |
| 3 | modulos/associacoes/editar.php | Dado **global** da equipe; só `header.php` (login). Nível 3 abria e **alterava** associações (POST sem checagem de nível) | Bloqueio `if (!$user->isGestorOuAdmin())` → "Acesso negado…" + `footer.php` + `exit` | comum GET `/associacao/editar?id=3`→`[NEGADO]`; comum POST `id=999999`→`[NEGADO]`; gestor/admin→liberado | **corrigido** |
| 4 | modulos/associacoes/excluir.php | Dado **global** + exclusão; só `header.php`. Nível 3 podia excluir associações (POST com CSRF válido) | Mesma guarda `isGestorOuAdmin()` antes do POST | comum POST `id=999999`→`[NEGADO]` (HTTP 200 com aviso); admin→"Associação não encontrada" (0 linhas afetadas) | **corrigido** |
| 5 | modulos/eventos/visualizar.php | Endpoint **JSON/AJAX** de evento (global) **sem autenticação**: anônimo lia nome/local/descrição/confirmados; botão "Editar" decidido por `$_SESSION['user_tipo']`; `$e->getMessage()` vazava erro do banco | `ApiAuth::exigirLogin()`; botão de editar via `ApiAuth::ehStaff()`; mensagens de erro genéricas | antes: anon AJAX `/evento/9`→200 com JSON do evento. depois: anon→**401** `Não autenticado…`; comum→200 (leitura permitida); admin segue com botão Editar | **corrigido** |
| 6 | modulos/eventos/excluir.php | `session_start()` + `$_SESSION['user_tipo'] === 'usuario'` (**case-sensitive**): anônimo passava (só o CSRF barrava) e nível 3 vindo de `associados` (`tipo='Usuario'`) chegava ao `DELETE` | `ApiAuth::exigirStaff()` no topo (401/403 JSON antes de qualquer coisa) | antes: comum POST→302 `listar.php?msg=excluido` (DELETE executado, 0 linhas com id falso). depois: comum→**403**, anon→**401**, gestor→302 (permitido) | **corrigido** |
| 7 | modulos/caderno_campo/categorias_atividades/editar.php | Dado **por associado** (`categorias_atividades.associado_id`); só `header.php`. Nível 3 abria/editava categoria do associado 13 e também as globais (NULL) | Guarda: `isGestorOuAdmin()` **ou** `associado_id === getAssociadoIdResolvido()` (globais = só equipe) | comum `/categoria-atividade/6/editar`→`[NEGADO]`; `/8/editar` (global)→`[NEGADO]`; admin/gestor→liberado | **corrigido** |
| 8 | modulos/caderno_campo/categorias_atividades/excluir.php | Endpoint `DELETE`; checava só `isset($_SESSION['user_id'])` — **qualquer** usuário logado apagava qualquer categoria | `ApiAuth::exigirLogin()` + `SELECT associado_id` da categoria + `ApiAuth::exigirProprioAssociado()` | comum POST→**403** `Acesso negado: você só pode acessar os seus próprios dados.`; admin→302 `?msg=excluido` (0 linhas) | **corrigido** |
| 9 | modulos/caderno_campo/propriedades/cadastrar.php | Dado **por associado**. A checagem usava `$_SESSION['user_tipo'] === 'usuario'` mas o banco grava `'Usuario'` → **restrição nunca aplicada**; e quando `$meu_associado_id` não era resolvido, o `associado_id` do request era aceito no `INSERT` (IDOR de escrita) | Reescrito: `$isStaff = isGestorOuAdmin()`; `$meu_associado_id = getAssociadoIdResolvido()`; nível 3 com `associado_id` de terceiro → `403`; no POST o `associado_id` é **forçado** ao do usuário para não-staff | comum `/caderno-campo/propriedade/novo?associado_id=13`→`[NEGADO]` (modal Permissão Negada); `/caderno-campo/propriedade/novo` (próprio)→liberado; admin com `?associado_id=13`→liberado | **corrigido** |
| 10 | modulos/caderno_campo/lotes_producao/rastrear.php | Dado **por associado** (`lotes_producao.associado_id`, + produto/lavoura/propriedade). Só login: nível 3 via rastreabilidade completa de lote alheio | Guarda por `$lote['associado_id']` vs `getAssociadoIdResolvido()` (staff passa) | comum `/caderno-campo/lote/9/rastrear` (assoc 13)→`[NEGADO]`; admin/gestor→liberado | **corrigido** |
| 11 | modulos/certificacao/renovar.php | Dado **por associado** (`certificacao_participativa.associado_id`); só login. Nível 3 abria a renovação (com upload de PDF assinado) de certificação de outro produtor (assoc 31) | Guarda por `$cert['associado_id']` | comum `/certificacao/5/renovar`→`[NEGADO]`; admin/gestor→liberado | **corrigido** |
| 12 | modulos/agroindustria/categorias_editar.php | Dado **por associado** (`agro_categorias_atividades.associado_id`); `temAgroindustria()` apenas verificava "é do ramo" → qualquer agro produtor editava categoria de outro (e as globais NULL) | Guarda por `$categoria['associado_id']` (globais = só equipe) | comum→`Acesso restrito a produtores de agroindústria` (gate pré-existente); admin `/agroindustria/categoria/1/editar`→liberado. IDOR cruzado **não exercitável** com as fixtures atuais (ver pendências) | **corrigido** |
| 13 | modulos/agroindustria/ordens_visualizar.php | Dado **por associado** (`ordens_producao.associado_id`); só login. Nível 3 lia ordem, lotes primários, balanço de massas e selo de outro produtor (assoc 38) | Guarda por `$op['associado_id']` | comum `/agroindustria/ordens/3`→`[NEGADO]`; admin/gestor→liberado | **corrigido** |
| 14 | modulos/producao_animal/ambientes_editar.php | Dado **por associado** (`ambiente_criacao_animal.associado_id`); só login → nível 3 editava ambiente de outro (assoc 13) | Guarda por `$ambiente['associado_id']` | comum `/producao-animal/ambiente/5/editar`→"Acesso negado: este ambiente pertence a outro produtor."; admin/gestor→liberado | **corrigido** |
| 15 | modulos/producao_animal/lotes_editar.php | Dado **por associado** (`animal_lotes.associado_id`); só login → nível 3 editava lote de animais de outro (assoc 28) | Guarda por `$lote['associado_id']` | comum `/producao-animal/lote/9/editar`→"Acesso negado: este lote pertence a outro produtor."; admin/gestor→liberado | **corrigido** |
| 16 | modulos/roteiros_inspecao/visitas/realizar.php | Dado **por associado** (`visitas_tecnicas.associado_id`); só login → nível 3 abria o checklist de inspeção de visita alheia (assoc 38) e podia enviá-lo | Guarda por `$visita['associado_id']` (antes da validação de status) | comum `/visita-tecnica/17/realizar`→`[NEGADO]`; admin/gestor→liberado | **corrigido** |
| 17 | modulos/roteiros_inspecao/visitas/relatorio.php | Dado **por associado** (visita); só login. A página não lê a visita no PHP (o Vue consome a API), então nível 3 via relatório de outro produtor (assoc 34) | `SELECT associado_id FROM visitas_tecnicas` + guarda por dono antes de renderizar | comum `/visita-tecnica/6/relatorio`→`[NEGADO]`; admin/gestor→liberado | **corrigido** |

Nenhum dos 17 arquivos estava "já protegido": todos exigiam login (ou nem isso) mas nenhum comparava o dono do registro com o associado do usuário.

## Testes executados

Harness: `tmp/idor_modulos_test.php` (próprio, em `tmp/`) e `tmp/portal_http_test.php get` (existente). Login via `POST /login` com `Teste@123`; cookie jar por cenário.

1. **Sintaxe (17/17):**
   `php -l <arquivo>` → `No syntax errors detected in ...` para todos os 17.

2. **Comum (associado 86) — antes × depois:**
   ```
   php tmp/idor_modulos_test.php get teste.comum@sinporg.local 'Teste@123' \
     /associado/13/carteirinha-pdf /associacao/editar?id=3 /categoria-atividade/6/editar \
     /categoria-atividade/8/editar "/caderno-campo/propriedade/novo?associado_id=13" \
     /caderno-campo/lote/9/rastrear /certificacao/5/renovar /agroindustria/ordens/3 \
     /producao-animal/ambiente/5/editar /producao-animal/lote/9/editar \
     /visita-tecnica/17/realizar /visita-tecnica/6/relatorio
   ```
   - **antes:** todos HTTP 200 com conteúdo (exceto carteirinhas, que usavam `die('Acesso negado.')`).
   - **depois:** todos `[NEGADO]` (403 para os binários; HTTP 200 + "Acesso negado…" nas páginas HTML), e `/caderno-campo/propriedade/novo` (sem `associado_id`, próprio) permaneceu `[liberado]` — caminho legítimo preservado.

3. **Staff não foi super-bloqueado:** `php tmp/idor_modulos_test.php get teste.admin@sinporg.local 'Teste@123' <mesmas urls>` e com `teste.gestor@sinporg.local` → **todos `[liberado]`** (HTTP 200, sem aviso de negação).

4. **Anônimo:** `php tmp/idor_modulos_test.php anon /evento/9 /associacao/editar?id=3`
   - `GET(anon,ajax) /evento/9` → **HTTP 401** `{"erro":"Não autenticado. Faça login para acessar este recurso."}` (antes: HTTP 200 com o JSON completo do evento).
   - `/associacao/editar?id=3` → **302** para `/login`.

5. **Endpoints POST (com token CSRF real, `id=999999`):**
   ```
   php tmp/idor_modulos_test.php post <usuario> 'Teste@123' <url> id=999999
   ```
   | Endpoint | comum (n3) | anônimo | gestor/admin |
   |---|---|---|---|
   | `/modulos/eventos/excluir.php` | **403** `Você não tem permissão…` | **401** `Não autenticado…` | 302 `listar.php?msg=excluido` |
   | `/modulos/caderno_campo/categorias_atividades/excluir.php` | **403** `Acesso negado: você só pode acessar os seus próprios dados.` | — | 302 `?msg=excluido` |
   | `/modulos/associacoes/excluir.php` (POST) | `[NEGADO]` | — | "Associação não encontrada" |
   | `/associacao/editar` (POST) | `[NEGADO]` | — | liberado |

6. **Diretoria (nível 3 com cargo — mudança de comportamento):** `php tmp/idor_modulos_test.php get teste.diretoria@sinporg.local 'Teste@123' /associado/13/carteirinha-pdf` → **403** (antes passava pela guarda `podeAcessarDiretoria()` e só esbarrava em "Carteirinha não encontrada").

## Arquivos alterados

`modulos/associados/carteirinha_pdf.php`, `modulos/associados/carteirinha_impressa.php`, `modulos/associacoes/editar.php`, `modulos/associacoes/excluir.php`, `modulos/eventos/visualizar.php`, `modulos/eventos/excluir.php`, `modulos/caderno_campo/categorias_atividades/editar.php`, `modulos/caderno_campo/categorias_atividades/excluir.php`, `modulos/caderno_campo/propriedades/cadastrar.php`, `modulos/caderno_campo/lotes_producao/rastrear.php`, `modulos/certificacao/renovar.php`, `modulos/agroindustria/categorias_editar.php`, `modulos/agroindustria/ordens_visualizar.php`, `modulos/producao_animal/ambientes_editar.php`, `modulos/producao_animal/lotes_editar.php`, `modulos/roteiros_inspecao/visitas/realizar.php`, `modulos/roteiros_inspecao/visitas/relatorio.php`.
Nenhum arquivo de `api/` foi tocado. Nenhum dado foi inserido/alterado/excluído no banco (somente `SELECT`; os POSTs de exclusão usaram `id` inexistente).

## Pendências

1. **APIs por trás das páginas (outro subagente / `api/`).** A guarda de página não protege o endpoint chamado por ela. Precisam da mesma checagem de dono:
   - `api/roteiros_inspecao.php` (`action=visitas` PUT usado por `realizar.php`; `action=relatorio` que gera o PDF linkado em `relatorio.php`; `action=upload_evidencia`);
   - `api/certificacao/controller.php` (`action=upload_assinatura` usado por `renovar.php`);
   - `api/get_propriedades_associado.php`, `api/get_propriedade_detalhes.php`, `api/get_associacao_detalhes.php`, `api/relatorios/*`.
   Sem isso, o IDOR continua acessível chamando a API direto.
2. **Diretoria Executiva perdeu acesso a dados de terceiros.** A missão determina "nível 3 só o próprio associado", então `carteirinha_pdf/impressa` (e demais telas por associado) passaram a negar o nível 3 com cargo de diretoria. Se a regra de negócio documentada em `PortalAccess::ehPrivilegiado()`/`podeAcessarAssociadoId()` deve valer para a Diretoria, é preciso acrescentar explicitamente `|| PortalAccess::ehPrivilegiado()` — **sempre após** `ApiAuth::exigirLogin()`, porque `PortalAccess::podeAcessarAssociadoId()` retorna `true` para visitante anônimo. Decisão de negócio pendente.
3. **Caminho positivo "dono do registro" não foi exercitado de ponta a ponta:** os associados de teste 86/87/88/89 não possuem nenhum registro em `propriedades`, `lotes_producao`, `certificacao_participativa`, `visitas_tecnicas`, `carteirinhas`, `ambiente_criacao_animal`, `animal_lotes`, `ordens_producao` nem `agro_categorias_atividades`. Só foi possível validar o ramo "próprio" em `carteirinha_pdf` (assoc 86 → passou a guarda e retornou "Carteirinha não encontrada") e no formulário de propriedade própria. Criar fixtures de dados (com autorização de escrita no banco) para fechar o teste positivo.
4. **IDOR cruzado em `agroindustria/categorias_editar.php` não exercitado:** nenhum usuário de teste tem `tem_agroindustria` e o gate pré-existente `temAgroindustria()` barra o comum antes da nova checagem. Criar uma fixture de produtor de agroindústria (nível 3) para validar.
5. **Categorias globais (`associado_id` NULL)** em `categorias_atividades`/`agro_categorias_atividades` agora só podem ser editadas/excluídas pela equipe. Confirmar se é o comportamento desejado (são categorias-semente; usuários comuns veem a lista).
6. **CSRF ausente em `modulos/associacoes/editar.php`** (POST sem `CSRFProtection::validatePostRequest()`) — pré-existente, fora do escopo desta missão (a autorização já foi corrigida).
7. **Vazamento de mensagem de erro em `modulos/eventos/excluir.php`**: o redirect de falha ainda carrega `&msg=<mensagem do PDO>` na query string (agora só alcançável por Admin/Gestor). Recomenda-se remover.
8. **Telas irmãs de cadastro/edição** (`ordens_cadastrar.php`, `ambientes_cadastrar.php`, `lotes_cadastrar.php`, `propriedades/editar.php`, `visitas/agendar.php`, `carteirinha_gerar.php`/`carteirinha_enviar.php`, `certificacao/editar.php`, `certificacao/historico.php`) têm o mesmo padrão de risco e não estavam na lista dos 17 — recomenda-se varredura semelhante.
9. **Versionamento:** os diretórios `modulos/agroindustria/` e `modulos/producao_animal/` estão **untracked** no git (aparecem como `?? pasta/`), então essas 4 correções não aparecem em `git diff`; considerar adicioná-los ao controle de versão.
10. **Fixtures sem dados:** para reprodutibilidade, documentar a criação das contas de teste (a senha `Teste@123` e as contas já existem), evitando uso de contas reais.
