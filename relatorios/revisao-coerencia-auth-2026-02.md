# Revisão de Coerência — Autenticação, Autorização e Identidade

**Projeto:** SINPORG / app PHP 8.2 (monolito próprio, XAMPP, `http://localhost/app`)
**Revisor:** agente de análise (revisão somente-leitura, sem alteração de código)
**Data da revisão:** 14/09/2026, ~10:20 (horário local)
**Escopo:** login/logout/recuperação de senha, Portal do Associado, modelo de níveis (`NIVEL_*`), identidade entre tabelas/sessões, CSRF e sessão.
**Método:** leitura estática (`read`/`grep`/`glob`), consultas SQL somente-leitura (`mysql --batch`), requisições HTTP GET anônimas (`curl`) e simulação de estado de sessão via `php -r` (CLI). Nenhum login autenticado real foi executado.

---

## 0. Aviso de snapshot (leia antes)

O código **foi alterado por outro processo durante esta revisão** (arquivos com `LastWriteTime` entre 10:11 e 10:18 de 14/09/2026). Portanto os achados valem para o snapshot abaixo, identificado por hash SHA-256 (12 primeiros dígitos):

| Arquivo | mtime | sha256[:12] |
|---|---|---|
| `config/config.php` | 09-11 22:06 | `B9F06E4FA849` |
| `index.php` | 09-08 19:43 | `B424146F0B51` |
| `login.php` | 09-14 10:14 | `F83F1A6930DF` |
| `logout.php` | 08-04 20:12 | `2C8C956D274F` |
| `includes/header.php` | 09-02 22:31 | `6F6335D7AD19` |
| `includes/menu.php` | 09-08 21:51 | `1097827C3108` |
| `classes/User.php` | 09-14 10:11 | `16DD8C6BD3C3` |
| `classes/PortalAccess.php` | 09-14 10:18 | `3F42824F02E7` |
| `classes/PasswordReset.php` | 09-14 10:18 | `DDC3652B21B7` |
| `classes/CSRFProtection.php` | 08-04 01:41 | `1A5087B7F354` |
| `classes/ApiAuth.php` | 09-14 10:16 | `DDCD2DC42DB1` |
| `modulos/portal/login.php` | 09-14 10:18 | `E57B75EC7B10` |
| `modulos/portal/area.php` | 09-14 10:18 | `9863EA02E71F` |
| `modulos/portal/logout.php` | 09-14 10:13 | `452A826208C9` |
| `modulos/usuarios/editar.php` | 03-23 19:45 | `2F62A1BDF878` |
| `modulos/usuarios/cadastrar.php` | 03-23 19:45 | `2A9D9BB17334` |
| `modulos/associados/meu_perfil.php` | 09-03 00:14 | `886A5C94BE28` |
| `api/recuperar_senha.php` | 08-14 09:38 | `24ED1FCDD668` |
| `redefinir_senha.php` | 08-14 09:57 | `7936E92E016F` |

**Mudança relevante ocorrida no meio da revisão:** a versão de `modulos/portal/login.php` lida às 10:14 casava a sessão principal do portal **por e-mail** contra `usuarios` e **não gravava `user_table`** — isso permitia escalonamento (ver F-07/F-08). Às ~10:18 o arquivo já usava `PortalAccess::usuarioVinculadoAoAssociado()` (vínculo real por FK) e grava `user_table = 'usuarios'`. O achado está registrado como **histórico + resíduo de risco**, não como bug presente.

---

## 1. Mapa resumido do modelo de identidade / permissão

### 1.1 Tabelas e papéis

| Tabela / coluna | Papel no modelo | Quem lê |
|---|---|---|
| `associados` (`id`, `nivel`, `tipo`, `tipo_usuario`, `area_producao`, `tem_agroindustria`, `senha`, `status`, `user_id` UNIQUE, `associacao_id`, `nicho`, `orgao`) | Pessoa física associada; também é conta de login do caderno de campo | `login.php:88`, `classes/User.php:57`, `classes/PortalAccess.php:134-145` |
| `usuarios` (`id`, `nivel` **NOT NULL DEFAULT 1**, `tipo`, `tipo_usuario`, `senha`, `status`, `associado_id` UNIQUE, `certificadora_id`) | Equipe interna (admin/gestor/fiscal) | `login.php:95`, `classes/User.php:74`, `api/legacy/session-bridge.php:91` |
| `associado_associacao` (`associado_id`, `associacao_id`, `tipo_vinculo`; PK composta) | Cargo/função (diretoria, comissões, membro) | `classes/User.php:335-338`, `:393-398` |
| `password_resets` (`email`, `token_hash` SHA-256, `expires_at`, `used`) | Token de link mágico / recuperação (1 ativo por e-mail) | `classes/PasswordReset.php:48-78` |
| `nivel`: 1=Admin, 2=Gestor, 3=Usuário | Fonte **efetiva** de autorização | todo o sistema |

Constantes: `config/config.php:23-25` → `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`, `NIVEL_USUARIO=3` ("menor = mais poder").

