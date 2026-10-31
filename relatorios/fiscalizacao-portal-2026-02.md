# Relatório de Fiscalização Independente — Portal do Associado e Guardas de Acesso

**Projeto:** SINPORG / `C:\xampp\htdocs\app` — PHP 8.2.12 (XAMPP), MySQL `sisagro`, `http://localhost/app`  
**Data/hora:** 2026-09-14, janela de execução 10:12–10:31 (horários dos registros em `audit_log`)  
**Papel:** Agente Fiscal (verificador independente / QA de segurança) — tentativa ativa de refutação  
**Método:** leitura do código (somente leitura), requisições HTTP reais com cookie jar, `SELECT` no MySQL, scripts auxiliares criados exclusivamente em `tmp/`. Nenhum arquivo de produção foi alterado.

> **Nota de contexto:** a implementação foi alterada pela equipe durante esta fiscalização (atualização de escopo recebida em ~10:20). Por isso o relatório separa:
> - **FASE 1** — resultados sobre o estado anterior (histórico, já superado);
> - **FASE 2** — revalidação completa + novos controles, executada sobre o **estado atual** (é a base do veredito).

**Ferramentas usadas**

- `tmp/portal_http_test.php` (harness fornecido) — cenários `portal`, `magictoken`, `ficha`, `anon`.
- `tmp/fiscal_probe.php` (meu) — HTTP genérico com cookie jar; mede ocorrências exatas da mensagem, campos `readonly`, links de token, conteúdo da área e identidade (CPF/e-mail esperado).
- `tmp/fiscal_api.php`, `tmp/fiscal_ficha.php`, `tmp/fiscal_email.php`, `tmp/fiscal_race.php`, `tmp/fiscal_anon_token.php`, `tmp/fiscal_unit.php` (meus) e consultas SQL.

---

## 1. FASE 1 — estado anterior (histórico, 10:12–10:19)

Comandos do harness executados (`php tmp/portal_http_test.php ...`):

```text
portal teste.comum@sinporg.local Teste@123 login_cpf 90000000268 Teste@123   -> msg segurança SIM; link mágico NÃO; /portal/area 302
portal teste.comum@sinporg.local Teste@123 solicitar_magic_link 90000000268    -> msg segurança SIM; link mágico NÃO; /portal/area 302
portal teste.comum@sinporg.local Teste@123 consultar_cpf 90000000268          -> msg segurança SIM; /portal/area 302
magictoken teste.comum@sinporg.local Teste@123 teste.vitima@sinporg.local     -> bloqueado; sessão de portal não criada
portal teste.diretoria@sinporg.local Teste@123 consultar_cpf 90000000268      -> 302 /portal/area; área 200 com dados da vítima
portal teste.admin@sinporg.local Teste@123 consultar_cpf 90000000268          -> 302 /portal/area; área 200 com dados da vítima
ficha teste.comum@sinporg.local Teste@123 21 8 19                             -> 21 CONTEÚDO; 8 e 19 ACESSO NEGADO
ficha teste.diretoria@sinporg.local Teste@123 21 8 19                         -> 21, 8 e 19 CONTEÚDO
```

Resultado da FASE 1: **todas as regras 1–5 atendidas**. Esta fase foi integralmente revalidada na FASE 2.

---

## 2. FASE 2 — estado atual (base do veredito)

### 2.1 Regras 1, 2, 4 e 5 — Portal (usuário comum vs. próprio CPF)

Sonda: `php tmp/fiscal_probe.php <label> <email> <senha> <acao> <campos...>` (login em `/login`, GET `/portal/login`, POST e GET `/portal/area`).

