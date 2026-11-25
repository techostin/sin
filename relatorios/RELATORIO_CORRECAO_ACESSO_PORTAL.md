# Relatório de Correção — Controle de Acesso do Portal do Associado

**Data:** 2026-02 (sessão de correção)
**Escopo primário:** impedir que usuário/associado comum acesse dados de outro associado em `/portal/login`
**Escopo secundário:** exposições críticas encontradas durante a auditoria de segurança paralela
**Ambiente de teste:** XAMPP local — Apache 2.4.58 / PHP 8.2.12 / MySQL, `http://localhost/app`, banco `sisagro`

---

## 1. Resumo executivo

O problema relatado (**qualquer usuário logado conseguia acessar dados de outro associado informando o CPF dele no Portal**) foi
confirmado, reproduzido por HTTP e corrigido no backend e no frontend.

Durante a correção foram encontradas **duas causas reais** e **uma barreira já existente porém incompleta**:

| # | Causa | Situação antes | Situação depois |
|---|-------|----------------|-----------------|
| 1 | `verificar_token` (confirmação do Link Mágico) não comparava o titular do token com o usuário logado | **IDOR confirmado ao vivo**: usuário comum com um token de terceiro obtinha sessão de portal completa da vítima | Bloqueado com a mensagem de segurança |
| 2 | Comparação de CPF cru no banco (10 dígitos em 1 registro real) contra entrada só-dígitos, com `!==` | **Falso positivo**: o próprio associado era bloqueado (associado id 20, CPF `3689160604`) | Comparação normalizada (11 dígitos, tolera pontuação e zero à esquerda) — acesso próprio funciona |
| 3 | Guarda existente cobria apenas `consultar_cpf`, `login_cpf` e `solicitar_magic_link`, e usava apenas `associados.cpf` | Cobertura parcial | Guarda central única (`classes/PortalAccess.php`) para **todos** os fluxos, inclusive token |

Além disso, a auditoria de segurança paralela (subagente) encontrou e **foram corrigidas** exposições críticas fora do portal:

| Severidade | Achado | Correção aplicada |
|---|---|---|
| Crítico | `tmp/bridge_token.php` **emitia um JWT de ADMIN sem autenticação** (confirmado ao vivo, HTTP 200) | Arquivo removido + `tmp/`, `dev-utils/`, `logs/`, `producao_bkp/` agora respondem **403** |
| Crítico | `api/relatorios/lista-associados.php` e `status-associados.php` devolviam **CPF, RG, e-mail e telefone de todos os associados sem login** (confirmado ao vivo) | Guarda `ApiAuth::exigirStaff()` → **401** para anônimo |
| Crítico | `api/relatorios/inadimplencia.php` sem autenticação | `ApiAuth::exigirStaff()` |
| Alto | `api/get_propriedades_associado.php` sem autenticação (confirmado ao vivo) | Login + dono/Admin/Gestor |
| Alto | `api/get_propriedade_detalhes.php`: checagem anti-IDOR só rodava se `user_nivel == 3` → anônimo passava | Login + dono/Admin/Gestor (sempre) |
| Alto | IDOR de leitura em `/associado/{id}/editar` (nome, e-mail, CPF, telefone, endereço de qualquer associado para qualquer usuário logado) | Nível 3 só acessa o próprio cadastro; Admin/Gestor mantêm acesso |
| Alto | IDOR em `/certificacao/historico/{associado_id}` | Nível 3 só acessa o próprio histórico |
| Alto | Escalonamento de privilégio: a sessão principal herdava `user_id/nivel` de uma conta `usuarios` **apenas por e-mail coincidente**, e o portal permitia trocar o próprio e-mail | Vínculo só com relação real (`usuarios.associado_id`/`associados.user_id`) + troca de e-mail para endereço já usado por outra conta é recusada |
| Médio | Consumo de token não atômico (TOCTOU) e sem regeneração de sessão | `UPDATE ... WHERE used = 0` com verificação + `session_regenerate_id(true)` nos 3 fluxos de login |
| Médio | Entrada não-string (`cpf[]`, `token[]`) derrubava a requisição com **HTTP 500** (TypeError) | Sanitização de entrada em `login.php`, `modulos/portal/login.php` e `classes/PasswordReset.php` |
| Médio | `hasPermission()` comparava nível de sessão sem tipo (`'' <= 1` = **true** em PHP 8 → Admin) | Validação numérica estrita |
| Médio | Scripts de desenvolvimento acessíveis pela web (`check_cpf.php`, `run_sql.php` — executa DDL, `test_*.php`, `schema.php`, `composer.json`, `logs/*.log`, `tmp/*.sql`) | Bloqueio 403 via `.htaccess` (padrão + extensões sensíveis) |