### 1.2 Sessões (dois modelos convivendo na mesma sessão PHP)

| Modelo | Chaves | Criado em |
|---|---|---|
| **Sessão principal** | `user_id`, `user_nivel`, `user_nome`, `user_tipo`, `user_tipo_usuario`, `user_area_producao`, `user_table` | `login.php:42-50` (etapa 2 gestor), `login.php:123-131`, `login.php:143-148`, `classes/User.php:63-69`, `:80-86` |
| **Sessão do Portal** | `associado_id`, `associado_nome`, `associado_cpf`, `associado_email`, `portal_acesso_via`, `portal_acesso_por` | `modulos/portal/login.php:121-126` (consulta admin), `:160-174` (CPF+senha), `:281-295` (link mágico) |
| **Transientes** | `login_temp`, `primeiro_acesso`, `portal_tentativas`, `csrf_token`, `mostrar_modal_eventos`, `user_config` | login/portal/CSRF |

Ambos os modelos **coabitam a mesma sessão** (mesmo `PHPSESSID`), sem namespace — origem de várias incoerências (F-07, F-08, F-15).

### 1.3 Checks e onde são aplicados

| Check | Implementação | Semântica |
|---|---|---|
| `User::isLoggedIn()` | `classes/User.php:96-98` | `isset($_SESSION['user_id'])` — **ignora** sessão só-portal |
| `User::hasPermission($n)` | `classes/User.php:104-131` | exige `isLoggedIn` e `user_nivel` numérico (>0); `nivel===1` ⇒ true; senão `nivel <= n` |
| `User::getAssociadoId()` | `classes/User.php:249-262` | `user_table==='associados'` ⇒ `currentUser.id`; senão `currentUser.associado_id` (lê `usuarios`) |
| `User::getAssociadoIdResolvido()` | `classes/User.php:289-319` | prioridade: `associado_id` → `user_table==='associados'` → `usuarios.associado_id` → `associados.user_id` |
| `User::podeAcessarDiretoria()` | `classes/User.php:370-373` | Admin **ou** qualquer cargo de `TIPOS_VINCULO_DIRETORIA` (inclui conselho fiscal e comissões) |
| `User::isDiretoriaExecutiva()` | `classes/User.php:382-404` | Admin **ou** `tipo_vinculo` em {presidente, vice-presidente, diretor, secretario, tesoureiro} |
| `PortalAccess::ehPrivilegiado()` | `classes/PortalAccess.php:256-263` | Admin **ou** Diretoria Executiva — libera ler/impersonar **qualquer** associado |
| `PortalAccess::bloqueioAtivo()` | `classes/PortalAccess.php:340-345` | autenticado **e** não privilegiado ⇒ só o próprio CPF |
| `ApiAuth::autenticado()/ehStaff()/exigirProprioAssociado()` | `classes/ApiAuth.php:56-73`, `:135-146` | **exige `user_id`** (não reconhece sessão só-portal) |
| Guarda de página | `includes/header.php:8-11` | qualquer página que inclua `header.php` exige `isLoggedIn()` |
| CSRF | `classes/CSRFProtection.php:24-38` | só `$_POST`; aplicado em **36 de 89** handlers POST |

---

## 2. Incoerências e bugs (arquivo:linha)

Legenda de severidade: **CRÍTICO** (quebra objetivo/segurança grave) · **ALTO** · **MÉDIO** · **BAIXO** · **INFO**.

### F-01 — CRÍTICO — Recuperação de senha 100% inoperante (rota inexistente)
- **Evidência:** `api/recuperar_senha.php:42` monta o link `SITE_URL . '/redefinir-senha/' . $token`; `redefinir_senha.php:99` envia o formulário para `SITE_URL . '/redefinir-senha/' . $token`; **não existe rota** para esse slug em `index.php` (grep `redefinir|recuperar` em `index.php`: nenhuma ocorrência). O roteador cai no fallback `index.php:1162-1164` → `404.php:3` inclui `includes/header.php` → `includes/header.php:8-11` redireciona para `/login`.
- **Confirmado empiricamente:** `GET /app/redefinir-senha/abc` → **302 → http://localhost/app/login**; `GET /app/recuperar-senha` → **302 → /app/login**.
- **Agravante:** o link "Esqueceu?" do login é `login.php:632` `<a href="#" class="login-link">Esqueceu?</a>` — link morto; `/recuperar-senha` também não tem rota.
- **Impacto no objetivo:** nenhum usuário ou associado consegue redefinir senha (nem pelo e-mail, nem pela tela); recuperação depende de intervenção manual no banco — perda de acesso em massa.
- **Correção:** adicionar em `index.php` as rotas `recuperar-senha` (GET → `recuperar_senha.php`) e `redefinir-senha/<token>` (GET/POST → `redefinir_senha.php` com `$_GET['token'] = $parts[1]`), apontar `login.php:632` para `/recuperar-senha` e incluir CSRF no formulário de redefinição (`redefinir_senha.php:99`).

