# Fiscalização independente — Fluxo de Acesso / Ficha de Adesão (AAES/RS)

- **Data da verificação:** 14/09/2026 (execução ~11:25–11:35 UTC / 16:25–16:35 no horário do servidor)
- **Agente:** AGENTE FISCAL (verificador independente) — subagente `session-c9bf82f5-62a4-49a1-9bcc-633841bd593c`
- **Ambiente:** PHP 8.2 + MySQL (XAMPP), `C:\xampp\htdocs\app`, `http://localhost/app`, base `sisagro`
- **Método:** somente leitura de código-fonte + testes por HTTP (curl com cookie jar por ator) e SQL (SELECT; DELETE apenas dos dados de teste). Nenhum arquivo de código-fonte foi alterado.
- **Scripts de teste (novos, em `tmp/`):** `tmp/fiscal_v2_lib.php`, `tmp/fiscal_v2_all.php`, `tmp/fiscal_v2_ressalvas.php`, `tmp/fiscal_v2_estado.php`, `tmp/fiscal_v2_orfaos.php`, `tmp/fiscal_v2_baseline.php`, `tmp/fiscal_v2_probe_idor.php`
- **Hackers de teste:** e-mails `teste.*@sinporg.local` e CPFs fictícios (111.222.333-96, 222.333.444-05, 333.444.555-12, 444.555.666-21). Nenhuma conta real foi usada ou alterada.

## 0. Revisão verificada (hashes MD5)

A árvore de trabalho estava sendo **editada durante a verificação**. Os resultados abaixo referem-se à revisão estável cujos hashes foram idênticos antes e depois da bateria final:

| Arquivo | MD5 |
|---|---|
| api/pre_cadastro.php | 6DF423C11BA86C139BC9743B2F3B4944 |
| api/ficha_adesao.php | 05D46D698CF0EF06D458803549DEFEEA |
| classes/FichaAdesao.php | 313D16BC344A926D350C22135A1F0BAB → **8813DF428742D0BDA95232B417884616** (2ª rodada) |
| classes/User.php | AD4A103A6997AC11CE946F13D4394000 |
| classes/PortalAccess.php | EDBBEB484A0FF375B0FCA2C2E2A90745 |
| classes/CSRFProtection.php | 86D7AAE4ED969143956D4469D43BB11E |
| includes/ficha_adesao_modal.php | 42C52EBAAD08095E368D720FBEE400BF |
| includes/header.php | EFC2B572BC0254D925711D929043C5FA |
| modulos/associados/pendentes.php | 7C7C60D467018FBEB32E4713769AEFEE → **038FBFEB568E51495079E8F962308E76** (2ª rodada) |
| modulos/associados/cadastros_pendentes.php | 4A291EB5F6E799912C0B39E7CD2F8984 |
| modulos/associados/ficha_adesao_visualizar.php | 787B55CCD9BB8B8334E2F8F9A98638EE |
| login.php | 19EDBAFBBD883F114D333C941654303C |
| index.php | 7E5414E575A785855AFF7923745FB878 |

> **Atenção:** `modulos/associados/ficha_adesao_visualizar.php` foi alterado às 11:27:01, no meio da verificação (ver Observação O1). O hash acima é o da versão efetivamente testada.

## 1. Matriz de testes executados

Legenda: **P** = PASSOU, **F** = FALHOU. Comandos executados via `php tmp/fiscal_v2_all.php` (bateria completa, 67 verificações) — cada linha corresponde a uma requisição HTTP/SQL concreta.

