# Correção de autorização em endpoints AJAX/API — fevereiro/2026

**Escopo:** fechar o acesso não autenticado dos endpoints `api/relatorios/*.php` e `api/*.php` (primeiro nível) que ainda respondiam a visitantes anônimos.
**Ambiente:** PHP 8.2.12 (XAMPP/Apache 2.4.58) em `http://localhost/app`, MySQL `sisagro`.
**Guarda utilizada:** `classes/ApiAuth.php` (já existente — não alterado).
**Data:** 2026-02-14.

---

## 1. Resumo executivo

| Situação | Quantidade |
|---|---|
| Endpoints de `api/relatorios/*.php` corrigidos | 11 |
| Endpoints de `api/*.php` (1º nível) corrigidos | 3 |
| **Total de arquivos alterados** | **14** |
| Exceções públicas confirmadas (sem login por design) | 4 |
| Endpoints que já estavam protegidos antes desta tarefa (não alterados) | 16 (`api/*.php` 1º nível) + 9 (`api/relatorios/`) |
| Arquivos fora do escopo com acesso anônimo (pendências encaminhadas) | 3 |

Resultado: **nenhum endpoint do escopo responde mais a visitante anônimo** — todos passaram a devolver `401` sem sessão, com `200` para o perfil autorizado e `403` para o perfil sem permissão.

---

## 2. Método — fonte de verdade dos níveis

1. **Relatórios** (`api/relatorios/*.php`): o nível foi lido de `relatorios.php` → `$reportCategories[...]['reports'][<slug>]['level']` (linhas 53–189). A regra de bloqueio da própria tela é `if ($userLevel > $report['level'])` (linha 202), portanto:
   - `level 1` → somente Admin;
   - `level 2` → Admin e Gestor;
   - `level 3` → qualquer usuário logado.
   A guarda foi aplicada com `ApiAuth::exigirNivel(<level>, false)` — `false` porque estes endpoints devolvem **HTML** (fragmento de tabela), não JSON.
2. **`api/*.php` de primeiro nível**: localizei a página/JS que chama cada arquivo (`grep` pelo nome em `*.php`/`*.js`) e usei a restrição real da página:
   - `api/get_associacao_detalhes.php` ← `modulos/associacoes/listar.php` (exige `isLoggedIn()`) → **`exigirLogin()`**;
   - `api/get_presenca_evento.php` ← `modulos/eventos/listar.php` (exige `hasPermission(NIVEL_USUARIO)`, linha 32) → **`exigirLogin()`**;
   - `api/limpar_modal_eventos.php` ← `includes/header.php` (toda página autenticada) → **`exigirLogin()`**.
3. **IDOR nos relatórios de nível 3**: espelhei `classes/ReportManager.php`, que nos métodos `getAtividadesReport`/`getInsumosReport`/`getLotesReport` aplica `WHERE <tabela>.associado_id = :user_id` sempre que `user_nivel > NIVEL_GESTOR`. Apliquei o mesmo filtro usando `ApiAuth::nivel()` e `ApiAuth::associadoId()`.
4. Toda guarda foi inserida **imediatamente após os `require_once`/`session_start()` e antes de qualquer query**, com comentário curto em português explicando o motivo.

---

## 3. Arquivos alterados, nível aplicado e justificativa

### 3.1 `api/relatorios/*.php` (11 arquivos)