### F-02 — CRÍTICO — Portal do Associado inacessível a visitante anônimo (login e link mágico quebrados)
- **Evidência:** `modulos/portal/login.php:331` inclui `includes/header.php`, cuja guarda (`includes/header.php:8-11`) redireciona quem não está logado para `/login`.
- **Confirmado empiricamente:** `GET /app/portal/login` → **302 → /app/login**; `GET /app/portal/login?etapa=verificar_token&token=…` (o link enviado por e-mail, gerado em `modulos/portal/login.php:206`) → **302 → /app/login**.
- **Impacto no objetivo:** o público-alvo do portal (associado que não tem login no sistema principal) não consegue nem abrir a tela de CPF+senha nem consumir o link mágico. O portal só funciona para quem **já** está logado no sistema principal, e `includes/menu.php:23` ("Portal do Associado") só aparece nesse caso. Para admin/gestor sem vínculo de associado, o menu leva a `/portal/area` → `/portal/login` → `/login` (ciclo de redirecionamento).
- **Correção:** usar um header próprio do portal (sem a guarda de `isLoggedIn()`) ou introduzir `$permitir_anonimo = true` antes do include; proteger `modulos/portal/area.php` por `associado_id` (já faz: `area.php:20-23`) e o POST por CSRF (já faz: `area.php:76`).

### F-03 — ALTO — Gestor eleva a si mesmo (ou a qualquer um) a Admin
- **Evidência:** `modulos/usuarios/editar.php:6` exige apenas `hasPermission(NIVEL_GESTOR)` (nível ≤ 2); `:40` lê `$nivel = $_POST['nivel']`; `:44`/`:47` gravam `nivel` do POST sem whitelist nem limite — inclusive para o **próprio** `id`. O arquivo **não usa** `CSRFProtection` (grep: 0 ocorrências).
- **Evidência correlata:** `modulos/usuarios/cadastrar.php:7` permite gestor acessar e `:22` insere com `nivel` arbitrário do POST.
- **Impacto:** qualquer conta nível 2 (gestor) obtém nível 1 = Admin → acesso a exclusões, financeiro, cadastro de usuários e relatórios administrativos. Com o F-09 (sem CSRF), a promoção pode ser disparada por uma página de terceiros.
- **Correção:** exigir `hasPermission(NIVEL_ADMIN)` para criar/alterar usuários; validar `nivel` em {1,2,3}; impedir alteração de `nivel` do próprio usuário (`$id === $_SESSION['user_id']`); aplicar CSRF.

### F-04 — ALTO — hasPerfilCompleto() + redirecionamento bloqueiam o dashboard de quase toda a base
- **Evidência:** `classes/User.php:410-431` — para `NIVEL_USUARIO` exige `nicho` (`:426-427`); `login.php:157-161` redireciona para `/meu-perfil` quando incompleto (e `:133-137` para gestor).
- A única tela que grava `nicho`/`orgao`/`setor`/`especialidade` é `/completar-perfil` (`completar_perfil.php:38`, `:149-156`), que **não é linkada em lugar nenhum** (grep `completar-perfil`: apenas `index.php:829-830` e o próprio form `completar_perfil.php:121`). `modulos/associados/meu_perfil.php` **não possui** nenhum input `name="nicho|orgao|setor|especialidade"` (grep: 0).
- **Dados (confirmados):** `associados`: 32 de 34 sem `nicho`; `usuarios` nível 3: 5 de 6 sem `nicho`.
- **Impacto:** a cada login, o associado/usuário nível 3 é desviado para "Meu Perfil" em vez do dashboard, e não há caminho de UI para completar o perfil.
- **Correção:** ou remover `nicho` do critério de bloqueio, ou linkar `/completar-perfil` (e migrar esses campos para `/meu-perfil`); alinhar o critério ao que o pré-cadastro efetivamente coleta (`api/pre_cadastro.php:124-140` não grava `nicho`).

### F-05 — ALTO — Precedência de tabela no login torna a credencial de usuarios inalcançável quando há associado com o mesmo e-mail
- **Evidência:** `login.php:88-101` consulta **primeiro** `associados` e só consulta `usuarios` se não encontrar nada (`:94-99`); a validação de senha (`:101`) é feita contra a linha encontrada. Idêntico em `classes/User.php:57-71` e `:74-87` e em `classes/PasswordReset.php:26-38` (o reset sempre atinge `associados`).
- **Dados:** 4 e-mails existem nas duas tabelas — `usuarios` 7/11/12/14 = `associados` 13/28/29/31.
- **Impacto:** se as senhas divergirem, a conta de `usuarios` (inclusive o Admin do sistema) **nunca autentica** ("E-mail ou senha inválidos!") e o "esqueci a senha" altera a senha do **associado**, nunca a dela.
- **Não confirmado:** se as senhas em texto claro coincidem (só é possível comparar hashes, que diferem por salt em bcrypt — `mesma_senha = 0` para os 4 pares, o que é inconclusivo). A falha estrutural, no entanto, é certa.
- **Correção:** autenticar contra **as duas** tabelas e aceitar aquela cujo `password_verify()` retornar true; considerar unicidade global de e-mail (ou desambiguar o login por tipo de conta).