| # | Cenário | Comando / Requisição | Esperado | Obtido | Res. |
|---|---|---|---|---|---|
| 1.1 | Pedido de acesso agricultor | `POST /api/pre_cadastro.php` (nome, sobrenome, email, senha+confirma, perfil=vegetal) | `{"success":true}` e conta gravada **só** em `usuarios` | HTTP 200 `success=true`; `usuarios.id=155, nivel=3, tipo=usuario, tipo_usuario=agricultor, area_producao=vegetal, tem_agroindustria=0, status=pendente` | P |
| 1.2 | Sem linha em associados | `SELECT COUNT(*) FROM associados WHERE email=?` | 0 | 0 | P |
| 1.3 | Perfil gestor | `POST /api/pre_cadastro.php` (perfil=gestor) | nivel 2 em `usuarios`, sem associado | `id=156, nivel=2, tipo=gestor, tipo_usuario=gestor, status=pendente`; associados=0 | P |
| 1.4 | Perfil agroindústria | `POST /api/pre_cadastro.php` (perfil=agroindustria) | `produtor_agroindustria`, `tem_agroindustria=1` | `id=157, tipo_usuario=produtor_agroindustria, tem_agroindustria=1, area_producao=agroindustria` | P |
| 1.5 | E-mail duplicado | `POST /api/pre_cadastro.php` (mesmo e-mail) | recusa | `"Este e-mail já está cadastrado. Tente fazer login."` | P |
| 2.1 | Login de conta pendente | `POST /login` (senha correta, status pendente) | não autenticar | HTTP 200 com `"E-mail ou senha inválidos!"` | P |
| 2.2 | Sessão não criada | `GET /api/ficha_adesao.php?action=status` | `mostrar=false` (sem sessão) | `{"success":true,"mostrar":false}` | P |
| 2.3 | Área restrita | `GET /caderno-campo/dashboard-agricultor` | 302 → /login | HTTP 302 `Location: /app/login` | P |
| 3.1 | Listagem de pendentes | `GET /associados/pendentes` (admin) | listar conta com `origem=usuario` | HTTP 200, e-mail de teste presente, `<input name="origem" value="usuario">` | P |
| 3.2 | Autorização do pedido | `POST /associados/pendentes` `acao=ativar&id=155&origem=usuario` | `usuarios.status='ativo'` | HTTP 200, `"Usuário ativado com sucesso!"`, status=`ativo` | P |
| 3.3 | Ativação não cria associado | `SELECT COUNT(*) FROM associados WHERE email=?` | 0 | 0 | P |
| 4.1 | Login após ativação | `POST /login` | 302 dashboard | HTTP 302 → `/caderno-campo/dashboard-agricultor` | P |
| 4.2 | Status da ficha | `GET /api/ficha_adesao.php?action=status` | `mostrar=true`, status `sem_ficha` | `{"mostrar":true,"status":"sem_ficha","ficha":null}` | P |
| 4.3 | Prefill da CONTA | idem | nome/e-mail da conta | `{"nome":"FISCAL TESTE A","email":"teste.fiscal.a@sinporg.local","telefone":"","cpf":""}` | P |
| 4.4 | Modal para nível 3 | `GET /caderno-campo/dashboard-agricultor` | `id="modalFichaAdesao"` renderizado | presente + `FICHA DE CADASTRO DE ASSOCIADO` | P |
| 4.5 | Admin (nível 1) sem modal | `GET /` / `GET /cadastros-pendentes` | sem modal | HTTP 200, `modalFichaAdesao` ausente | P |
| 4.6 | Gestor (nível 2) sem modal | login 2 etapas + `GET /meu-perfil` | sem modal | HTTP 200, ausente | P |
| 5.1 | CSRF da sessão | token de `window.CSRF_TOKEN` | token de 64 hex | len=64 | P |
| 5.2 | Submeter ficha | `POST /api/ficha_adesao.php` `action=submeter` + `X-CSRF-Token` | `success` + `ficha_id` | HTTP 200, `ficha_id=41` | P |
| 5.3 | Vínculo da ficha | `SELECT usuario_id,associado_id,status FROM ficha_adesao WHERE id=41` | `usuario_id=155`, `associado_id=NULL`, `pendente` | exatamente isso + `data_submissao` | P |
| 5.4 | Produtos/aceite | `SELECT COUNT(*) FROM ficha_adesao_produtos WHERE ficha_id=41` | 1 linha, aceite=1 | 1; `aceite_termos=1` | P |
| 6.1 | Aprovação | `POST /api/ficha_adesao.php` `action=aprovar&ficha_id=41` + CSRF (admin) | `success` | HTTP 200, `"Ficha aprovada! Nº de cadastro gerado: AAES-00041"` | P |
| 6.2 | Ficha aprovada | `SELECT ... FROM ficha_adesao WHERE id=41` | `aprovada`, `numero_cadastro`, `data_entrada`, `associado_id` | `aprovada`, `AAES-00041`, `2026-09-14`, `associado_id=115` | P |
| 6.3 | Associado criado | `SELECT * FROM associados WHERE id=115` | criado da ficha, CPF em dígitos | `cpf=11122233396`, `status=ativo`, `user_id=155`, `tipo_associado=Efetivo` | P |
| 6.4 | Vínculo lado 1 | `SELECT associado_id FROM usuarios WHERE id=155` | = 115 | 115 | P |
| 6.5 | Vínculo lado 2 | `SELECT user_id FROM associados WHERE id=115` | = 155 | 155 | P |
| 6.6 | Status pós-aprovação | `GET ...?action=status` | `mostrar=false`, `aprovada` | `{"mostrar":false,"status":"aprovada"}` | P |
| 7.1 | 2º pedido com mesmo CPF | `POST .../pre_cadastro` + ativação + `action=submeter` (CPF 111.222.333-96) | ficha pendente criada | `ficha_id=42`, `usuario_id=159` | P |
| 7.2 | Aprovação do 2º pedido | `POST .../ficha_adesao.php action=aprovar&ficha_id=42` | recusa com mensagem específica | HTTP 422 `"Este CPF já possui cadastro de associado vinculado a outra conta de acesso. Verifique se não é um pedido duplicado."` | P |
| 7.3 | Sem duplicidade | `SELECT COUNT(*) ... cpf=11122233396` | 1 | 1 (id 115) | P |
| 7.4 | Rollback do 2º pedido | `SELECT status,associado_id FROM ficha_adesao WHERE id=42` | `pendente`, NULL | `pendente`, NULL | P |
| 7.5 | Conta do 2º pedido | `SELECT associado_id FROM usuarios WHERE id=159` | NULL | NULL | P |
| 8.1 | Legado — status | login `teste.legado@` + `GET ...?action=status` | `sem_ficha` + prefill do cadastro | `sem_ficha`, `cpf=9000000041`, nome/e-mail do associado | P |
| 8.2 | Legado — submissão | `POST ...action=submeter` | ficha com `associado_id`, sem `usuario_id` | `ficha_id=43, usuario_id=NULL, associado_id=112` | P |
| 8.3 | Legado — aprovação | `POST ...action=aprovar&ficha_id=43` | `success`, **sem** criar associado | `success`, `AAES-00043`; total de associados 35→35 | P |
| 8.4 | Legado — efeito | `SELECT ficha_aprovada,user_id FROM associados WHERE id=112` | `ficha_aprovada=1`, `user_id=NULL` | exatamente isso | P |
| 9.1 | IDOR ficha pendente — terceiro | conta D (nível 3, sem vínculo) `GET /cadastro-ficha/visualizar?id=41&modal=1` | `Acesso negado` | HTTP 200 `Acesso negado.`, nenhum dado vazado | P |
| 9.2 | IDOR ficha pendente — anônimo | `GET /cadastro-ficha/visualizar?id=41&modal=1` (sem sessão) | `Acesso negado` | HTTP 200 `Acesso negado.` | P |
| 9.3 | Dono vê a própria ficha | conta E autora `GET ...?id=45&modal=1` | conteúdo exibido | conteúdo completo exibido | P |
| 9.4 | Terceiro não vê ficha do dono | conta D `GET ...?id=45&modal=1` | negado | negado | P |
| 9.5 | Ficha aprovada de terceiro | conta D `GET ...?id=41&modal=1` (já aprovada) | negado | negado | P |
| 9.6 | `/cadastros-pendentes` LEFT JOIN | `GET /cadastros-pendentes` (admin) | ficha sem associado listada, nome vindo de `usuarios` | HTTP 200, `value="41"` e `"FISCAL TESTE A"` presentes | P |
| 9.7 | Aprovar exige Diretoria | conta nível 3 `POST ...action=aprovar` | 403 | HTTP 403 `"Acesso negado. Apenas a Diretoria/Presidência pode avaliar fichas."` | P |
| 9.8 | Gestor/Fiscal não aprova | gestor nível 2 `POST ...action=aprovar` + CSRF | 403 | HTTP 403, mesma mensagem | P |
| 9.9 | Aprovar exige CSRF | `POST ...action=aprovar` sem token (admin) | 403 | HTTP 403 `"Erro de segurança: token CSRF inválido."` | P |
| 9.10 | Submeter exige CSRF | `POST ...action=submeter` sem token | 403 | HTTP 403 `"Erro de segurança: token CSRF inválido."` | P |
| 9.11 | CSRF preso à sessão | token da sessão D usado na sessão admin | 403 | HTTP 403 | P |
| 9.12 | Sem ID de terceiro no POST | `action=submeter` com `usuario_id=155` e `associado_id=115` | ficha da própria conta | `usuario_id=158` (D), `associado_id=NULL` | P |
| 9.13 | Reenvio após aprovação | `action=submeter` na ficha já aprovada | bloqueio | HTTP 422 `"Esta ficha já foi aprovada e não pode ser alterada."` | P |
| 9.14 | Aprovar ficha já aprovada | `action=aprovar&ficha_id=41` novamente | bloqueio | `"Esta ficha já foi aprovada."` | P |
| 9.15 | Páginas restritas | conta nível 3 `GET /associados/pendentes`; gestor `GET /cadastros-pendentes` | `Acesso negado` | HTTP 200 com `Acesso negado` (sem listar dados) | P |
| 9.16 | Ativação só por Admin | conta nível 3 e gestor `POST /associados/pendentes acao=ativar` | não ativar | `usuarios.status` permaneceu `pendente` | P |
| 9.17 | `/cadastros-pendentes` POST sem CSRF | `POST /cadastros-pendentes acao=aprovar` sem token | não aprovar | ficha permaneceu `pendente`, flash `"token CSRF inválido"` | P |
| 9.18 | `/cadastro-ficha/visualizar` POST sem CSRF | `POST ...acao=aprovar` sem token | não aprovar | ficha permaneceu `pendente` | P |