| Arquivo | Nível (`relatorios.php`) | Guarda aplicada | Justificativa |
|---|---|---|---|
| `api/relatorios/visao-geral.php` | 1 | `exigirNivel(1, false)` | `$reportCategories['dashboard']['reports']['visao-geral']['level'] = 1`. A tela bloqueia Gestor (`$userLevel > 1`). Expõe contadores globais (associados, inadimplência). |
| `api/relatorios/movimentacoes.php` | 2 | `exigirNivel(2, false)` | `$reportCategories['estoque']...['movimentacoes']['level'] = 2`. |
| `api/relatorios/fluxo-caixa.php` | 2 | `exigirNivel(2, false)` | `$reportCategories['financeiro']...['fluxo-caixa']['level'] = 2`. Dados financeiros da associação. |
| `api/relatorios/contas-pagar.php` | 2 | `exigirNivel(2, false)` | `...['contas-pagar']['level'] = 2`. |
| `api/relatorios/contas-receber.php` | 2 | `exigirNivel(2, false)` | `...['contas-receber']['level'] = 2`. |
| `api/relatorios/previsao-receita.php` | 2 | `exigirNivel(2, false)` | `$reportCategories['associacao']...['previsao-receita']['level'] = 2`. |
| `api/relatorios/fluxo-caixa-realizado-previsto.php` | 2 | `exigirNivel(2, false)` | `...['fluxo-caixa-realizado-previsto']['level'] = 2`. |
| `api/relatorios/pagamentos-por-forma.php` | 2 | `exigirNivel(2, false)` | `...['pagamentos-por-forma']['level'] = 2`. |
| `api/relatorios/estoque-atual.php` | 3 | `exigirNivel(3, false)` **+ filtro por associado** | `$reportCategories['estoque']...['estoque-atual']['level'] = 3`. Usuário comum passa a ver somente `insumos.associado_id = ApiAuth::associadoId()`. |
| `api/relatorios/atividades-diarias.php` | 3 | `exigirNivel(3, false)` **+ filtro por associado** | `$reportCategories['atividades']...['atividades-diarias']['level'] = 3`. Comum vê somente `atividades_diarias.associado_id` próprio. |
| `api/relatorios/produtividade.php` | 3 | `exigirNivel(3, false)` **+ filtro por associado** | `...['produtividade']['level'] = 3`. Idem, na mesma tabela. |

Exemplo do bloco inserido (estoque-atual):

```php
// Seguranca: exige nivel 3 (fonte de verdade: relatorios.php -> reportCategories['estoque']['level']).
// Nivel 1 = Admin, 2 = Admin/Gestor, 3 = qualquer usuario logado (semantica de User::hasPermission).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirNivel(3, false); // false = resposta em texto, pois o endpoint devolve HTML
```

Exemplo do filtro IDOR (estoque-atual):

```php
// IDOR: usuario de nivel 3 (comum) so pode ver os insumos do proprio associado.
// Mesma regra de ReportManager::getInsumosReport (nivel > NIVEL_GESTOR filtra por associado).
$where = '';
$params = [];
if (ApiAuth::nivel() > NIVEL_GESTOR) {
    $where = ' WHERE associado_id = :associado_id';
    $params[':associado_id'] = ApiAuth::associadoId();
}
```

### 3.2 `api/*.php` de primeiro nível (3 arquivos)

| Arquivo | Guarda aplicada | Justificativa |
|---|---|---|
| `api/get_associacao_detalhes.php` | `ApiAuth::exigirLogin()` | Respondia `{"success":true,"data":{...}}` com CNPJ/endereço/nome de **qualquer** associação para anônimos (`getDetalhesCompletos($id)`). Página chamadora exige usuário logado. |
| `api/get_presenca_evento.php` | `ApiAuth::exigirLogin()` | A única barreira era o cabeçalho `X-Requested-With: XMLHttpRequest`, trivialmente forjável. Devolvia a lista de confirmados com **nomes e telefones** de associados. Página chamadora exige `NIVEL_USUARIO`. |
| `api/limpar_modal_eventos.php` | `ApiAuth::exigirLogin()` | Não devolvia dados, mas permitia a terceiros manipular a flag de sessão `mostrar_modal_eventos`; só é chamado de páginas autenticadas (`includes/header.php`). Aplicado "negar por padrão". |

---

## 4. Exceções públicas por design (confirmadas, sem login)

| Endpoint | Por que permanece público | Devolve dados pessoais? |
|---|---|---|
| `api/get_cidades.php` | Dados de referência (municípios/UF) usados por formulários de busca. | **Não** — apenas `id, nome, estado`. |
| `api/get_estado_cidade.php` | Dados de referência (UF de um município). | **Não** — apenas `id, nome, sigla` do estado. |
| `api/pre_cadastro.php` | Primeiro acesso / auto-cadastro (POST). Não devolve registro de terceiros; apenas `success/message` e erros de validação. | **Não** devolve dados de terceiros. Ver risco residual nº 4. |
| `api/recuperar_senha.php` | Fluxo "esqueci minha senha" (POST). Resposta sempre genérica ("Se este e-mail estiver cadastrado..."), independente de a conta existir. | **Não** — sem enumeração de e-mails e sem link exposto. |

**Observação sobre `api/insumos_aprovados.php`:**
o enunciado o listava como possível exceção pública, mas **ele já exige login** (`$user->isLoggedIn()` + `401`, linhas 22–27). Portanto **não é exceção pública** e **não foi alterado**: já estava fechado para anônimos. Devolve apenas o catálogo de insumos aprovados da Portaria MAPA 52/2021 (id, nome, fabricante, categoria) — sem dados pessoais. A página que o consome (`modulos/certificacao/insumos.php`) é restrita a Admin/Gestor, mas o endpoint aceita qualquer usuário logado; recomendo endurecer para `exigirStaff()` (ver pendência nº 5).