| # | Cenário | Comando (resumo) | Esperado | Obtido | Resultado |
|---|---------|------------------|----------|--------|-----------|
| 1 | Comum (86) → `login_cpf` CPF da vítima (87) | `... p1 teste.comum@sinporg.local Teste@123 login_cpf cpf=90000000268 senha=Teste@123` | Bloquear com a mensagem exata; sem sessão | HTTP 200; mensagem exata ×1; link mágico 0; `/portal/area` 302 | **PASSOU** |
| 2 | Comum → `solicitar_magic_link` CPF da vítima | `... p2 ... solicitar_magic_link cpf=90000000268` | Bloquear; não gerar/exibir link | HTTP 200; mensagem exata ×1; link 0; área 302 | **PASSOU** |
| 3 | Comum → `consultar_cpf` CPF da vítima | `... p3 ... consultar_cpf cpf_consulta=90000000268` | Bloquear | HTTP 200; mensagem exata ×1; área 302 | **PASSOU** |
| 4 | Comum → `verificar_token` token da vítima | `... p11 ... verificar_token token=<token vítima>` | Bloquear | HTTP 200; mensagem exata ×1; área 302 | **PASSOU** |
| 5 | Comum → próprio CPF (`login_cpf`) | `... p4 ... cpf=90000000187 senha=Teste@123` | Permitir; área com o próprio CPF | HTTP 302 → área 200; `area_contem_cpf_esperado=true` (90000000187) | **PASSOU** |
| 6 | Comum → próprio CPF (`solicitar_magic_link`) | `... p5 ... cpf=90000000187` | Permitir e exibir o **próprio** link | HTTP 200; link do titular exibido (1) | **PASSOU** |
| 7 | Readonly das abas “CPF + Senha” e “Link Mágico” | GET `/portal/login` logado | `value` = CPF do usuário + `readonly` | 2 inputs `name="cpf"` do portal com `value="900.000.001-87"` e `readonly aria-readonly="true" data-cpf-vinculado="1"` | **PASSOU** |
| 8 | Aba “Consultar por CPF” oculta para comum | GET `/portal/login` | `id="tab-consulta"` ausente | ausente (`tab_consulta=false`) | **PASSOU** |
| 9 | CPF legado de 10 dígitos (89, `9000000041`) | `... p7 teste.legado@... login_cpf cpf=9000000041 senha=Teste@123` | Permitir o próprio; `readonly` = `090.000.000-41` | área 200; readonly `090.000.000-41` | **PASSOU** |
| 10 | Legado (89) → CPF da vítima | `... p6 ... login_cpf cpf=90000000268` | Bloquear | mensagem exata ×1; área 302 | **PASSOU** |
| 11 | Usuário sem CPF vinculado (115, nível 3) → CPF da vítima | `... p8 teste.usuario@... login_cpf cpf=90000000268` | Bloquear; sem `readonly` e sem valor | mensagem exata ×1; área 302; campos não readonly | **PASSOU** |
| 12 | Usuário sem CPF 115 → `verificar_token` da vítima | `... usuário115_token_vitima ...` | Bloquear | mensagem exata ×1; área 302 | **PASSOU** |
| 13 | Gestor (116, nível 2, sem vínculo) → `consultar_cpf` vítima | `... gestor116_consulta_vitima ...` | Bloquear (só Admin/Diretoria Executiva são privilegiados) | mensagem exata ×1; área 302 | **PASSOU** |

Mensagem exigida (comparação byte a byte, acentos inclusos): `Você só tem permissão para acessar os seus próprios dados exclusivos.` — presente **exatamente 1 vez** em cada resposta bloqueada, no `alert alert-danger`.

### 2.2 Regra 3 — Admin e Diretoria Executiva

| # | Cenário | Esperado | Obtido | Resultado |
|---|---------|----------|--------|-----------|
| 14 | Diretoria (88, vínculo `diretor`) → `consultar_cpf` 90000000268 | Acesso ao portal da vítima | HTTP 302 → área 200; `area_contem_cpf_esperado=true` e e-mail da vítima presente; abas administrativas visíveis | **PASSOU** |
| 15 | Admin (114, nível 1) → `consultar_cpf` 90000000268 | Idem | HTTP 302 → área 200 com CPF/e-mail da vítima | **PASSOU** |
| 16 | Diretoria → CPF legado 10 dígitos (89) | Consulta tolerante | HTTP 302 → área 200 | **PASSOU** |
| 17 | Diretoria → CPF inexistente | Mensagem “não encontrado”, sem sessão | HTTP 200 “Associado não encontrado ou inativo…”; área 302 | **PASSOU** |
| 18 | Diretoria → `solicitar_magic_link` de terceiro | Não exibir o link a terceiros | HTTP 200, mensagem genérica, 0 links; área 302 | **PASSOU** |

### 2.3 Reuso/consumo atômico do token (novo)