**Resultado da bateria automatizada:** `== RESUMO: 67/67 verificações PASSOU ==`

## 2. Tentativas de burla e resultado

| Burla | Como foi executada | Resultado |
|---|---|---|
| Aprovar a própria ficha sem ser Diretoria | Conta nível 3 (autora da ficha) `POST /api/ficha_adesao.php action=aprovar` com o próprio token CSRF | **Bloqueado** — HTTP 403 `Acesso negado. Apenas a Diretoria/Presidência...`; ficha continuou pendente |
| Gestor/Fiscal aprovando ficha | Login gestor em 2 etapas (nível 2) + `action=aprovar` | **Bloqueado** — HTTP 403 (Gestor não é Diretoria) |
| Anônimo aprovando ficha | `POST action=aprovar` sem sessão | **Bloqueado** — HTTP 403 (CSRF ausente; e sem sessão não haveria autorização) |
| Submeter ficha "para outro usuário" | `POST action=submeter` com `usuario_id=155` e `associado_id=115` de terceiros no corpo | **Bloqueado** — campos ignorados; ficha criada com `usuario_id=158` (a própria conta D) e `associado_id=NULL` |
| Reenviar ficha após aprovada | `POST action=submeter` na ficha 41 (aprovada) | **Bloqueado** — HTTP 422 `Esta ficha já foi aprovada e não pode ser alterada.` |
| Aprovar ficha já aprovada | `POST action=aprovar&ficha_id=41` duas vezes | **Bloqueado** — 2ª tentativa: `Esta ficha já foi aprovada.` |
| CPF duplicado (2º pedido de acesso) | Conta C com CPF idêntico ao da conta A já aprovada; admin tenta aprovar | **Bloqueado** — HTTP 422 com a mensagem exata pedida pelo cliente; **nenhum** 2º associado criado; conta C não vinculada; ficha 42 intacta |
| Conta sem autorização (nível 3) | `GET /associados/pendentes`, `POST acao=ativar`, `GET /cadastros-pendentes` | **Bloqueado** — `Acesso negado`; status do usuário-alvo não mudou |
| Gestor (nível 2) sem autorização | idem acima | **Bloqueado** — `Acesso negado` |
| POST sem CSRF (API da ficha) | `action=submeter` e `action=aprovar` sem token | **Bloqueado** — HTTP 403 `Erro de segurança: token CSRF inválido.` |
| CSRF de outra sessão (token roubado) | Token da sessão D enviado em requisição da sessão admin | **Bloqueado** — HTTP 403 (token é validado contra a sessão) |
| Ficha acessada por outra conta / anônimo (IDOR) | conta D (nível 3 sem vínculo) e visitante anônimo acessam `/cadastro-ficha/visualizar?id=<ficha pendente>&modal=1` | **Bloqueado na revisão testada** — `Acesso negado.` nas duas tentativas; o dono vê a própria ficha normalmente |
| **Ativação sem CSRF (RESSALVA R2)** | `POST /associados/pendentes acao=ativar&id=154&origem=usuario` **sem nenhum token** | **NÃO bloqueado** — HTTP 200 e `usuarios.status` passou de `pendente` para `ativo` |
| **CPF de associado legado já efetivado (RESSALVA R1)** | Legado aprova a própria ficha; depois um **novo pedido de acesso** com o mesmo CPF tenta aprovar | **Parcialmente bloqueado** — não cria 2º associado e não vincula a conta, mas devolve **erro SQL cru** em vez da mensagem de duplicidade |