---

## 5. Endpoints inventariados que já possuíam guarda (não alterados)

Verificação exaustiva: para cada arquivo de `api/*.php` (1º nível) procurei por `ApiAuth::exigir`, `isLoggedIn`, `$_SESSION['user_id']` ou `podeAcessarDiretoria`.
Resultado: após as correções, **19 de 23** têm guarda (antes desta tarefa eram 16 de 23) — `get_eventos_simples.php`, `get_eventos_pendentes_new.php`, `nao_conformidades.php`, `plano_manejo.php`, `ocorrencias.php`, `toggle_presenca.php`, `cnpo_consulta.php`, `relatorios_admin.php`, `certificadoras.php`, `get_eventos_pendentes.php`, `insumos_aprovados.php`, `relatorios_controller.php`, `roteiros_inspecao.php`, `ficha_adesao.php`, `get_propriedades_associado.php`, `get_propriedade_detalhes.php` + os 3 corrigidos aqui. Os 4 restantes (`get_cidades.php`, `get_estado_cidade.php`, `pre_cadastro.php`, `recuperar_senha.php`) são exatamente as exceções públicas do item 4.

Em `api/relatorios/` já estavam protegidos: `lista-associados.php`, `status-associados.php`, `inadimplencia.php` (`exigirStaff(false)`), `relatorio_associados.php` (Admin/Gestor), `relatorio_atividades_usuario.php`/`_temp.php` (login), `admin_export.php` (`isLoggedIn + hasPermission(NIVEL_GESTOR)`), `rapido.php` (login), `gerar.php`/`bootstrap.php` (login). **Nenhum destes foi tocado.**

---

## 6. Defesa contra IDOR nos relatórios de nível 3

Os três relatórios liberados para usuário comum listavam dados de **todos** os produtores. Aplicado o mesmo critério de `classes/ReportManager.php`:

| Endpoint | Tabela | Filtro aplicado quando `ApiAuth::nivel() > NIVEL_GESTOR` |
|---|---|---|
| `estoque-atual.php` | `insumos` | `WHERE associado_id = :associado_id` |
| `atividades-diarias.php` | `atividades_diarias a` | `AND a.associado_id = :associado_id` |
| `produtividade.php` | `atividades_diarias a` | `AND a.associado_id = :associado_id` |

Admin/Gestor continuam vendo tudo (o filtro só entra para nível 3).

---

## 7. Evidências

### 7.1 `php -l` (todos os arquivos alterados)

Comando:

```powershell
php -l api/relatorios/visao-geral.php; php -l api/relatorios/movimentacoes.php; php -l api/relatorios/fluxo-caixa.php;
php -l api/relatorios/contas-pagar.php; php -l api/relatorios/contas-receber.php; php -l api/relatorios/previsao-receita.php;
php -l api/relatorios/fluxo-caixa-realizado-previsto.php; php -l api/relatorios/pagamentos-por-forma.php;
php -l api/relatorios/estoque-atual.php; php -l api/relatorios/atividades-diarias.php; php -l api/relatorios/produtividade.php;
php -l api/get_associacao_detalhes.php; php -l api/get_presenca_evento.php; php -l api/limpar_modal_eventos.php
```

Resultado: `No syntax errors detected` nos **14/14** arquivos.

### 7.2 Matriz HTTP pós-correção

Comando (script de teste criado em `tmp/`, permitido): `php tmp/api_auth_matrix.php after`
Fixtures: `teste.admin@sinporg.local` (Admin n1), `teste.gestor@sinporg.local` (Gestor n2), `teste.comum@sinporg.local` (Usuário n3 = associado 86). Senha `Teste@123`. Requisições enviadas com `X-Requested-With: XMLHttpRequest` (necessário para `get_presenca_evento.php`).