---

## 2. Regra de negócio implementada

> "Quando um usuário que já está logado tenta usar o login do portal (CPF+Senha ou Link Mágico), o sistema verifica se ele é
> Admin ou da Diretoria Executiva. Se for usuário/associado comum, compara o CPF informado com o seu próprio CPF. Se for
> diferente, bloqueia com: *Você só tem permissão para acessar os seus próprios dados exclusivos.*"

Implementação em uma **única fonte de verdade** — `classes/PortalAccess.php`:

- `ehPrivilegiado()`: Admin (`hasPermission(NIVEL_ADMIN)`) **ou** cargo executivo em `associado_associacao`
  (`presidente`, `vice-presidente`, `diretor`, `secretario`, `tesoureiro`) — via `User::isDiretoriaExecutiva()`.
- `cpfDoUsuario()`: resolve o CPF do usuário logado por sessão de portal, `associados` ou `usuarios` (cobre `usuarios.associado_id` e `associados.user_id`).
- `normalizarCpf()`: 11 dígitos, tolera pontuação e zeros à esquerda ausentes.
- `podeAcessarCpf()` / `podeAcessarAssociadoId()`: Admin/Diretoria → sempre; titular → sim; qualquer outro CPF → **não**.
- `associadoIdDoToken()`: descobre o titular de um token de Link Mágico válido.
- `buscarAssociadoAtivoPorCpf()`: busca tolerante (compatível com os CPFs legados de 10 dígitos).
- `registrarAcessoNegado()` / `registrarAcessoPrivilegiado()`: auditoria na tabela `audit_log` (LGPD: CPF mascarado).
- `tentativasRestantes()` / `registrarTentativa()`: limite de 10 tentativas por sessão/15 min (força bruta e spam de link).

### Backend — `modulos/portal/login.php`
1. Guarda aplicada a **todas** as ações: `consultar_cpf`, `login_cpf`, `solicitar_magic_link` **e `verificar_token`**.
2. Bloqueio **antes** de qualquer efeito colateral (não cria sessão, não consome token, não gera link).
3. O link mágico deixou de ser impresso para terceiros: agora é **enviado por e-mail** (`Mailer`/`Resend`);
   o texto do link só é exibido na tela para **o próprio titular autenticado** (apoio em ambiente sem e-mail).
4. `session_regenerate_id(true)` nos três fluxos de autenticação (anti fixação de sessão).
5. Consulta privilegiada registrada em `audit_log` (`PORTAL_ACESSO_PRIVILEGIADO`), antes só `error_log`.
6. Entradas normalizadas: `cpf[]`/`token[]` (array) não geram mais HTTP 500.
7. Vínculo com a conta de sistema só quando há relação real (não mais por e-mail coincidente).

### Frontend — abas "CPF + Senha" e "Link Mágico"
- Campo CPF vem **preenchido** com o CPF do usuário logado e com `readonly` (`aria-readonly`, `data-cpf-vinculado`).
- Texto auxiliar "CPF vinculado ao seu usuário — campo somente leitura" + ícone de cadeado.
- Usuário logado sem CPF vinculado recebe um aviso claro (e o backend bloqueia).
- A máscara JS ignora campos `readonly`.
- A aba "Consultar por CPF" permanece visível **apenas** para Admin/Diretoria Executiva.