| # | Cenário | Esperado | Obtido | Resultado |
|---|---------|----------|--------|-----------|
| 19 | 1º uso do próprio token (comum) | Logar | HTTP 302 → área 200 | **PASSOU** |
| 20 | Reuso do mesmo token (sequencial) | Falhar | HTTP 200 “Link inválido ou expirado”; área 302 | **PASSOU** |
| 21 | Reuso concorrente (`tmp/fiscal_race.php`, 2 sessões, `curl_multi`) | No máximo 1 sucesso | `sucessos=1` (1× HTTP 302, 1× “Link inválido ou expirado”); `used=1` no banco | **PASSOU** |
| 22 | Token de conta da tabela `usuarios` (não-associado) | Falhar sem vazar | “Link inválido ou expirado”; área 302 | **PASSOU** |
| 23 | Token inexistente/fabricado | Falhar | “Link inválido ou expirado”; área 302 | **PASSOU** |
| 24 | Anônimo com token **válido** da vítima (fluxo legítimo do link mágico) | Logar (posse do token = autenticação) | HTTP 302 → área 200 com dados da vítima; `used=1` | **PASSOU (por desenho)** |

### 2.4 Ficha de Adesão — `modulos/associados/ficha_adesao_visualizar.php`

| # | Usuário | Ficha 21 (do 86) | Ficha 8 (terceiro) | Ficha 19 (terceiro) | Resultado |
|---|---------|------------------|--------------------|---------------------|-----------|
| 25 | Comum 86 | CONTEÚDO | ACESSO NEGADO | ACESSO NEGADO | **PASSOU** |
| 26 | Vítima 87 | ACESSO NEGADO | ACESSO NEGADO | ACESSO NEGADO | **PASSOU** |
| 27 | Legado 89 | ACESSO NEGADO | ACESSO NEGADO | ACESSO NEGADO | **PASSOU** |
| 28 | Usuário 115/gestor 116 | ACESSO NEGADO | ACESSO NEGADO | ACESSO NEGADO | **PASSOU** |
| 29 | Diretoria 88 / Admin 114 | CONTEÚDO | CONTEÚDO | CONTEÚDO | **PASSOU** |

IDs codificados (`encodeId`, chave real de `config/secrets.php`) também foram testados com `tmp/fiscal_ficha.php`: `enc:8` e `enc:19` → **Acesso negado**; `enc:21` (própria) → conteúdo. Nenhum vazamento dos dados do associado 13 nas páginas negadas (`techostin@gmail.com`, CPF `47677929087`, nome “Edmilson” ausentes).

POST de aprovação/recusa (`acao=aprovar|recusar`): com usuário comum sem CSRF, a resposta é **“Acesso negado.”** (guarda de privilégio antes do CSRF); com a Diretoria, a mesma requisição avança para a página da ficha. Estado das fichas 21/8/19 verificado **inalterado** antes/depois.

### 2.5 Novas guardas de endpoints e páginas (ampliação de escopo)

Comando: `php tmp/fiscal_api.php <email|-> <senha|-> <urls...>`

| Recurso | Anônimo | Comum 86 | Usuário 115 (n3, sem vínculo) | Gestor 116 (n2) | Admin 114 (n1) | Resultado |
|---------|---------|----------|-------------------------------|-----------------|----------------|-----------|
| `/api/relatorios/lista-associados.php` | 401 | 403 | 403 | 200 | 200 | **PASSOU** |
| `/api/relatorios/status-associados.php` | 401 | 403 | 403 | 200 | 200 | **PASSOU** |
| `/api/relatorios/inadimplencia.php` | 401 | 403 | 403 | 200 | 200 | **PASSOU** |
| `/api/get_propriedades_associado.php?associado_id=86` (próprio do comum) | 401 | **200** | 403 | 200 | 200 | **PASSOU** |
| `/api/get_propriedades_associado.php?associado_id=13` (terceiro) | 401 | 403 | 403 | 200 | 200 | **PASSOU** |
| `/api/get_propriedade_detalhes.php?id=8` (dono 13) | 401 | 403 | 403 | 200 | 200 | **PASSOU** |
| `/associado/13/editar` | 302 `/login` | “Acesso negado: você só pode visualizar o seu próprio cadastro.” | idem negado | 200 | 200 | **PASSOU** |
| `/associado/86/editar` (próprio) | 302 `/login` | 200 (sem negativa) | negado | 200 | 200 | **PASSOU** |
| `/certificacao/historico/13` | 302 `/login` | “Acesso negado: você só pode acessar o seu próprio histórico…” | idem negado | 200 | 200 | **PASSOU** |
| `/certificacao/historico/86` (próprio) | 302 `/login` | 200 (sem negativa) | negado | 200 | 200 | **PASSOU** |

### 2.6 Troca de e-mail no portal (bloqueio de escalonamento) — novo

Comando: `php tmp/fiscal_email.php teste.comum@sinporg.local Teste@123 <novoEmail>`