### F-06 — ALTO — Session fixation e cookies de sessão sem flags
- **Evidência:** `config/config.php:84-86` chama `session_start()` sem `session_set_cookie_params`/`ini_set` (grep de `session_set_cookie_params|session_name(|ini_set('session` no app: **0 ocorrências**). O login principal grava a sessão **sem** regenerar o ID (`login.php:123-131`, `:143-148`; `classes/User.php:63-86`). O portal regenera (`modulos/portal/login.php:157` e `:278`) — padrão inconsistente.
- **Confirmado empiricamente (cabeçalho HTTP):** `Set-Cookie: PHPSESSID=…; path=/` — **sem HttpOnly, sem SameSite, sem Secure**.
- **Impacto:** (a) *session fixation*: o atacante planta um `PHPSESSID` na vítima e assume a sessão depois do login; (b) `PHPSESSID` acessível a JavaScript ⇒ roubo de sessão via XSS; (c) ausência de `SameSite` agrava o CSRF (F-09).
- **Correção:** antes do `session_start()`: `session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=> (SITE_URL começa com https)]);` e `session_regenerate_id(true)` em todo login/logout/mudança de privilégio.

### F-07 — MÉDIO-ALTO — user_table ausente ⇒ identidade resolvida na tabela errada
- **Estado atual:** `modulos/portal/login.php:169-174` e `:290-295` **já gravam** `$_SESSION['user_table'] = 'usuarios'` (correção das 10:18). **Resíduo:** `User::tabelaUsuarioSegura()` (`classes/User.php:31-52`) assume `associados` quando a chave está ausente (`:40-42`), e `getCurrentUser()` (`:226-243`) faz `SELECT * FROM {tabela} WHERE id = {user_id}`.
- **Consequência verificada (código anterior, 10:14):** a sessão criada pelo portal com `user_id = 7` (id da linha em `usuarios`) e sem `user_table` fazia `getCurrentUser()` ler `associados WHERE id = 7` → **false / dado de outra pessoa**; `modulos/associados/meu_perfil.php:185` grava pelo mesmo caminho (`User::updateCurrentUser`, `classes/User.php:483-538`), ou seja, escreveria na linha errada de `associados`. Simulação CLI no snapshot anterior: `getCurrentUser=false`, `tabelaUsuarioSegura=associados` para `user_id=7`.
- **Correção:** nunca ter default silencioso — derivar a tabela da própria identidade (ou de coluna `origem` na sessão) e **falhar fechado** (negar) quando ausente/inválida.

### F-08 — MÉDIO-ALTO — A senha do associado concede a sessão de sistema do usuarios vinculado (troca de credencial)
- **Evidência:** `modulos/portal/login.php:153` valida `password_verify($senha, $associado['senha'])` (senha do **associado**); `:169-174` (e `:290-295`) então concedem `user_id`/`user_nivel` do registro em `usuarios` vinculado por FK (`classes/PortalAccess.php:190-211`), **sem exigir a senha dessa conta**.
- **Dados (não explorável hoje):** os vínculos existentes apontam para nível 3 (`associados` 28/29 → `usuarios` 11/12); o `usuarios` 7 (nível **1**) tem `associado_id = 1` **inexistente** e `associados` 13 tem `user_id = 1` **inexistente** (FKs órfãs). Simulação CLI no código atual: `usuarioVinculadoAoAssociado(13)` → "sem vínculo"; `(28)` → `usuarios.id=11 nivel=3`.
- **Impacto:** o modelo permite que uma credencial de portal conceda a sessão de sistema (inclusive Admin) de outra conta. **Não confirmado como exploração ativa** no dataset atual; é risco latente de desenho.
- **Correção:** a sessão do portal não deve elevar para sessão de sistema; se for necessária, exigir a senha da conta `usuarios` (ou limitar `user_nivel` herdado a `NIVEL_USUARIO` e não gravar `user_id`).

### F-09 — MÉDIO — CSRF ausente em 53 dos 89 handlers POST
- **Evidência:** `classes/CSRFProtection.php:24-38` implementa validação correta, mas apenas **36 arquivos** a invocam. Sem validação, entre outros: `modulos/usuarios/editar.php`, `modulos/usuarios/cadastrar.php`, `modulos/usuarios/perfil.php`, `modulos/usuarios/configuracoes.php`, `modulos/associados/pendentes.php`, `modulos/contas/{cadastrar,editar}.php`, `modulos/associacoes/{cadastrar,editar}.php`, `modulos/caderno_campo/**` (propriedades, lavouras, insumos, produtos, lotes, categorias), `modulos/producao_animal/**`, `modulos/agroindustria/**`, `completar_perfil.php`, `relatorios.php`.
- **Impacto:** escrita de dados (inclusive a promoção a Admin do F-03) disparável por página de terceiros com o cookie da vítima; agravado pela ausência de `SameSite` (F-06).
- **Ressalva de método:** a classificação vem da ausência da string `CSRFProtection` no arquivo (grep), não de inspeção caso a caso de todos os 53 — pode haver validação por outro caminho em algum deles.
- **Correção:** validar CSRF de forma central (ex.: no topo de `index.php` para todo `POST`, com lista de exceções), além de `SameSite=Lax`.