---

## 3. Evidências (HTTP real, `http://localhost/app`)

Harness: `tmp/portal_http_test.php` (cookie jar real, sem `follow` para observar redirecionamentos).
Fixtures: `teste.comum` (nível 3), `teste.vitima` (nível 3), `teste.diretoria` (`tipo_vinculo='diretor'`), `teste.legado` (CPF de 10 dígitos), `teste.admin` (nível 1), `teste.gestor` (nível 2).

### 3.1 Antes da correção (baseline reproduzido)

| Cenário | Resultado observado |
|---|---|
| Comum → `consultar_cpf` (vítima) | bloqueado pela guarda parcial |
| Comum → `login_cpf` (vítima, senha correta da vítima) | bloqueado |
| Comum → `solicitar_magic_link` (vítima) | bloqueado |
| **Comum → `verificar_token` com token da vítima** | **IDOR CONFIRMADO** — `/portal/area` HTTP 200 exibindo os dados da vítima |
| **Legado (CPF 10 dígitos) → `login_cpf` com o próprio CPF** | **bloqueado indevidamente** (falso positivo) |
| Anônimo → `GET /portal/login` | 302 para `/app/login` (link mágico não vaza para anônimo — refuta o achado C-01 da auditoria) |

### 3.2 Depois da correção

| Cenário testado | Resultado |
|---|---|
| Comum → `login_cpf` com **CPF da vítima** (mesmo com a senha correta) | bloqueado: `Você só tem permissão para acessar os seus próprios dados exclusivos.` — sem sessão de portal |
| Comum → `solicitar_magic_link` com CPF da vítima | bloqueado com a mesma mensagem; **nenhum link exibido** |
| Comum → `consultar_cpf` (ação administrativa) | bloqueado com a mesma mensagem |
| Comum → `verificar_token` com **token da vítima** | bloqueado; `/portal/area` → 302 `/portal/login` (**IDOR eliminado**) |
| Comum → `login_cpf` com o **próprio CPF** | 302 → `/portal/area` HTTP 200 (funciona) |
| Comum → `solicitar_magic_link` com o próprio CPF | link exibido **apenas para o titular** e sessão criada |
| Reuso do mesmo token (2ª vez) | falha: `Link inválido ou expirado. Solicite um novo.` (consumo atômico) |
| Legado de 10 dígitos → próprio CPF formatado `090.000.000-41` | 302 → `/portal/area` HTTP 200 (**corrigido o falso positivo**) |
| Admin (nível 1) → `consultar_cpf` (vítima) | 302 → `/portal/area` HTTP 200 + `audit_log` `PORTAL_ACESSO_PRIVILEGIADO` |
| Diretoria executiva → `consultar_cpf` (vítima) | 302 → `/portal/area` HTTP 200 |
| Gestor (nível 2) → CPF de terceiro no portal | bloqueado (não é Admin nem Diretoria Executiva — regra do enunciado) |
| Usuário `usuarios` nível 3 sem CPF vinculado | bloqueado com a mensagem de segurança + aviso na tela |
| Payload `cpf[]=...` / `token[]=...` | HTTP 200 sem `Fatal error` (antes: **HTTP 500**) |

### 3.3 Frontend

| Perfil | Campos CPF nas abas CPF+Senha e Link Mágico | Aba "Consultar por CPF" |
|---|---|---|
| Comum (CPF vinculado) | `readonly`, preenchidos com `900.000.001-87` | oculta |
| Diretoria executiva | `readonly`, preenchidos com o próprio CPF | visível |
| Admin com CPF vinculado | `readonly`, preenchidos com o próprio CPF | visível |
| Admin sem CPF vinculado | editáveis (sem CPF para preencher) | visível |
| Usuário sem CPF vinculado | editáveis + aviso de segurança | oculta |

### 3.4 Exposições críticas (verificação ao vivo)