## 3. Consultas SQL de comprovação

### 3.1 Vínculo bidirecional (conta ↔ associado ↔ ficha)

```sql
SELECT u.id AS usuario_id, u.email AS conta_email, u.associado_id AS conta_aponta_para,
       a.id AS associado_id, a.cpf, a.user_id AS associado_aponta_para,
       f.id AS ficha_id, f.usuario_id AS ficha_usuario, f.associado_id AS ficha_associado,
       f.status AS ficha_status, f.numero_cadastro, f.data_entrada
FROM usuarios u
JOIN associados a ON a.id = u.associado_id
LEFT JOIN ficha_adesao f ON f.usuario_id = u.id
WHERE u.email = 'teste.fiscal.a@sinporg.local';
```

Resultado:

```json
{"usuario_id":155,"conta_email":"teste.fiscal.a@sinporg.local","conta_aponta_para":115,
 "associado_id":115,"cpf":"11122233396","associado_aponta_para":155,
 "ficha_id":41,"ficha_usuario":155,"ficha_associado":115,"ficha_status":"aprovada",
 "numero_cadastro":"AAES-00041","data_entrada":"2026-09-14"}
```

→ `usuarios.associado_id = 115 = associados.id` **e** `associados.user_id = 155 = usuarios.id` (vínculo nos dois lados confirmado).