| # | Endpoint | Nível | anon | admin(1) | gestor(2) | comum(3) |
|---|---|---|---|---|---|---|
| 1 | `api/relatorios/estoque-atual.php` | 3 | **401** | 200 | 200 | 200 |
| 2 | `api/relatorios/atividades-diarias.php` | 3 | **401** | 200 | 200 | 200 |
| 3 | `api/relatorios/produtividade.php` | 3 | **401** | 200 | 200 | 200 |
| 4 | `api/relatorios/movimentacoes.php` | 2 | **401** | 200 | 200 | **403** |
| 5 | `api/relatorios/fluxo-caixa.php` | 2 | **401** | 200 | 200 | **403** |
| 6 | `api/relatorios/contas-pagar.php` | 2 | **401** | 200 | 200 | **403** |
| 7 | `api/relatorios/contas-receber.php` | 2 | **401** | 200 | 200 | **403** |
| 8 | `api/relatorios/previsao-receita.php` | 2 | **401** | 200 | 200 | **403** |
| 9 | `api/relatorios/fluxo-caixa-realizado-previsto.php` | 2 | **401** | 200 | 200 | **403** |
| 10 | `api/relatorios/pagamentos-por-forma.php` | 2 | **401** | 200 | 200 | **403** |
| 11 | `api/relatorios/visao-geral.php` | 1 | **401** | 200 | **403** | **403** |
| 12 | `api/get_associacao_detalhes.php?id=3` | login | **401** | 200 | 200 | 200 |
| 13 | `api/get_presenca_evento.php?evento_id=3` | login | **401** | 200 | 200 | 200 |
| 14 | `api/limpar_modal_eventos.php` | login | **401** | 200 | 200 | 200 |
| R1 | `api/relatorios/lista-associados.php` (já corrigido) | staff | 401 | 200 | 200 | 403 |
| R2 | `api/insumos_aprovados.php` (já exigia login) | login | 401 | 200 | 200 | 200 |
| R3 | `api/ficha_adesao.php?action=status` | sessão | 200 neutro¹ | 200 | 200 | 200 (dados próprios) |

¹ `{"success":true,"mostrar":false,"status":"aprovada"}` — resposta neutra para anônimo, sem nenhum dado pessoal.

Trecho literal da execução (anônimo — `php tmp/portal_http_test.php get - - <urls>`):

```
GET http://localhost/app/api/relatorios/estoque-atual.php -> HTTP 401 :: Não autenticado. Faça login para acessar este recurso.
GET http://localhost/app/api/relatorios/fluxo-caixa.php -> HTTP 401 :: Não autenticado. Faça login para acessar este recurso.
GET http://localhost/app/api/relatorios/visao-geral.php -> HTTP 401 :: Não autenticado. Faça login para acessar este recurso.
GET http://localhost/app/api/get_associacao_detalhes.php?id=3 -> HTTP 401 :: {"erro":"Não autenticado. Faça login para acessar este recurso."}
GET http://localhost/app/api/get_presenca_evento.php?evento_id=3 -> HTTP 401 :: {"erro":"Não autenticado. Faça login para acessar este recurso."}
GET http://localhost/app/api/limpar_modal_eventos.php -> HTTP 401 :: {"erro":"Não autenticado. Faça login para acessar este recurso."}
```

Trecho literal (admin — `php tmp/portal_http_test.php get teste.admin@sinporg.local Teste@123 <urls>`):

```
GET .../api/relatorios/fluxo-caixa.php -> HTTP 200 :: Entradas (Receitas) R$ 35,20 Saídas (Despesas) R$ 23.000,00 ...
GET .../api/relatorios/contas-pagar.php -> HTTP 200 :: Total de Despesas do Período ... R$ 23.000,00 ...
GET .../api/relatorios/previsao-receita.php -> HTTP 200 :: Previsão Total (3 meses) R$ 0,00 ...
GET .../api/get_associacao_detalhes.php?id=3 -> HTTP 200 :: {"success":true,"data":{"id":3,"nome":"Associação de Agricultores ...
GET .../api/limpar_modal_eventos.php -> HTTP 200 :: {"success":true}
```

Trecho literal (comum — nível 3):

```
GET .../api/relatorios/fluxo-caixa.php -> HTTP 403 :: Você não tem permissão para acessar este recurso.
GET .../api/relatorios/previsao-receita.php -> HTTP 403 :: Você não tem permissão para acessar este recurso.
GET .../api/relatorios/visao-geral.php -> HTTP 403 :: Você não tem permissão para acessar este recurso.
GET .../api/relatorios/estoque-atual.php -> HTTP 200
GET .../api/relatorios/atividades-diarias.php -> HTTP 200
GET .../api/relatorios/produtividade.php -> HTTP 200
```