| # | Novo e-mail tentado | Esperado | Obtido | Resultado |
|---|---------------------|----------|--------|-----------|
| 30 | `teste.vitima@sinporg.local` (outro associado) | Recusar | `warning: Dados atualizados. O e-mail informado já está em uso por outra conta e por isso NÃO foi alterado.`; e-mail permaneceu `teste.comum@sinporg.local` | **PASSOU** |
| 31 | `teste.admin@sinporg.local` (tabela `usuarios`) | Recusar | aviso idêntico; e-mail inalterado | **PASSOU** |
| 32 | `TESTE.VITIMA@SINPORG.LOCAL` (caixa alta) | Recusar | aviso idêntico; e-mail inalterado | **PASSOU** |
| 33 | O próprio e-mail | Permitir | `success: Perfil atualizado com sucesso!` | **PASSOU** |

### 2.7 Vínculo real da sessão principal — novo

- `tmp/fiscal_unit.php` → `PortalAccess::usuarioVinculadoAoAssociado(86/87/88/89) = null` (nenhum vínculo real).
- Caso crítico de colisão por e-mail: o associado **13** (`techostin@gmail.com`) coincide com o usuário `usuarios.id=7` (**nível 1, Admin**), mas **não há vínculo real** (`associados.user_id` de 13 = 1, que não existe em `usuarios`; `usuarios.associado_id` de 7 é 1, não 13). `usuarioVinculadoAoAssociado(13)` retorna **null** → a sessão principal **não** é promovida a Admin. Vínculos reais existentes (28→11, 29→12, 31→14) são resolvidos corretamente.
- Auditoria: logins de portal do usuário 86 registram `user_id` nulo (sem vínculo real), comprovando que não houve promoção indevida.

### 2.8 Bloqueio de ferramentas de desenvolvimento (`.htaccess` + `.htaccess` por diretório)

| Recurso | HTTP | Resultado |
|---------|------|-----------|
| `tmp/portal_http_test.php`, `tmp/fiscal_encode.php`, `tmp/` | 403 | **PASSOU** |
| `tmp/bridge_token.php` (removido) | 403 | **PASSOU** |
| `tmp/*.sql` (ex.: `tmp/fase2_prep.sql`) | 403 | **PASSOU** |
| `logs/api_debug.log`, `logs/cron_externos.log` | 403 | **PASSOU** |
| `dev-utils/check_db_structure.php`, `dev-utils/migrate_certificadora_logos.php`, `dev-utils/` | 403 | **PASSOU** |
| `producao_bkp/PRODUCAO.md`, `producao_bkp/sincronizar_externos.sh`, `producao_bkp/` | 403 | **PASSOU** |
| `check_cpf.php`, `check_schema.php`, `run_sql.php`, `schema.php`, `test_api.php`, `debug_seed.php`, `composer.json`, `composer.lock` | 403 | **PASSOU** |
| Controle: `login.php`, `index.php`, `img/logo_Sinporg-2.png` | 200 | **PASSOU** |

---

## 3. Tentativas de bypass executadas (e resultado)