### 3.2 Ausência de duplicidade de CPF após a 2ª tentativa

```sql
SELECT REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') AS cpf_digitos,
       COUNT(*) AS qtd, GROUP_CONCAT(id) AS ids
FROM associados
WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = '11122233396'
GROUP BY cpf_digitos;
```

Resultado: `{"cpf_digitos":"11122233396","qtd":1,"ids":"115"}` → **um único** associado para o CPF, mesmo após a 2ª tentativa de aprovação.

Estado da 2ª ficha (deve continuar pendente e sem associado):

```sql
SELECT id,usuario_id,associado_id,status FROM ficha_adesao WHERE id = 42;
-- {"id":42,"usuario_id":159,"associado_id":null,"status":"pendente"}

SELECT associado_id FROM usuarios WHERE id = 159;   -- NULL (não vinculada)
```

### 3.3 Fluxo legado preservado

```sql
SELECT f.id AS ficha_id, f.associado_id, f.usuario_id, f.status, f.numero_cadastro,
       a.id AS assoc_id, a.user_id AS assoc_user_id
FROM ficha_adesao f JOIN associados a ON a.id = f.associado_id
WHERE f.id = 43;
```

Resultado:

```json
{"ficha_id":43,"associado_id":112,"usuario_id":null,"status":"aprovada",
 "numero_cadastro":"AAES-00043","assoc_id":112,"assoc_user_id":null}
```

→ ficha legada com `associado_id` e **sem** `usuario_id`; a aprovação **não** criou novo associado (total 35 antes = 35 depois).

### 3.4 Ressalva R1 — erro SQL bruto devolvido ao cliente

```sql
-- associado legado 112 possui a ficha 39 aprovada; novo pedido (ficha 40) tem o mesmo CPF
SELECT COUNT(*) FROM associados WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = '9000000041';  -- 1
SELECT associado_id FROM usuarios WHERE id = <conta do novo pedido>;                                        -- NULL
SELECT status FROM ficha_adesao WHERE id = 40;                                                              -- 'pendente'
```

Resposta HTTP capturada:

```
HTTP 422 | success=false
MENSAGEM DEVOLVIDA AO CLIENTE: SQLSTATE[23000]: Integrity constraint violation: 1062
Duplicate entry '112' for key 'uq_ficha_associado'
```

## 4. Ressalvas