### F-10 — MÉDIO — /meu-perfil sincroniza dados entre associados e usuarios por e-mail (escrita cruzada entre contas)
- **Evidência:** `modulos/associados/meu_perfil.php:185` → `User::updateCurrentUser()` (`classes/User.php:483-538`), cuja lista de bloqueio **não inclui `email`** (`:490`) e trata `email` como campo atualizável (`:513`). Em seguida, `:204-234` sincroniza por e-mail: `:216` `UPDATE usuarios SET … WHERE email = :email` e `:231` `UPDATE associados SET … WHERE email = :email` (campos `nome, telefone, orgao, setor, especialidade, nicho` e, no sentido inverso, também `cpf`, `sobrenome`, endereço).
- **Impacto:** como o e-mail é editável, um usuário pode apontar seu e-mail para o de outra conta e sobrescrever dados dessa conta (nome, telefone, órgão, setor, especialidade, nicho, cidade/CEP conforme o lado). Também quebra silenciosamente quando qualquer e-mail muda.
- **Correção:** sincronizar pelo FK (`usuarios.associado_id` / `associados.user_id`), nunca por e-mail; bloquear alteração de e-mail em `/meu-perfil` (ou exigir confirmação por token no novo endereço).

### F-11 — MÉDIO — Divergências entre menu, páginas e helpers

| # | Evidência | Efeito |
|---|---|---|
| a | `includes/menu.php:111` mostra "Clusters de Comunicação" sob `hasPermission(NIVEL_GESTOR)`; `modulos/admin/clusters.php:11` exige `hasPermission(NIVEL_ADMIN)` | Gestor vê link que devolve 302 para `/login` |
| b | `includes/menu.php:23` mostra "Portal do Associado" a todos; `modulos/portal/area.php:20-23` exige `associado_id` | Admin/gestor sem vínculo cai no ciclo de redirecionamento (F-02) |
| c | `modulos/associados/listar.php:57-64` redireciona usuário comum para `/usuario/<user_id>/editar`, que exige nível ≤ 2 (`modulos/usuarios/editar.php:6`); usa `$_SESSION['user_associado_id']`, chave **nunca escrita** (só existe propriedade homônima em `classes/ReportManager.php:9`) | Usuário comum recebe "Você não tem permissão"; ramo `isset()` é código morto |
| d | `modulos/caderno_campo/lavouras/listar.php:102` usa `hasPermission('admin')` → `is_numeric('admin')` é false (`classes/User.php:115-118`) | Check **sempre falso**: o botão de excluir lavoura nunca é renderizado (nem para Admin) |
| e | `includes/header.php:76` publica `window.USER_NIVEL` a partir de `$_SESSION['nivel']`, chave que **nunca é escrita** (o correto é `user_nivel`) | Variável JS sempre vazia (grep confirma: única ocorrência no app) |

### F-12 — MÉDIO — Modelo nivel × tipo inconsistente (a coluna tipo é ignorada na autorização)
- **Schema:** `usuarios.nivel INT NOT NULL **DEFAULT 1**` (1 = Admin) convive com `usuarios.tipo ENUM('admin','gestor','usuario') DEFAULT 'usuario'`; `associados.tipo ENUM('Admin','Gestor','Usuario')` (maiúsculas) × `usuarios.tipo` (minúsculas).
- **Dados:** `usuarios` 8 (João da Silva) e 16 (Gabriel Cibulski) têm `nivel = 1` com `tipo = 'usuario'`; 10 e 116 têm `nivel = 2` com `tipo = 'usuario'`; `associados` 13 tem `tipo = 'Admin'`.
- **Impacto:** toda autorização usa `nivel` (`classes/User.php:104-131`), então contas "usuário" são de fato Admin; telas e rotinas que exibem/comparam `tipo` divergem; qualquer `strtolower($tipo)` funciona em `usuarios` e falha em `associados` (ex.: `modulos/associados/meu_perfil.php:197-198`); o DEFAULT 1 faz novas linhas de `usuarios` nascerem Admin se a coluna for omitida.
- **Correção:** eleger `nivel` como fonte única (deprecar `tipo` ou derivá-lo), remover o DEFAULT 1 da coluna e padronizar o case.