| # | Tentativa | Resultado | Evidência |
|---|-----------|-----------|-----------|
| B1 | POST forjado `login_cpf` com CPF da vítima e senha da vítima como usuário comum | Bloqueado | mensagem exata; `/portal/area` 302 |
| B2 | POST forjado `consultar_cpf` (aba administrativa) como comum | Bloqueado | mensagem exata; 302 |
| B3 | POST forjado `solicitar_magic_link` de terceiro como comum | Bloqueado | mensagem exata; 0 links |
| B4 | Token de link mágico de terceiro (`verificar_token`) como comum/legado/usuário sem CPF | Bloqueado | mensagem exata ×1; 302 |
| B5 | Token de terceiro por **anônimo** | Página responde mensagem genérica e **não** exibe link; nenhum dado exposto | `anon_magic_vitima`: 0 links |
| B6 | Manipular o `readonly` via POST (enviar CPF de terceiro no campo travado) | Bloqueado | B1/B3 |
| B7 | CPF de terceiro com pontuação (`900.000.002-68`) | Bloqueado | mensagem exata |
| B8 | CPF com 13 dígitos (`9000000026800`) | Bloqueado | mensagem exata |
| B9 | CPF em dígitos full-width (`９０００００００２６８`) | Bloqueado | mensagem exata |
| B10 | CPF com espaços/`+` (`+90000000268+`) | Bloqueado | mensagem exata |
| B11 | Campos duplicados (`cpf=próprio&cpf=vítima` e vice-versa) | Bloqueado no primeiro caso; permitido apenas o próprio no segundo | mensagem exata / login próprio |
| B12 | Injeção de array `cpf[]=90000000268` (urlencoded e multipart) | Negado; **sem HTTP 500** | urlencoded → “Informe CPF e senha.”; multipart → mensagem de segurança |
| B13 | Injeção de array `token[]=...` | Negado; sem 500 | “Token inválido.” / bloqueio |
| B14 | `cpf=Array` (string) | Bloqueado | mensagem exata |
| B15 | `acao` com caixa diferente (`LOGIN_CPF`) | Nenhuma ação executada; sem sessão | `/portal/area` 302 |
| B16 | Terceiro com token expirado/reutilizado/inexistente | Negado | “Link inválido ou expirado”; 302 |
| B17 | Reuso concorrente do mesmo token (duas sessões simultâneas) | Apenas 1 sucesso | `tmp/fiscal_race.php`: `sucessos=1`; `used=1` |
| B18 | Acesso direto a `/portal/area` sem sessão de portal (com/sem login principal) | 302 para `/portal/login`, 0 bytes, sem “Bem-vindo” | 302 |
| B19 | Ficha de terceiro por ID numérico e por ID codificado (`encodeId`) | Acesso negado | 21/8/19 e `enc:8`/`enc:19` |
| B20 | POST de aprovar/recusar ficha de terceiro como comum | Acesso negado antes do CSRF; dados inalterados | “Acesso negado.” |
| B21 | Trocar o e-mail do próprio portal para o de outra conta (associado ou usuário da equipe), inclusive em caixa alta | Recusado; e-mail inalterado | aviso “já está em uso por outra conta” |
| B22 | Logar no portal (sem login principal) esperando ganhar sessão principal por coincidência de e-mail | Não promove sessão principal | `usuarioVinculadoAoAssociado(13)=null`; auditoria com `user_id` nulo |
| B23 | Endpoints/arquivos de desenvolvimento expostos | 403 | tabela 2.8 |

Nenhuma das tentativas resultou em acesso indevido, criação de sessão de portal ou HTTP 500.

---

## 4. Verificação de vazamento do link mágico

- **Link de terceiro em tentativa bloqueada:** **NÃO aparece**. Em todas as respostas bloqueadas, a contagem de `href` com `verificar_token` e de blocos `<code>` com `token=` foi **0**; varredura dos corpos salvos (`tmp/fiscal_body_*.html`) confirma que o único arquivo com `token=` é o do **próprio** usuário (`comum_magic_proprio`).
- **Link do próprio usuário:** aparece quando o solicitante é o titular autenticado (regra de apoio em testes) — esperado.
- **Anônimo solicitando CPF de terceiro:** resposta genérica `Se o CPF estiver cadastrado e ativo, o link será enviado para o e-mail registrado.` e **0** links exibidos.
- **Diretoria/Admin solicitando link de terceiro:** resposta genérica e **0** links exibidos (o link só iria por e-mail ao titular).
- **Nenhuma tentativa bloqueada criou `$_SESSION['associado_id']`**: todas terminaram com `GET /portal/area → 302 /portal/login`.

---

## 5. Auditoria (`audit_log`)

Contagem de eventos `PORTAL*` no estado atual:

| Ação | Qtd | Primeiro | Último |
|------|-----|----------|--------|
| `PORTAL_ACESSO_NEGADO` | 49 | 2026-09-14 10:12:35 | 2026-09-14 10:22:54 |
| `PORTAL_ACESSO_PRIVILEGIADO` | 16 | 2026-09-14 10:12:44 | 2026-09-14 10:21:45 |
| `PORTAL_LOGIN` | 25 | 2026-09-14 10:12:38 | 2026-09-14 10:23:50 |
| `PORTAL_LOGIN_MAGIC_LINK` | 5 | 2026-09-14 10:17:10 | 2026-09-14 10:24:04 |
| `PORTAL_MAGIC_LINK` | 8 | 2026-09-14 10:12:42 | 2026-09-14 10:23:39 |

Exemplos de registros (todos com CPF mascarado — LGPD):