| Verificação | Antes | Depois |
|---|---|---|
| `GET /tmp/bridge_token.php` | 200 (JWT de Admin) | **403** (arquivo removido) |
| `GET /tmp/admin_teste.sql`, `/logs/api_debug.log` | 200 | **403** |
| `GET /dev-utils/test_api_ficha.php` | 200 executando PHP | **403** |
| `GET /check_cpf.php`, `/run_sql.php`, `/composer.json` | 200 | **403** |
| `GET /api/relatorios/lista-associados.php` (anônimo) | 200 com CPF/RG/e-mail/telefone de todos | **401** |
| `GET /api/relatorios/status-associados.php` (anônimo) | 200 com CPF/telefone/dívida | **401** |
| `GET /api/relatorios/inadimplencia.php` (anônimo) | 200 | **401** |
| `GET /api/get_propriedades_associado.php?associado_id=13` (anônimo) | 200 | **401** |
| `GET /api/get_propriedade_detalhes.php?id=8` (anônimo) | 200 com endereço/observações | **401** |
| `GET /associado/13/editar` (comum) | 200 com dados do associado 13 | negado |
| `GET /certificacao/historico/13` (comum) | 200 | negado |
| `GET /api/relatorios/lista-associados.php` (Admin/Gestor) | 200 | 200 (funcionalidade preservada) |
| `GET /api/get_propriedades_associado.php?associado_id=86` (comum) | 200 | 200 (próprios dados) |
| `GET /manifest.json`, `/assets/css/style.css`, `/login` | 200 | 200 (nada legítimo foi bloqueado) |
| Troca de e-mail no portal para e-mail de outra conta | aceita (escalonamento) | recusada; e-mail do banco inalterado |
| Troca de e-mail no portal para e-mail livre | aceita | aceita (funcionalidade preservada) |

---

## 4. Arquivos alterados/criados

**Criados**
- `classes/PortalAccess.php` — guarda central de autorização do portal (regra única).
- `classes/ApiAuth.php` — guarda de autorização para endpoints/AJAX (login, staff, nível, dono).
- `tmp/.htaccess`, `dev-utils/.htaccess`, `logs/.htaccess`, `producao_bkp/.htaccess` — negação web (403).
- `classes/.htaccess`, `config/.htaccess`, `includes/.htaccess`, `vendor/.htaccess`, `sql/.htaccess`, `tools/.htaccess`, `scripts/.htaccess` — diretórios internos nunca servidos pela web (403). `doc/` e `uploads/` foram preservados (usados por telas do sistema).

**Alterados**
- `modulos/portal/login.php` — guarda em todos os fluxos, e-mail do link mágico, sessão regenerada, auditoria, entradas normalizadas, CPF `readonly`, vínculo real com a conta de sistema.
- `modulos/portal/area.php` — unicidade de e-mail na atualização de perfil + normalização de entradas.
- `modulos/portal/logout.php` — encerra sempre a sessão do portal; Admin/Diretoria que estava só consultando volta ao sistema sem perder a sessão principal.
- `classes/User.php` — `hasPermission()` com validação numérica estrita do nível (corrige escalonamento por nível vazio).
- `classes/PasswordReset.php` — consumo atômico do token e rejeição de entradas não-string.
- `modulos/associados/editar.php` — fim do IDOR de leitura.
- `modulos/certificacao/historico.php` — fim do IDOR.
- `modulos/associados/ficha_adesao_visualizar.php` — autorização centralizada (`PortalAccess`).
- `api/relatorios/lista-associados.php`, `api/relatorios/status-associados.php`, `api/relatorios/inadimplencia.php`, `api/get_propriedades_associado.php`, `api/get_propriedade_detalhes.php` — autenticação/autorização.
- `login.php` (raiz) — entradas não-string não geram mais HTTP 500 + `session_regenerate_id(true)` no login (anti fixação de sessão).
- `config/config.php` — cookie de sessão com `HttpOnly`, `SameSite=Lax` e `Secure` automático em HTTPS; `session.use_strict_mode` e `use_only_cookies` ativados.
- `index.php` — rotas públicas `recuperar-senha` e `redefinir-senha/<token>` (a recuperação de senha estava quebrada).
- `includes/header.php` — exceções declaradas para páginas públicas/do portal (`$PERMITIR_ACESSO_ANONIMO`, `$PERMITIR_SESSAO_PORTAL`) + correção de `window.USER_NIVEL`.
- `includes/menu.php` — nome do usuário seguro para visitante, nome do associado na sessão só-portal, Clusters restrito a Admin.
- `modulos/usuarios/editar.php`, `modulos/usuarios/cadastrar.php` — fim do escalonamento gestor→admin, whitelist de nível/status e CSRF.
- `modulos/associados/meu_perfil.php` — atribuição de cargo restrita e sincronização entre tabelas por vínculo (FK), não por e-mail.
- `modulos/associados/listar.php`, `modulos/caderno_campo/lavouras/listar.php` — correções de coerência de permissão/redirecionamento.
- `.htaccess` — bloqueio de scripts de desenvolvimento e de extensões sensíveis.
- `tmp/bridge_token.php` — **removido** (emissor de JWT de Admin sem autenticação).