### F-13 — BAIXO-MÉDIO — Delegação de privilégio via associado_associacao.tipo_vinculo
- **Evidência:** `modulos/associados/meu_perfil.php:94` define `$podeGerenciarVinculos = hasPermission(NIVEL_GESTOR) || isMembroDiretoria()` e `:112-137` grava vínculos/cargos de **qualquer** `associado_id` recebido por GET (`:95`) / POST (`:117`), sem verificar se o operador pertence àquela associação. `isDiretoriaExecutiva()` (`classes/User.php:382-404`) e `PortalAccess::ehPrivilegiado()` (`classes/PortalAccess.php:256-263`) derivam privilégio desse campo.
- **Cadeia:** um `conselho_fiscal`/`comissao_etica` (que tem `podeAcessarDiretoria`) pode autoatribuir `presidente` → passa a `isDiretoriaExecutiva()` → passa a `ehPrivilegiado()` → consulta/impersona **qualquer** associado no portal (`modulos/portal/login.php:107-136`).
- **Não confirmado:** nenhum vínculo atual usa essa cadeia para virar admin; os cargos existentes são `diretor` (4), `presidente` (2) e `membro` (19).
- **Correção:** restringir a atribuição de cargo a Admin, limitar ao escopo da associação do operador, registrar auditoria com antes/depois e exigir dupla aprovação para cargos executivos.

### F-14 — BAIXO — ApiAuth e PortalAccess são guardas paralelas com modelos de identidade diferentes
- **Evidência:** `classes/ApiAuth.php:56-60` exige `!empty($_SESSION['user_id'])`; `classes/PortalAccess.php:246-250` aceita também sessão só-portal (`associado_id`). Endpoints que usam `ApiAuth`: `api/get_propriedades_associado.php:5,9`, `api/get_propriedade_detalhes.php:6,9`, `api/relatorios/lista-associados.php`, `status-associados.php`, `inadimplencia.php`.
- **Impacto:** um associado autenticado **apenas** no portal recebe 401 nesses endpoints, embora a regra de negócio do portal o autorize — comportamento inconsistente conforme a porta de entrada; e um vínculo de portal com `usuarios` (F-08) passa a valer como staff (`ehStaff` = nível ≤ 2, `ApiAuth.php:69-73`).
- **Correção:** uma única função de identidade (`origem`, `id`, `associadoId`, `nivel`) consumida por `ApiAuth` e `PortalAccess`.

### F-15 — BAIXO — Logout acopla portal e sessão principal
- **Evidência:** `modulos/portal/logout.php:17-18` preserva a sessão principal **apenas** quando `portal_acesso_via === 'consulta_admin'`; nos fluxos `login_comum`/`magic_link` executa `session_destroy()` (`:40`) → quem estava logado no sistema e usou o portal perde a sessão principal. No sentido inverso, `logout.php:8` → `User::logout()` (`classes/User.php:100-102`) destrói tudo, inclusive o portal.
- **Correção:** separar o estado do portal (namespace próprio, ex.: `session_name` distinto ou subchave) ou restaurar explicitamente a sessão principal ao sair do portal.

### F-16 — BAIXO — Sem limite de tentativas no login principal
- **Evidência:** `login.php:75-183` não tem contador/backoff/lockout (o portal tem: `classes/PortalAccess.php:31-32`, `:427-449`, 10 tentativas/15 min).
- **Correção:** contador por e-mail+IP com backoff progressivo, registro em `AuditLog` e alerta.

### F-17 — INFO — hasPermission está fail-closed; risco residual é do argumento
- **Evidência:** `classes/User.php:104-131` rejeita nível não numérico (`:115-118`) e ≤ 0 (`:121-123`), trata 1 como superusuário (`:126`) e usa `$nivel <= $required_level` (`:130`). Auditoria dos 178 usos: 156× `NIVEL_GESTOR`, 16× `NIVEL_ADMIN`, 4× `NIVEL_USUARIO`, 1× argumento dinâmico (a própria definição) e **1× `hasPermission('admin')`** (`modulos/caderno_campo/lavouras/listar.php:102`) que é **sempre false** (ver F-11d). Não há uso com literal numérico divergente.
- **Risco residual:** um argumento maior que 3 (ex.: `hasPermission(4)`) liberaria todos; recomenda-se validar o domínio (enum ou `assert(in_array($required_level, [1,2,3]))`).

---

## 3. Riscos de escalonamento de privilégio (fluxos concretos)

**E-1 — Gestor → Admin (presente, explorável).**
Login como gestor (nível 2) → `POST /usuario/<seu_id>/editar` com `nivel=1` e cookie de sessão → `modulos/usuarios/editar.php:6` autoriza (≤2) → `:44`/`:47` persistem `nivel=1`. Sem CSRF, a requisição pode vir de outra origem. Resultado: Admin (financeiro, exclusões, usuários, relatórios). **Correção: F-03.**

**E-2 — Associado → Sessão de sistema (via portal, por vínculo FK).**
`associados.user_id` ou `usuarios.associado_id` liga um associado a uma conta `usuarios` de **qualquer** nível → o associado autentica no portal com CPF + senha **do associado** (`modulos/portal/login.php:153`) → `:169-174`/`:290-295` gravam `user_id`/`user_nivel` **da conta vinculada** → `User::hasPermission()` (`classes/User.php:104-131`) passa a autorizar no sistema principal (e `api/legacy/session-bridge.php:89-106` emite JWT `origem=staff` com esse nível, incluindo o `associadoId`). **Hoje mitigado** porque os vínculos existentes são de nível 3 e há FKs órfãs; o desenho segue permitindo. **Correção: F-08 + validação de integridade das FKs.**