**Nota sobre `get_presenca_evento.php` no harness:** o harness não envia `X-Requested-With`; por isso ele registra `403 {"success":false,"erro":"Acesso negado"}` para todos os perfis. O chamador real (`modulos/eventos/listar.php:278-283`) envia o cabeçalho, e nesse cenário (script `tmp/api_auth_matrix.php`) o resultado é `401` anônimo / `200` autenticado — que é o comportamento correto.

### 7.3 Linha de base (antes da correção)

Como `tmp/` é bloqueado no Apache, a linha de base foi reproduzida por **CLI com sessão vazia** (equivalente a anônimo), usando a versão do arquivo anterior à correção (`git show HEAD:<arquivo>`), executada por `php tmp/baseline_orig/_run.php <arquivo>`:

| Endpoint | Antes (anônimo) | Depois (anônimo) |
|---|---|---|
| `fluxo-caixa.php` | **HTTP-equivalente 200 — 2.847 bytes com dados financeiros reais** (R$ 35,20 / R$ 23.000,00 / saldo −R$ 22.964,80) | 401 |
| `contas-pagar.php` | 200 — 1.303 bytes (R$ 23.000,00) | 401 |
| `contas-receber.php` | 200 — 925 bytes | 401 |
| `previsao-receita.php` | 200 — 3.228 bytes | 401 |
| `fluxo-caixa-realizado-previsto.php` | 200 — 4.426 bytes | 401 |
| `pagamentos-por-forma.php` | 200 — 2.489 bytes | 401 |
| `get_associacao_detalhes.php?id=3` | 200 — 816 bytes JSON (CNPJ/endereço/nome) | 401 |
| `get_presenca_evento.php?evento_id=3` | 200 — 3.291 bytes JSON (confirmados, nomes, telefones) | 401 |
| `limpar_modal_eventos.php` | 200 — `{"success":true}` | 401 |
| `estoque-atual.php`, `atividades-diarias.php`, `produtividade.php`, `movimentacoes.php`, `visao-geral.php` | 200 — caixa de erro SQL **pré-existente** (ver pendência nº 1) | 401 |

Isso confirma que os 5 endpoints com erro SQL **já falhavam antes** desta correção (o erro está na lista de `SELECT`, que não foi alterada; a guarda de autorização é anterior à query).

### 7.4 Prova do vínculo do usuário comum (associado 86)

Comando: `php tmp/portal_http_test.php get teste.comum@sinporg.local Teste@123 ...`

```
/api/get_propriedades_associado.php?associado_id=86 -> HTTP 200 :: []
/api/get_propriedades_associado.php?associado_id=13 -> HTTP 403 :: {"erro":"Acesso negado: você só pode acessar os seus próprios dados."}
```

Como `api/get_propriedades_associado.php` (já corrigido) libera apenas `ApiAuth::associadoId()`, o `200` para o id 86 e o `403` para o id 13 provam que `ApiAuth::associadoId()` resolve **86** na sessão do usuário comum — exatamente o valor usado nos novos filtros de nível 3.

Contagens (somente SELECT — `php tmp/_auth_counts.php`):

```
insumos (estoque-atual): total=14 | associado 86 (comum)=0 | outros=14
atividades_diarias (atividades-diarias/produtividade): total=11 | associado 86 (comum)=0 | outros=11
```

Ou seja, o filtro isola completamente o conjunto de dados do produtor 86 do conjunto global.

---

## 8. Pendências e riscos residuais

### Dentro do escopo

1. **Erros SQL pré-existentes impedem validar as linhas dos relatórios de nível 3.** As três consultas de nível 3 (e outras duas) referenciam colunas que não existem no schema atual:
   - `estoque-atual.php` → `unidade_medida`, `estoque_minimo`, `status` não existem em `insumos`;
   - `atividades-diarias.php` e `produtividade.php` → `a.titulo` (a coluna real é `nome`);
   - `movimentacoes.php` → `lp.identificacao_lote`;
   - `visao-geral.php` → `status_lote`.
   A guarda funciona (401/403/200 conforme a matriz), mas o conteúdo é uma caixa de erro para todos os perfis. **Não corrigi** por ser bug funcional fora do mandato de autenticação; a prova do filtro IDOR foi feita por código + `ApiAuth::associadoId()` + contagens SQL (item 7.4).

### Fora do escopo (inventariado por varredura, **não alterado** — encaminhar)