---

## 5. Ações obrigatórias em produção (pendências fora do código desta sessão)

1. **Rotacionar `JWT_ACCESS_SECRET`** em `sinporg/.env`. O `.env` responde 403, mas o segredo assinava tokens emitidos por um endpoint que esteve público — deve ser considerado comprometido.
2. **Mover `tmp/`, `dev-utils/`, `producao_bkp/`, `logs/` para fora do docroot.** O `.htaccess` já nega o acesso, porém a prática correta é não publicar ferramentas de desenvolvimento.
3. Revisar os endpoints de `api/*.php` restantes (sendo fechados em paralelo por subagente dedicado) — ver `relatorios/correcao-auth-apis-2026-02.md`.
4. ~~Endurecer cookies de sessão~~ **feito** em `config/config.php` (HttpOnly + SameSite=Lax + Secure em HTTPS + strict mode). Falta revisar o `session.cookie_secure` no servidor de produção e avaliar CSP/HSTS.
5. Adicionar CSRF ao `completar_perfil.php` e proteção anti-CSRF no `login.php` raiz.
6. Padronizar a validade do link mágico exibida na interface (a tela diz 30 min, o código define 60 min).
7. Criar teste automatizado de regressão de acesso (o harness usado nesta sessão é um bom ponto de partida) e incluir a suíte no CI.

---

## 6. Verificação independente (agentes)

- **Auditoria de segurança (subagente, somente leitura):** relatório em `relatorios/auditoria-portal-acesso-2026-02.md`.
- **Revisão de coerência de autenticação/permissões (subagente):** relatório em `relatorios/revisao-coerencia-auth-2026-02.md`.
- **Fiscalização independente da correção (subagente):** relatório em `relatorios/fiscalizacao-portal-2026-02.md`.
- **Fechamento de autenticação das APIs restantes (subagente):** `relatorios/correcao-auth-apis-2026-02.md`.

---

## 7. Correções adicionais (revisão de coerência de autenticação)

A revisão independente de coerência (agente de análise) encontrou falhas funcionais e de escalonamento
que também foram corrigidas nesta sessão, todas validadas por HTTP:

| # | Achado | Correção | Evidência |
|---|--------|----------|-----------|
| F-01 | **Recuperação de senha inoperante**: o link enviado por e-mail (`/redefinir-senha/<token>`) não tinha rota no `index.php` → 404 → `/login` | Rotas públicas `recuperar-senha` e `redefinir-senha/<token>` no roteador + link "Esqueceu?" do login corrigido (era `href="#"`) | `GET /recuperar-senha` → 200; `GET /redefinir-senha/<token válido>` → 200 (página de nova senha); token inválido → página de erro |
| F-02 | **Portal inacessível a anônimo**: `header.php` exigia login, então nem o Link Mágico nem CPF+senha funcionavam para o associado não logado | `includes/header.php` passou a aceitar duas exceções declaradas pela própria página: `$PERMITIR_ACESSO_ANONIMO` (login do portal) e `$PERMITIR_SESSAO_PORTAL` (`/portal/area` com sessão só do portal) | `GET /portal/login` → 200 (sem aba administrativa); anônimo loga com CPF+senha → `/portal/area` 200 com os próprios dados; ficha própria acessível, ficha de terceiro negada |
| F-03 | **Gestor → Admin**: `modulos/usuarios/editar.php` exigia apenas nível 2 e gravava `nivel` do POST sem whitelist (auto-promoção a Admin), sem CSRF; `cadastrar.php` permitia a um gestor criar Admin | Whitelist de nível/status, gestor não edita contas de equipe (níveis 1/2) nem a própria conta, criação de Admin só por Admin, CSRF nos dois formulários, opção "Admin" oculta para não-admins | Gestor: `GET/POST /usuario/116/editar` (auto-promoção) → "Acesso negado", nível permanece 2; gestor sobre Admin (id 7) → negado; POST sem CSRF → rejeitado e nome inalterado; Admin → página funcional |
| F-04 | **`hasPerfilCompleto()` exigia o campo `nicho`** (que ~94% da base não tem e só era editável em tela sem link), desviando quase todos para `/meu-perfil` | Passou a aceitar `nicho` **ou** telefone/cidade/endereço/CEP | Usuário sem telefone → redireciona a `/meu-perfil`; com telefone → vai ao dashboard |
| F-05 | **Conta `usuarios` inacessível** quando o mesmo e-mail existe em `associados` (4 casos, incluindo o Admin): o login achava a linha de associado e nunca testava `usuarios` | Login valida as duas tabelas pelo hash e prefere a conta de sistema (`usuarios`); mesma correção em `User::login()` | Admin (`techostin@gmail.com`, presente nas duas tabelas) autentica com o nível 1 de `usuarios` |
| F-10 | `/meu-perfil` sincronizava dados entre `associados` e `usuarios` com `UPDATE ... WHERE email = ?` (escrita em outra conta) | Sincronização passou a usar o **vínculo real** (`usuarios.associado_id` / `associados.user_id`) | Página renderiza (200) e o update continua funcionando; nenhuma escrita por e-mail |
| F-11 | Bugs silenciosos de coerência | `$_SESSION['nivel']` → `user_nivel` (window.USER_NIVEL); `hasPermission('admin')` → `NIVEL_ADMIN` (botão de excluir lavoura); menu de Clusters oculto para gestor (página exige Admin); redirecionamento de associado comum em `/associados` corrigido (`user_associado_id` nunca existia) | Lint + smoke tests |
| F-13 | Qualquer cargo de diretoria/comissão podia atribuir cargos de **qualquer** associado, inclusive autoatribuir "presidente" (que dá acesso ao portal de todos) | Admin gerencia qualquer vínculo; os demais só os **próprios** vínculos e nunca podem atribuir um cargo executivo novo (só manter o atual) | Diretoria tentando cargo em outro associado → sem alteração; diretoria "virando presidente" de si → cargo forçado a `membro`; Admin atribuindo → permitido |
| F-06 | Session fixation / cookies sem flags | (já corrigido) `session_set_cookie_params(HttpOnly, SameSite=Lax, Secure em HTTPS)`, `use_strict_mode`, `use_only_cookies` e `session_regenerate_id(true)` no login principal e no portal | `Set-Cookie` com `HttpOnly; SameSite=Lax` |

### Pendências conhecidas (não corrigidas nesta sessão)