### R1 — Duplicidade por CPF de associado já efetivado: mensagem errada + vazamento de erro interno (MÉDIA) — **RESOLVIDA na 2ª rodada (§7.2)**
- **Cenário:** já existe um associado **efetivado** (com ficha aprovada, `ficha_adesao.associado_id` ocupado) **sem conta de sistema**; chega um pedido de acesso cujo CPF é o mesmo.
- **Causa:** em `FichaAdesao::aprovar()`, o guard anti-duplicidade só verifica `usuarioIdDoAssociado()` (vínculo com **outra conta**). Ele não detecta o caso "CPF já pertence a associado efetivado sem conta". O fluxo segue, `criarAssociadoDaFicha()` reaproveita o associado existente e o `UPDATE ficha_adesao SET associado_id=<id já usado por outra ficha>` estoura `uq_ficha_associado`. Como `PDOException` estende `RuntimeException`, o `catch (Throwable $e)` em `aprovar()` repassa a mensagem crua do banco ao cliente.
- **Impacto:** (a) o usuário/cliente recebe um erro técnico em vez de "Este CPF já possui cadastro de associado..."; (b) **divulgação de detalhes internos** (nome da constraint, SQLSTATE) — regra `A6`/boas práticas de não expor erro de banco; (c) o tratamento depende de constraint para impedir duplicidade, e não da regra de negócio.
- **Segurança de dados:** **não** há duplicidade de associado, **não** há vínculo indevido e a ficha permanece `pendente` (o rollback funciona). É um defeito de regra/mensagem, não de integridade.
- **Sugestão:** em `aprovar()`, recusar com a mensagem de negócio quando o associado encontrado já tiver ficha aprovada (`SELECT 1 FROM ficha_adesao WHERE associado_id=? AND status='aprovada'`) ou quando não estiver vinculado a esta conta; e restringir o `catch` para devolver mensagem genérica em `PDOException`.

### R2 — POST de ativação em `/associados/pendentes` não valida CSRF (MÉDIA) — **RESOLVIDA na 2ª rodada (§7.1)**
- **Evidência:** `POST /associados/pendentes` com `acao=ativar&id=154&origem=usuario` **sem qualquer token** → HTTP 200 e `usuarios.status` mudou de `pendente` para `ativo`.
- **Causa:** `modulos/associados/pendentes.php` processa `$_POST` (linhas 16–38) sem `CSRFProtection::validatePostRequest()`; a página só checa `hasPermission(NIVEL_ADMIN)` (autorização, essa correta — níveis 2 e 3 são barrados).
- **Impacto:** um admin autenticado pode ser induzido, por uma página de terceiros, a **ativar ou inativar** contas de acesso (a ação `inativar` também é aceita). O item 9 do fluxo exige CSRF nos POSTs.
- **Sugestão:** validar `CSRFProtection::validatePostRequest()` antes de processar, como já é feito em `cadastros_pendentes.php` e `ficha_adesao_visualizar.php`.

### O1 — Observação sobre a janela de verificação (informativa)
`modulos/associados/ficha_adesao_visualizar.php` foi alterado durante a fiscalização (mtime 14/09/2026 11:27:01). Na revisão lida por volta das 11:26, a checagem de dono comparava apenas `$associadoSessao !== (int)$ficha['associado_id']`; como fichas de pedido de acesso têm `associado_id = NULL` (`(int) = 0`) e um visitante sem vínculo também resolve para `0`, **qualquer sessão sem associado (inclusive anônima) poderia ler a ficha pendente de terceiros** por `/cadastro-ficha/visualizar?id=<n>` (IDOR de CPF/dados bancários). Essa leitura foi feita por **revisão de código** e não chegou a ser explorada por HTTP antes da correção.
Na versão efetivamente testada (hash `787B55CC...`), o guard foi reforçado com `$donoDaFicha` (dono por `usuario_id` **ou** `associado_id`) e os testes 9.1/9.2/9.3/9.4/9.5 **passaram** (terceiro e anônimo recebem `Acesso negado`; o dono vê a própria ficha). **Recomenda-se reconfirmar com o hash congelado** antes do deploy, já que o arquivo mudou durante a verificação.

### O2 — Higiene do banco (informativa)
Após a limpeza, permanecem 95 registros órfãos em `notificacoes` (usuario_id sem usuário correspondente) — nenhum deles referente às fichas de teste (`fichas órfãs = 0`); são resíduos de execuções anteriores e de rotinas que não removem notificações. Os scripts `cleanup_fluxo.php`/`cleanup_final.php` não cobrem `notificacoes`; não foram removidos por não serem inequivocamente dados de teste desta sessão.