**E-3 — Associado → Admin por troca de e-mail (presente no snapshot 10:14, corrigido às 10:18; risco residual em F-10).**
A versão anterior de `modulos/portal/login.php` casava a conta `usuarios` **por e-mail** sem checar vínculo. Combinada com `modulos/associados/meu_perfil.php:185` (e-mail editável — `classes/User.php:490` não bloqueia `email`), a cadeia era: (1) o associado altera o próprio e-mail para o do Admin; (2) loga no portal com CPF+senha; (3) recebe `user_id`/`user_nivel` do Admin (nível 1) e a sessão principal, **sem conhecer a senha do Admin**. Simulação CLI nesse snapshot confirmou: `hasPermission(NIVEL_ADMIN)=true`, `podeAcessarDiretoria=true`, `isDiretoriaExecutiva=true`, `bloqueioAtivo=false` (a guarda do portal deixava de restringir). **O casamento por e-mail foi substituído por vínculo FK (`classes/PortalAccess.php:190-211`)** — o risco direto caiu, mas a escrita cruzada por e-mail (F-10) permanece e deve ser fechada por completude.

**E-4 — Comissão/Conselho Fiscal → Diretoria Executiva → leitura de qualquer associado.**
`modulos/associados/meu_perfil.php:112-137` grava cargo de qualquer `associado_id`; `classes/User.php:382-404` deriva `isDiretoriaExecutiva` de `tipo_vinculo`; `classes/PortalAccess.php:256-263` libera leitura/impersonação de qualquer associado (dados financeiros, CPF, ficha de adesão). **Correção: F-13.**

**E-5 — Session fixation → sessão de Admin.**
`config/config.php:84-86` + `login.php:143-148` mantêm o `PHPSESSID` apresentado pela vítima e o cookie vai sem `HttpOnly`/`SameSite`. Um atacante que consiga fixar/injetar o cookie (subdomínio, XSS, link com `PHPSESSID`) assume a sessão após o login da vítima. **Correção: F-06.**

**E-6 — CSRF → promoção a Admin e escrita financeira.**
Ausência de token em `modulos/usuarios/editar.php` (F-03), `modulos/contas/*`, `modulos/caderno_campo/**`, entre outros 53 handlers (F-09), somada à ausência de `SameSite` (F-06). **Correção: F-09.**

---

## 4. Top 10 ações de correção (risco × esforço)

| # | Ação | Risco mitigado | Esforço | Prioridade |
|---|---|---|---|---|
| 1 | Criar as rotas `recuperar-senha` e `redefinir-senha/<token>` no `index.php`, corrigir `login.php:632` (link morto) e incluir CSRF no form de redefinição (`redefinir_senha.php:99`) — **F-01** | Recuperação de acesso totalmente quebrada | Baixo (~1 h) | P0 |
| 2 | Header próprio do portal (ou `$permitir_anonimo`) em `modulos/portal/login.php:331` para liberar login CPF+senha e link mágico a anônimos — **F-02** | Portal e link mágico inutilizáveis para o público-alvo | Baixo (~1 h) | P0 |
| 3 | Em `modulos/usuarios/{editar,cadastrar}.php`: exigir `NIVEL_ADMIN`, whitelist {1,2,3}, proibir auto-promoção, aplicar CSRF — **F-03** | Escalonamento gestor→admin (explorável hoje) | Baixo (~1 h) | P0 |
| 4 | Flags de cookie (`HttpOnly`, `SameSite=Lax`, `Secure` condicional) + `session_regenerate_id(true)` no login/logout/elegação — **F-06** | Session fixation, roubo de cookie, CSRF | Baixo (~1 h) | P0 |
| 5 | Validação central de CSRF para todo `POST` (middleware no `index.php` + exceções documentadas) — **F-09** | Ações de escrita disparadas por terceiros | Baixo-médio (~3 h) | P1 |
| 6 | Remover `nicho` do critério de `hasPerfilCompleto()` ou linkar `/completar-perfil` e mover os campos para `/meu-perfil` — **F-04** | ~94% da base desviada do dashboard a cada login | Baixo (~2 h) | P1 |
| 7 | Login/reset: autenticar nas duas tabelas pelo hash válido; definir estratégia de e-mail único — **F-05** | Contas de staff (inclusive Admin) inacessíveis | Médio (~4 h) | P1 |
| 8 | `user_table` explícito em toda criação de sessão + `tabelaUsuarioSegura()` e `getCurrentUser()` falhando fechado — **F-07** | Leitura/escrita na conta errada (IDOR de identidade) | Médio (~4 h) | P1 |
| 9 | Restringir `gerenciar_vinculos` (Admin + escopo da associação + auditoria); trocar a sincronização por e-mail por FK e travar `email` em `/meu-perfil` — **F-13, F-10** | Delegação de privilégio e escrita cruzada entre contas | Médio (~6 h) | P1 |
| 10 | Limpeza de coerência: `hasPermission('admin')` em lavouras, `$_SESSION['nivel']`→`user_nivel` em `includes/header.php:76`, `user_associado_id` morto, link de Clusters no menu, alinhar `tipo` com `nivel` (remover DEFAULT 1) e `ApiAuth`×`PortalAccess` — **F-11, F-12, F-14, F-17** | Bugs silenciosos e autorização inconsistente | Baixo (~3 h) | P2 |