2. `api/certificacao/gerar_pdf.php` — **sem nenhuma autenticação** e sem chamadores no código hoje. Um POST anônimo com `associado_id` válido faz `SELECT * FROM associados WHERE id = ?` (IDOR), gera certificado/declaração em PDF e **grava arquivos** em `uploads/certificados`/`uploads/declaracoes`. Provado: `POST anônimo com associado_id=999999 -> HTTP 200 {"success":false,"error":"Associado não encontrado."}` (ou seja, o anônimo alcança a consulta). Recomendação: `ApiAuth::exigirStaff()` (ou `exigirLogin() + exigirProprioAssociado($associado_id)`).
3. `api/regionalizacao/dados.php` — responde a anônimos com dados agregados do mapa de calor (contagem por microrregião). **Não há dados pessoais**, mas expõe estatísticas de produção. Decidir se deve exigir login.
4. `api/pix/webhook/{asaas,gerencianet,mercadopago}.php` — webhooks públicos **sem validação de assinatura/IP**. Severidade mitigada porque `Mensalidade::processarWebhookPix()` revalida o status junto ao provedor PIX antes de dar baixa (linhas 708-747), mas ainda permite replay/sondagem de `txid`. Recomendação: validar assinatura do provedor + allowlist de IP.
5. `api/insumos_aprovados.php` — já exige login, mas a página consumidora é restrita a Admin/Gestor; avaliar `exigirStaff()` para alinhar endpoint e interface.
6. `api/comunicacao/whatsapp_links.php` e `api/regionalizacao/microrregioes.php` — o `require_once` aponta para `../../../config/config.php` (caminho errado): ambos morrem com *fatal error* antes de qualquer checagem (**falham fechado**, sem vazamento). O primeiro devolveria nomes+telefones de associados a qualquer sessão (só CSRF, sem login) se o caminho fosse corrigido — corrigir o caminho **sem** adicionar `exigirLogin()`/`exigirStaff()` reabriria o vazamento.
7. `api/relatorios/bootstrap.php` define `ROOT_PATH = dirname(dirname(dirname(__DIR__)))` = `C:\xampp\htdocs` (e não `...\app`), onde não existe `config/config.php`. Consequência: `api/relatorios/gerar.php` (listado como "já tem guarda") responde **HTTP 500** para todos os perfis — a guarda `verificarAutenticacao()` nunca é alcançada. Falha fechado (não vaza), mas o endpoint está inoperante.
8. `api/get_eventos_pendentes_new.php` — quando não autenticado devolve, além do erro, `session_status`, `session_id` e o dump de `$_SESSION`. Sem dados de terceiros (sessão anônima vazia), porém é informação desnecessária.
9. **`api/pre_cadastro.php` (exceção pública):** aceita `perfil=gestor` e cria usuário de nível 2 (`INSERT INTO usuarios ... nivel = NIVEL_GESTOR ... status='pendente'`) e revela se um e-mail já está cadastrado ("Este e-mail já está cadastrado") — vetor de *user enumeration*. Mitigação atual: `login.php` só autentica `status='ativo'` (linhas 95 e 99), então a conta pendente não entra. Recomendação: resposta genérica para e-mail duplicado e validação/liberação do perfil "gestor".
10. `api/get_presenca_evento.php` — embora alinhado à página (qualquer usuário logado), devolve **nomes e telefones** de associados confirmados a um usuário de nível 3. Se a regra de negócio permitir, restringir a `exigirStaff()`.
11. `api/get_associacao_detalhes.php` — a página declara no comentário "apenas Admin/Gestor", mas o código só exige `isLoggedIn()`. Apliquei `exigirLogin()` para espelhar a implementação real; se a intenção for a do comentário, trocar por `exigirStaff()`.
12. `api/relatorios/fluxo-caixa-realizado-previsto.php`, `previsao-receita.php` e `pagamentos-por-forma.php` **não estão versionados no git** (`git ls-files` não os reconhece), portanto não foi possível obter a versão anterior pelo `HEAD`; a linha de base deles foi reconstruída removendo o bloco de guarda inserido.

---

## 9. Arquivos de apoio criados em `tmp/` (fora do código de produção)

| Arquivo | Função |
|---|---|
| `tmp/api_auth_matrix.php` | Matriz HTTP (anon/admin/gestor/comum) dos 14 endpoints + 3 de regressão. |
| `tmp/_auth_counts.php` | Contagens SQL (somente SELECT) que comprovam o filtro por associado. |
| `tmp/_probe_extras.php` | Sondagem anônima dos endpoints fora do escopo (pêndências 2, 3, 6, 7). |
| `tmp/baseline_orig/` | Cópias pré-correção usadas como linha de base (executadas por CLI). |