## 5. Veredito

> ## APROVADO COM RESSALVAS *(1ª rodada — antes das correções; ver §7 para o veredito final)*

**Fundamento:** o fluxo desejado pelo cliente (itens 1 a 8) e os controles de segurança do item 9 foram implementados e **comprovados por execução real** — 67/67 verificações da bateria principal, incluindo vínculo bidirecional, ausência de duplicidade de CPF, preservação do fluxo legado, resolução do dono pela sessão, autorização Admin/Diretoria, CSRF nos POSTs da API e listagem de fichas sem associado. As tentativas de burla listadas no item 2 foram todas bloqueadas, **exceto** os dois pontos abaixo, que motivam as ressalvas:

1. **R1 (MÉDIA)** — no caso de CPF já pertencente a associado **efetivado** (ficha aprovada) sem conta, a aprovação é abortada por constraint e devolve **erro SQL interno** ao cliente em vez da mensagem de duplicidade; integridade preservada.
2. **R2 (MÉDIA)** — `POST /associados/pendentes` (ativar/inativar conta) **não valida CSRF**, apesar de exigir Admin.

Nenhuma das ressalvas permite criar associado duplicado, roubar vínculo, aprovar ficha sem ser Diretoria/Admin ou ler ficha de terceiros na revisão testada.

## 6. Limpeza e estado final (confirmado)

Comandos: `php tmp/cleanup_fluxo.php` e `php tmp/cleanup_final.php` (conforme solicitado).

| Verificação final | Resultado |
|---|---|
| `usuarios` com `teste.*@sinporg.local` | **0** |
| `associados` com `teste.*@sinporg.local` | **0** |
| `ficha_adesao.usuario_id IS NOT NULL` | **0** |
| Fichas órfãs (usuario_id/associado_id inexistente) | **0 / 0** |
| Totais restaurados | `associados=30`, `usuarios=10`, `ficha_adesao=5` (idênticos ao estado inicial) |
| Vínculos `usuarios.associado_id` restantes | apenas os 4 pré-existentes (contas reais, intocadas) |
| Dados reais alterados | **nenhum** |

**Arquivos criados:** apenas `relatorios/fiscalizacao-fluxo-acesso-2026-02.md` (este relatório) e scripts de teste em `tmp/`. Nenhum arquivo de código-fonte foi modificado.

---

## 7. Revalidação pós-correção (2ª rodada — 14/09/2026)

**Escopo:** revalidar **apenas** as ressalvas R1 e R2 no estado atual, mantendo o restante da bateria como regressão.
**Revisão revalidada (estável durante toda a rodada):** `classes/FichaAdesao.php` = `8813DF428742D0BDA95232B417884616`; `modulos/associados/pendentes.php` = `038FBFEB568E51495079E8F962308E76`; demais arquivos inalterados em relação à 1ª rodada.
**Nota de harness:** `pendentes.php` usa `CSRFProtection::validatePostRequest()`, que lê o token do **campo** `csrf_token` do formulário (não do header `X-CSRF-Token`). Os testes da 2ª rodada enviam o token no corpo do POST.

### 7.1 (a) R2 — CSRF em `POST /associados/pendentes` → **RESOLVIDA (PASSOU)**

| Cenário | Esperado | Obtido | Res. |
|---|---|---|---|
| Conta pendente criada via `pre_cadastro` (id 187) | `pendente` | `pendente` | P |
| Token da sessão disponível (window global e campo do form) | token de 64 hex | len=64; `name="csrf_token"` presente | P |
| `acao=ativar` **SEM token** | não mudar + erro de CSRF | HTTP 200, status `pendente`, `"Erro de segurança: token CSRF inválido"` | P |
| `acao=ativar` com token de **OUTRA sessão** (2ª sessão do mesmo admin) | não mudar | HTTP 200, status `pendente` | P |
| `acao=ativar` com token da **própria sessão** (campo) | ativar | HTTP 200, status `ativo`, `"Usuário ativado com sucesso!"` | P |
| `acao=inativar` **SEM token** | não mudar | HTTP 200, status `ativo` | P |
| `acao=inativar` com token da própria sessão | inativar | HTTP 200, status `inativo` | P |
| Formulários da página trazem o campo `csrf_token` | presente | presente (2 formulários) | P |