---

## 5. Anexo — evidências reproduzíveis (somente leitura)

**a) HTTP (anônimo, 14/09/2026 10:13):**

```
curl -i http://localhost/app/portal/login
  -> HTTP/1.1 302 Found | Location: http://localhost/app/login
     Set-Cookie: PHPSESSID=3b8c2qthgc1ef3vqfgpoq3n17r; path=/   (sem HttpOnly/SameSite/Secure)

curl -o NUL -w '%{http_code} %{redirect_url}' "http://localhost/app/portal/login?etapa=verificar_token&token=abc"
  -> 302 http://localhost/app/login          (link magico anonimo quebrado)
curl ... http://localhost/app/redefinir-senha/abc   -> 302 http://localhost/app/login
curl ... http://localhost/app/recuperar-senha       -> 302 http://localhost/app/login
curl ... http://localhost/app/portal/area           -> 302 http://localhost/app/portal/login
```

**b) SQL (somente leitura, base sisagro):**

```sql
-- 4 e-mails presentes em associados e usuarios (F-05)
SELECT u.id, u.nivel, u.status, a.id, a.nivel, a.status, a.email
FROM usuarios u JOIN associados a ON a.email = u.email;
-- uid univ utipo  aid aniv astatus email
--   7    1 admin   13    1 ativo   techostin@gmail.com
--  12    3 usuario 29    3 ativo   getulio@gmail.com
--  14    3 usuario 31    3 ativo   alderi@gmail.com
--  11    3 usuario 28    3 ativo   roseleiporto@gmail.com

-- nivel efetivo divergente do tipo (F-12)
SELECT nivel, tipo, status, COUNT(*) FROM usuarios GROUP BY nivel, tipo, status;
--  1 admin ativo 3 | 1 usuario ativo 2 | 2 gestor ativo 1 | 2 usuario ativo 1 | 3 usuario ativo 5

-- FKs orfas (F-08)
SELECT a.id, a.user_id FROM associados a WHERE a.user_id IS NOT NULL;        -- 13->1, 28->11, 29->12, 30->13
SELECT u.id, u.associado_id FROM usuarios u WHERE u.associado_id IS NOT NULL; -- 7->1, 11->28, 12->29, 14->31
-- associados.id=1 e usuarios.id=1/13 NAO existem -> vinculos quebrados

-- perfil incompleto (F-04)
SELECT COUNT(*), SUM(nicho IS NOT NULL AND nicho<>'') FROM associados;  -- 34 / 2
SELECT COUNT(*), SUM(nicho IS NOT NULL AND nicho<>'') FROM usuarios;    -- 14 / 1
```

**c) Simulação de estado de sessão (PHP CLI, código atual em 10:20):**

```php
require 'classes/PortalAccess.php';
PortalAccess::usuarioVinculadoAoAssociado(13); // -> null      (nao eleva sessao)
PortalAccess::usuarioVinculadoAoAssociado(28); // -> usuarios.id=11, nivel=3
PortalAccess::usuarioVinculadoAoAssociado(29); // -> usuarios.id=12, nivel=3
```

No snapshot anterior (10:14) o mesmo teste, com o casamento por e-mail, produzia para `associado_id=13` + `user_id=7` (usuarios nível 1): `hasPermission(NIVEL_ADMIN)=true`, `podeAcessarDiretoria=true`, `isDiretoriaExecutiva=true`, `bloqueioAtivo=false`, `getCurrentUser=false` (F-07/E-3).

**d) Não confirmado / limitações:** (1) não houve login autenticado real — os fluxos pós-login foram inferidos por código + simulação de sessão; (2) não é possível verificar se as senhas em texto claro de `associados` e `usuarios` com mesmo e-mail coincidem (F-05); (3) a lista de 53 handlers sem CSRF foi obtida por grep da string `CSRFProtection`, não por inspeção individual; (4) o código mudou durante a revisão (ver seção 0) — revalidar antes de aplicar correções.

---

### Conclusão

O sistema tem hoje um núcleo de autorização razoável (`User::hasPermission` *fail-closed*, `PortalAccess` como guarda central do portal, `CSRFProtection` correta, `ApiAuth` recém-criada), mas **o modelo de identidade continua ambíguo**: dois catálogos de conta (`associados`/`usuarios`) com e-mail não exclusivo entre si, dois modelos de sessão na mesma sessão PHP, `user_table` opcional com default perigoso e privilégio de diretoria derivado de uma tabela editável. Duas falhas funcionais impedem o objetivo do sistema hoje — **recuperação de senha (F-01)** e **Portal do Associado anônimo (F-02)** — e há um caminho imediato de escalonamento de gestor para admin (F-03). As ações 1 a 4 do Top 10 cobrem os maiores riscos com cerca de um dia de trabalho.