```text
PORTAL_ACESSO_NEGADO      acao=login_cpf        cpf_alvo=900.***.***-68 associado_alvo=0   user_id=86  ip=::1
PORTAL_ACESSO_NEGADO      acao=consultar_cpf    cpf_alvo=900.***.***-68 associado_alvo=0   user_id=86  ip=::1
PORTAL_ACESSO_NEGADO      acao=verificar_token  cpf_alvo=—              associado_alvo=87  user_id=86  ip=::1
PORTAL_ACESSO_PRIVILEGIADO acesso_privilegiado  associado=87 user_id=88  ip=::1 via=consulta_cpf
PORTAL_LOGIN              via=login_cpf         registro_id=86
PORTAL_LOGIN_MAGIC_LINK   via=magic_link        registro_id=86
PORTAL_MAGIC_LINK         solicitado_por=86 email_enviado=1  registro_id=86
```

**Confirmação:** as quatro ações exigidas estão registradas — `PORTAL_ACESSO_NEGADO`, `PORTAL_ACESSO_PRIVILEGIADO`, `PORTAL_LOGIN` (CPF+senha) e `PORTAL_MAGIC_LINK` (solicitação); adicionalmente `PORTAL_LOGIN_MAGIC_LINK` (confirmação do token). Observação: parte dos registros tem origem em testes paralelos da própria equipe no mesmo banco/instante (ex.: `solicitado_por=—`), o que não altera a conclusão.

---

## 6. Veredito

### APROVADO COM RESSALVAS

A correção resistiu a todas as tentativas de refutação: regras 1–5 do Portal, guardas novas de API/páginas, bloqueio de troca de e-mail, vínculo real de sessão, consumo atômico do token e quarentena de arquivos de desenvolvimento. Não foi possível obter acesso a dados de terceiros, criar sessão de portal a partir de tentativa bloqueada, nem expor link mágico de terceiros.

### Ressalvas (nenhuma bloqueante de segurança)

1. **Mensagem para token de terceiro não-associado.** Quando o token não resolve para um associado ativo (ex.: conta da tabela `usuarios`, token expirado/reutilizado), a resposta é a genérica `Link inválido ou expirado. Solicite um novo.` e não a mensagem exata da regra 2. Não há exposição de dados nem sessão criada; a mensagem exata aparece sempre que existe um CPF/associado de terceiro identificável (casos 1–4, 10, 12). Considerado aceitável, mas registrado.
2. **Solicitação anônima de link mágico para terceiro** gera token e envia e-mail (`PORTAL_MAGIC_LINK`), sem exibir o link — comportamento equivalente a “esqueci minha senha”. Mitigado por limite de 10 tentativas/15 min por sessão. Não viola as regras avaliadas.
3. **Colisão teórica de CPF legado (10 dígitos).** `normalizarCpf` completa zeros à esquerda; não existem colisões entre os associados **ativos** atuais (apenas CPFs vazios, que não são localizáveis por CPF). Recomenda-se monitorar duplicidade caso surjam novos cadastros legados.
4. **Comparação frouxa em `modulos/associados/editar.php:23`** (`$_SESSION['user_nivel'] != NIVEL_ADMIN`) define apenas o modo de visualização; a autorização real usa `User::isGestorOuAdmin()` (endurecido). Sem impacto de segurança, mas vale padronizar.
5. **Observação funcional (fora do escopo de autorização):** na página `/portal/area` do associado 86 apareceu o alerta `Erro ao carregar a ficha. Tente novamente.` durante os testes de atualização de perfil. Não há relação com controle de acesso; recomenda-se investigar como bug funcional.
6. **Ambiente compartilhado:** durante a fiscalização a implementação foi alterada e testes paralelos da equipe gravaram em `audit_log`. Todas as conclusões deste relatório foram revalidadas no estado atual (FASE 2).

### Evidências-chave (reprodução rápida)

```text
# bloqueio de terceiro (mensagem exata + sem sessão)
php tmp/fiscal_probe.php p1 teste.comum@sinporg.local Teste@123 login_cpf cpf=90000000268 senha=Teste@123

# privilegiado consulta terceiro (área com dados da vítima)
php tmp/fiscal_probe.php p9 teste.diretoria@sinporg.local Teste@123 consultar_cpf cpf_consulta=90000000268

# token: próprio funciona, reuso falha, concorrência → 1 sucesso
php tmp/fiscal_race.php

# endpoints e páginas por nível
php tmp/fiscal_api.php teste.comum@sinporg.local Teste@123 /api/relatorios/lista-associados.php /api/get_propriedade_detalhes.php?id=8 /associado/13/editar /certificacao/historico/13

# troca de e-mail
php tmp/fiscal_email.php teste.comum@sinporg.local Teste@123 teste.vitima@sinporg.local
```