### 7.2 (b) R1 — CPF de associado com ficha já registrada → **RESOLVIDA (PASSOU)**

Pré-condições e execução:
1. associado legado (**sem conta**) submete e tem a própria ficha aprovada → passa a ter ficha registrada (`uq_ficha_associado` ocupado);
2. **novo pedido de acesso** com o **mesmo CPF** é criado, ativado e submete a ficha;
3. admin tenta aprovar.

| Verificação | Esperado | Obtido | Res. |
|---|---|---|---|
| Aprovação recusada com mensagem de negócio | `success=false` + `"já possui cadastro de associado"` | HTTP 422 — `"Este CPF já possui cadastro de associado com ficha de adesão registrada. Verifique se não é um pedido duplicado."` | P |
| Sem vazamento de erro interno | sem `SQLSTATE`/`Duplicate entry` | ausentes | P |
| Sem segundo associado | 1 cadastro com o CPF | `COUNT=1` | P |
| Conta nova não vinculada / ficha intacta | `associado_id=NULL`, `pendente` | exatamente isso (rollback íntegro) | P |
| (b2) Dois **pedidos de acesso** com o mesmo CPF (1º aprovado, 2º tentado) | recusa sem erro SQL, sem 2º associado, ficha pendente, conta não vinculada | HTTP 422 mensagem de negócio; `COUNT=1`; `link=NULL`; `pendente` | P |

### 7.3 (c) Regressão → **PASSOU**

- **Bateria completa** (`php tmp/fiscal_v2_all.php`, atualizada para o novo comportamento de CSRF na ativação): **69/69 verificações PASSOU**, cobrindo itens 1–9 (pre_cadastro, pendente sem login, ativação, status/modal, submissão, aprovação + vínculo bidirecional, CPF duplicado, fluxo legado, IDOR/CSRF/autorização/LEFT JOIN).
- **Revalidação focada** (`php tmp/fiscal_v2_revalida2.php`): **34/34 PASSOU**, incluindo: ativação com token no campo; fluxo normal conta→ficha→aprovação→`usuarios.associado_id = associados.id` **e** `associados.user_id = usuarios.id` (ex.: usuário 191 ↔ associado 130); status pós-aprovação `aprovada`/`mostrar=false`; ficha legada com `associado_id` e `usuario_id=NULL`; reenvio/aprovação repetida bloqueados; nível 3 sem aprovar (403); API sem CSRF (403); dono vê a própria ficha e terceiro/anônimo recebem `Acesso negado`.

### 7.4 Observação O3 (baixa severidade)

No cenário literal do **item 7** (dois **pedidos de acesso** com o mesmo CPF), a mensagem antes exigida — `"Este CPF já possui cadastro de associado vinculado a outra conta de acesso"` — **deixou de ser emitida**: a nova checagem de `criarAssociadoDaFicha()` (CPF existente **com ficha registrada**) ocorre **antes** do teste de vínculo com outra conta e devolve `"…com ficha de adesão registrada…"`. O comportamento de segurança é preservado (recusa, sem 2º associado, sem vínculo, ficha pendente). A mensagem antiga continua alcançável apenas quando o associado existente **não** tem ficha e já está vinculado a outra conta. **Ação sugerida:** se o texto do cliente for contratual, alinhar a ordem das checagens ou unificar as mensagens.

### 7.5 Limpeza da 2ª rodada (confirmada)

`php tmp/cleanup_fluxo.php` + `php tmp/cleanup_final.php`: `usuarios` teste.* = **0**, `associados` teste.* = **0**, `ficha_adesao.usuario_id` preenchidos = **0**, fichas órfãs = **0**, totais restaurados (`associados=30`, `usuarios=10`, `ficha_adesao=5`). Nenhum dado real alterado; nenhum código-fonte modificado.

### 7.6 Veredito final (2ª rodada)

> ## APROVADO COM RESSALVAS BAIXAS

As duas ressalvas da 1ª rodada (**R1** e **R2**) foram **corrigidas e confirmadas por HTTP** no estado atual. Permanecem apenas: **O3** (texto da mensagem do item 7 substituído — decisão de produto, baixa severidade), **O1** (cautela de deploy: o `ficha_adesao_visualizar.php` foi alterado durante a 1ª rodada; congelar o hash antes do deploy) e **O2** (higiene: notificações órfãs). A integridade dos dados e os controles de acesso permanecem corretos.