| # | Pendência | Risco | Recomendação |
|---|-----------|-------|--------------|
| F-09 | CSRF ausente em ~53 handlers POST (usuarios/perfil, contas, associações, caderno de campo, completar_perfil, relatorios) | Médio | Aplicar `CSRFProtection::validatePostRequest()` + `getTokenField()` de forma sistemática |
| F-12 | `usuarios.nivel DEFAULT 1` (Admin) e divergência `nivel` × `tipo` | Médio | Remover o default 1, padronizar `tipo` e usar `nivel` como fonte única |
| F-16 | Login principal sem rate limit (o portal tem 10/15 min) | Baixo | Reaproveitar o limitador de `PortalAccess` |
| F-17 | `getValidToken`/`hasPermission` com argumento > 3 liberaria acessos | Baixo | Validar faixa de níveis |
| — | Rotacionar `JWT_ACCESS_SECRET` e mover dev dirs para fora do docroot | Alto | Ver seção 5 |

---

## 8. Verificação independente — resultado final

| Agente | Papel | Entregável | Resultado |
|--------|-------|-----------|-----------|
| Auditoria de segurança (subagente) | Auditoria do portal/autenticação (somente leitura) | `relatorios/auditoria-portal-acesso-2026-02.md` | 3 críticos, 6 altos, 4 médios; base para as correções extras |
| Revisão de coerência (subagente) | Revisão do modelo de identidade/permissões | `relatorios/revisao-coerencia-auth-2026-02.md` | F-01..F-17; 4 P0 corrigidos nesta sessão |
| **Fiscalização independente (subagente)** | Tentou refutar a correção (red team + QA) | `relatorios/fiscalizacao-portal-2026-02.md` | **APROVADO COM RESSALVAS** — nenhuma tentativa resultou em acesso indevido, sessão de portal indevida ou HTTP 500 |
| Fechamento de auth das APIs (subagente) | Guarda de autenticação em APIs/AJAX | `relatorios/correcao-auth-apis-2026-02.md` | 14 endpoints fechados (401/403) com matriz de evidência |
| IDOR em módulos (subagente) | Correção de IDOR/autenticação em 17 telas | `relatorios/correcao-idor-modulos-2026-02.md` | 17/17 corrigidos, com evidência HTTP por perfil |

### Destaques da fiscalização (estado atual revalidado)
- Comum × vítima nas 4 ações do portal: **mensagem de segurança exata**, 0 links mágicos exibidos e `/portal/area` → 302 (nenhuma sessão criada).
- Bypasses testados e **todos falharam**: CPF formatado/13 dígitos/full-width/com espaços, campos duplicados, `cpf[]`/`token[]` (sem 500), `acao` com caixa diferente, token expirado/reutilizado, **reuso concorrente** (`sucessos=1`), acesso direto a `/portal/area`, ficha por ID numérico e por `encodeId`, POST de aprovar/recusar ficha, usuário sem CPF e gestor nível 2.
- APIs: anônimo 401; comum 403 em dados de terceiros e 200 nos próprios; gestor/admin 200.
- `audit_log` com `PORTAL_ACESSO_NEGADO`, `PORTAL_ACESSO_PRIVILEGIADO`, `PORTAL_LOGIN`, `PORTAL_MAGIC_LINK`/`PORTAL_LOGIN_MAGIC_LINK` e CPF mascarado.
- Ressalvas não bloqueantes: token que não resolve para associado ativo responde "Link inválido ou expirado" (sem exposição); solicitação anônima de link de terceiro gera token e envia e-mail (sem exibir o link); colisão teórica de CPF legado por padding (sem colisões reais).

### Transparência sobre dados de teste
- As contas/fichas/tokens de teste (`teste.%@sinporg.local`, ficha 21) foram **criadas e removidas** ao final; o script `tmp/portal_fixtures.php` recria e limpa tudo.
- Durante um teste de fumaça como Admin, a visita técnica **id 3** foi finalizada com checklist vazio e teve `resultado/pontuacao/pontuacao_maxima/conformidade` sobrescritos. Os valores originais foram **restaurados a partir do backup** `recicla/backups/sisagro_bkp_20260819_182150.sql` (pontuação 100, conformidade "Conforme", checklist com 5 itens conforme) — script `tmp/restore_visita3.php`. Somente `updated_at` permanece com a data da sessão.
- PDFs gerados pelo teste de `api/certificacao/gerar_pdf.php` foram removidos de `uploads/`.
