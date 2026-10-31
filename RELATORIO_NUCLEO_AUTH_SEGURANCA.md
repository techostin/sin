# Relatório Técnico — NÚCLEO do Sistema SINPORG
### Autenticação · Autorização · Sessão · Segurança · Infraestrutura

**Workspace:** `C:\xampp\htdocs\app\`
**Método:** leitura integral dos arquivos do núcleo + varredura exaustiva (grep) de todo o repositório PHP para inventário de sessão, guardas de autorização e CSRF.
**Aviso de escopo:** as conclusões sobre arquivos lidos **parcialmente** estão marcadas em §0.3. Nada foi inferido sem citação de arquivo:linha.

---

## 0. Escopo efetivamente lido

### 0.1 Arquivos lidos 100% (linha 1 até a última)

| Arquivo | Linhas |
|---|---|
| `classes/User.php` | 557 |
| `classes/AcessoPendente.php` | 304 |
| `classes/PortalAccess.php` | 504 |
| `classes/ApiAuth.php` | 195 |
| `classes/CSRFProtection.php` | 111 |
| `classes/Sanitizer.php` | 200 |
| `classes/PasswordReset.php` | 121 |
| `classes/Mailer.php` | 81 |
| `classes/AuditLog.php` | 28 |
| `classes/Database.php` | 36 |
| `includes/perfil.php` | 30 |
| `includes/header.php` | 159 |
| `includes/footer.php` | 150 |
| `config/config.php` | 104 |
| `config/secrets.example.php` | 9 |

### 0.2 Arquivos adicionais lidos 100% (necessários para fechar o núcleo)

`login.php` (998), `logout.php` (12), `recuperar_senha.php` (124), `redefinir_senha.php` (133), `api/recuperar_senha.php` (75), `api/pre_cadastro.php` (166), `modulos/portal/login.php` (551), `modulos/portal/logout.php` (43), `includes/rota_segura.php` (122), `modulos/relatorios/relatorio_associados.php` (153), `config/error_config.php` (56), `.htaccess` (65), `dev-utils/.htaccess`, `includes/.htaccess`, `config/.htaccess`, `api/.htaccess`, `uploads/.htaccess`, `api/relatorios/bootstrap.php` (37), `api/relatorios/gerar.php` (49), `api/legacy/session-bridge.php` (119), `classes/Certificadora.php` (155), e o DDL de `usuarios` / `associados` em `sinporg/prisma/migrations/0_baseline/migration.sql`.

### 0.3 Arquivos lidos PARCIALMENTE (declarado)

| Arquivo | Lido | Total |
|---|---|---|
| `index.php` (roteador) | 1–120, 495–534, 862–886 + greps direcionados | 1203+ |
| `includes/menu.php` | 1–60, 290–334, 405–425 | 415 |
| `modulos/portal/area.php` | 1–90 | 840 |
| `modulos/usuarios/editar.php` | 1–218 | 267 |
| `modulos/usuarios/cadastrar.php` | 1–134 | 205 |
| `modulos/usuarios/listar.php` | 1–100 | 199 |
| `modulos/usuarios/perfil.php` | 1–100 | 227 |
| `modulos/caderno_campo/relatorios/atividades.php` | 1–30 | 242 |
| `modulos/dashboard/dashboard_diretoria.php` | 1–30 | 161 |
| `modulos/dashboard/dashboard_admin.php` | 1–45 | 100 |

**Não lidos:** `classes/FichaAdesao.php`, `classes/Associado.php`, `includes/ficha_adesao_modal.php`, `includes/lembretes.php`, `sw.js`, `assets/js/db_sync.js`. Nenhuma afirmação deste relatório depende deles.

---

## 1. Mapa de identidades

### 1.1 Duas tabelas de identidade, com vínculo 1:1 opcional

O sistema tem **duas** tabelas que representam "usuário", e o login é unificado sobre as duas.

**`usuarios`** — contas de sistema (equipe interna + contas de acesso criadas pelo pré-cadastro). DDL em `sinporg/prisma/migrations/0_baseline/migration.sql:1475-1516`:

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | INT AI PK | |
| `nome`, `sobrenome` | VARCHAR(100) | `sobrenome` NULL |
| `cpf` | VARCHAR(14) NULL | |
| `email` | VARCHAR(100) NOT NULL | **UNIQUE** — identidade de login |
| `senha` | VARCHAR(255) NOT NULL | hash via `password_hash` |
| `nivel` | INT NOT NULL **DEFAULT 1** | ATENÇÃO: default = Admin (ver §6.2) |
| `status` | ENUM('ativo','inativo','pendente') DEFAULT 'ativo' | |
| `tipo` | ENUM('admin','gestor','usuario') DEFAULT 'usuario' | minúsculo |
| `tipo_usuario` | VARCHAR(50) NULL | agricultor / produtor_agroindustria / agricultor_agroindustria / gestor |
| `orgao`, `setor`, `especialidade`, `nicho` | VARCHAR(100) | vínculo profissional / perfil |
| `associado_id` | INT NULL **UNIQUE** | lado A do vínculo 1:1 |
| `certificadora_id` | INT NULL | tenant (soft) |
| `ultimo_login`, `notificacoes_ativas`, `dias_antecedencia_evento` | | |

Colunas adicionadas depois (usadas por `AcessoPendente`, ausentes do baseline): `nivel_solicitado`, `perfil_solicitado`, `acesso_confirmado_por`, `acesso_confirmado_em`, `acesso_negado_motivo`, `area_producao`, `tem_agroindustria` — ver `sql/migracao_confirmacao_acesso.sql` e o uso em `classes/AcessoPendente.php:98-101,196-207`.

**`associados`** — o cadastro do produtor (fonte canônica de dados pessoais). DDL em `migration.sql:322-381`:

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | INT AI PK | |
| `nome`, `sobrenome` | VARCHAR(100) NOT NULL | |
| `cpf` | VARCHAR(14) NULL **UNIQUE** | CPF é a chave de login do Portal |
| `email` | VARCHAR(100) NULL **UNIQUE** | **NULLABLE** |
| `senha` | VARCHAR(255) **NULL** | pode não existir (login só por link mágico) |
| `nivel` | INT **NOT NULL, sem DEFAULT** | |
| `ativo` | BOOLEAN DEFAULT true | **flag paralela a `status`** (ver §6.3) |
| `status` | ENUM('ativo','inativo','pendente') DEFAULT 'pendente' | |
| `tipo` | ENUM('Admin','Gestor','Usuario') NULL | **capitalizado** (difere de `usuarios.tipo`) |
| `tipo_usuario` | VARCHAR(50) DEFAULT 'agricultor' | |
| `tem_agroindustria`, `area_producao` | | multi-área |
| `user_id` | INT NULL **UNIQUE** | lado B do vínculo 1:1 |
| `certificadora_id`, `tipo_certificacao`, `tipo_associado`, `status_associado`, `associacao_id`, `vinculo`, `vinculo_base`, `microrregiao_id`, `cluster_comunicacao`, `ficha_enviada`, `ficha_aprovada` | | |

**Vínculo 1:1 (bidirecional, ambos UNIQUE):** `usuarios.associado_id` ↔ `associados.user_id`. A sincronização dos dois lados é transacional em `modulos/usuarios/editar.php:149-168` e `modulos/usuarios/cadastrar.php:81-104`, com auditoria `IDENTIDADE_VINCULADA` / `IDENTIDADE_DESVINCULADA`. A resolução tolerante está em `User::getAssociadoIdResolvido()` (`classes/User.php:299-329`), cobrindo 4 formas: (1) `$_SESSION['associado_id']`, (2) `user_table === 'associados'`, (3) `usuarios.associado_id`, (4) `associados.user_id` reverso.

### 1.2 Como o login unificado funciona

**Regra:** o mesmo e-mail pode existir nas duas tabelas. As duas linhas são buscadas com `status = 'ativo'` e o hash é validado nas duas; **a conta de sistema (`usuarios`) tem precedência**, para que a linha de `associados` não esconda nem rebaixe uma conta de equipe.

- `login.php:95-111` (fluxo real)
- `classes/User.php:63-81` (`User::login()`)
- `classes/PasswordReset.php:26-38` (recuperação de senha: ordem inversa — testa `associados` primeiro)
- `modulos/portal/login.php` **não** usa e-mail: usa **CPF** (`PortalAccess::buscarAssociadoAtivoPorCpf`, `classes/PortalAccess.php:127-145`), tolerando pontuação e zeros à esquerda ausentes (`variantesCpf`, linhas 107-118).

> ATENÇÃO — **`User::login()` é código morto.** Varredura por `->login(` em todo o repositório retorna **0 chamadas**. O login efetivo é a implementação duplicada dentro de `login.php`. Consequência direta em §6.1.

### 1.3 Níveis de acesso

Definidos em `config/config.php:23-25`:

```php
define('NIVEL_ADMIN', 1);
define('NIVEL_GESTOR', 2);
define('NIVEL_USUARIO', 3);
```

**Semântica invertida: número MENOR = MAIS poder.** Documentada em `ApiAuth.php:14-15` e `AcessoPendente.php:233-235` ("conceder um número MAIOR que o solicitado significa REDUZIR privilégios").

Regra de decisão — `User::hasPermission()` (`classes/User.php:114-141`):
1. não logado ou sem `user_nivel` → `false`;
2. `user_nivel` não numérico → `false` + `error_log` (correção B-04 para o bug `'' <= 1 === true` em PHP 8, documentada nas linhas 119-123);
3. `(int)$nivel <= 0` → `false`;
4. `nivel === NIVEL_ADMIN` → `true` (acesso total);
5. senão `return $nivel <= $required_level`.

`ApiAuth` replica a mesma semântica com `nivel > nivelAdmin() && nivel > nivelNecessario` → nega (`ApiAuth.php:157-164`).

### 1.4 `tipo_usuario` e o roteamento de dashboard

Valores canônicos produzidos em `api/pre_cadastro.php:34-41`:

| perfil do formulário | `tipo_usuario` | `area_producao` |
|---|---|---|
| vegetal | `agricultor` | `vegetal` |
| animal | `agricultor` | `animal` |
| agroindustria | `produtor_agroindustria` | `agroindustria` |
| mista | `agricultor_agroindustria` | `animal,vegetal,agroindustria` |
| gestor | `gestor` (vai para `usuarios`) | vazio |

Roteamento — `includes/perfil.php:14-30` (`dashboard_destino()`), espelhado em `User::perfilDashboard()` (`classes/User.php:157-167`) e `index.php:873-877`:

- `nivel === 1` → `/dashboard/admin`
- `nivel === 2` → `/dashboard/gestor`
- `tipo_usuario` em {produtor_agroindustria, agroindustria} → `/dashboard/agroindustria`
- senão → `/dashboard/agricultor`

**Os três pontos leem `$_SESSION['user_tipo_usuario']`, que o `login.php` nunca grava** (§6.1).

### 1.5 Vínculo com certificadora (multi-tenant)

Há **três** pontos de acoplamento a `certificadoras`:

1. `certificadoras.gestor_id` → `usuarios.id` — `Certificadora::findByGestor()` (`classes/Certificadora.php:35-47`), usado para resolver a certificadora de um Gestor em `relatorios/gerar_certificado.php:33`, `api/relatorios_admin.php:210-211`, `classes/VisitaReport.php:101-105`, `classes/CertificadoOrganico.php:129-131` — **sempre como fallback de logo/branding**.
2. `usuarios.certificadora_id` — exposto no JWT da ponte Node (`api/legacy/session-bridge.php:104`).
3. `associados.certificadora_id` — idem (`session-bridge.php:86`), mais `tipo_certificacao` ENUM('OCS','SPG','AUDITORIA').

> ATENÇÃO — **Não existe isolamento multi-tenant efetivo.** Buscas por `certificadora_id = ?` em todo o repositório retornam **1 ocorrência**, e ela é de um script de migração (`dev-utils/migrate_certificadora_logos.php:20`). **Não há chave de sessão de certificadora** (grep por `$_SESSION[...certificadora...]` = 0 resultados) e nenhuma query de listagem é filtrada por ela. O "tenant" é hoje **atribuição de marca** (qual logo/nome renderizar), **não fronteira de dados**.

---

## 2. Fluxo de autenticação completo

### 2.1 Configuração de sessão (global, ocorre no `require config.php`)

`config/config.php:88-103`:

```php
if (session_status() == PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        @ini_set('session.use_strict_mode', '1');   // anti fixação
        @ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,          // expira com o navegador
            'path'     => '/',
            'secure'   => $isHttps,   // auto-detectado
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}
```

Como `config.php` é `require`-ido por praticamente todo entry point (`login.php:2`, `logout.php:2`, `index.php:2`, `includes/header.php:2`, `classes/Database.php:2`, `classes/CSRFProtection.php:2`), **a sessão é iniciada sempre**, inclusive em páginas públicas. Não há `session.gc_maxlifetime` customizado, **não há timeout por inatividade** e **não há expiração absoluta** — apenas o `lifetime = 0` do cookie.

### 2.2 `login.php` — Etapa 1 (credenciais)

Bloco: `login.php:78-221`. Dispara quando `REQUEST_METHOD === 'POST'` e `(!isset($_POST['etapa']) || $_POST['etapa'] !== '2')`.

1. **Saneamento de tipo** (79-82): `email` e `senha` só são aceitos se `is_string()` — proteção contra `senha[]=x`, que causaria `TypeError` em `password_verify()` e HTTP 500.
2. Validação de vazio (84-85) → "Informe o e-mail e a senha."
3. **Consulta dupla** (95-101): `SELECT * FROM usuarios WHERE email = :email AND status = 'ativo'` e o equivalente em `associados`.
4. **Verificação de hash com precedência** (103-111): `usuarios` primeiro; `elseif` `associados`. Sempre `password_verify()`, exigindo `!empty($row['senha'])`.
5. Falha → mensagem genérica "E-mail ou senha inválidos!" (214). **Nenhum registro de auditoria e nenhum contador de tentativas** (§6.5).
6. **`session_regenerate_id(true)` na linha 115** — anti fixação de sessão.
7. Ramo **Gestor** (`(int)$userData['nivel'] === NIVEL_GESTOR`, linha 118):
   - se `orgao` **e** `especialidade` **e** `setor` preenchidos → **login direto** (138-155): grava 7 chaves + `mostrar_modal_eventos`; depois `hasPerfilCompleto()` decide entre `/meu-perfil` e `/`;
   - senão → **Etapa 2 obrigatória**: grava `$_SESSION['login_temp']` com `{user_id, user_nivel, user_nome, user_tipo='gestor', user_table, email}` (126-133), marca `['primeiro_acesso'] = true` (134), `$showStep2 = true` (135).
8. Ramo **Admin / usuário comum** (156-211):
   - grava `user_id`, `user_nivel`, `user_nome`, `user_tipo`, `user_table`, `mostrar_modal_eventos` (158-163);
   - **ficha de adesão** (175-185): se `user_table === 'usuarios'` e `nivel === NIVEL_USUARIO` e `associado_id` vazio e `FichaAdesao::associadoIdDoUsuario() === 0` → redireciona para `/caderno-campo/dashboard-agricultor` (o modal global em `includes/header.php:155-158` oferece a ficha);
   - `primeiro_acesso` (188-191): `empty(telefone) && empty(cpf)`;
   - `hasPerfilCompleto()` falso → `/meu-perfil` (194-198);
   - `isPrimeiroAcesso` e não-Admin → `/meu-perfil` (201-204);
   - `NIVEL_USUARIO` → `/caderno-campo/dashboard-agricultor`; senão `/` (206-210).

> **`user_orgao` / `user_setor` / `user_especialidade` NÃO são gravados no login direto de Admin/Usuário** — apenas no ramo Gestor.
> **Chaves exatas gravadas na Etapa 1, ramo Admin/usuário:** `user_id`, `user_nivel`, `user_nome`, `user_tipo`, `user_table`, `mostrar_modal_eventos`. **Não inclui `user_tipo_usuario` nem `user_area_producao`.**

### 2.3 `login.php` — Etapa 2 (vínculo profissional do Gestor)

Bloco: `login.php:20-75`. Condição: `$_POST['etapa'] === '2' && isset($_SESSION['login_temp'])`.

1. Lê `orgao`, `especialidade`, `setor` via `trim($_POST[...] ?? '')` (22-24). Exige `orgao` e `especialidade` não vazios (`setor` é opcional).
2. `$table = $temp['user_table']` e `UPDATE {$table} SET orgao = :orgao, setor = :setor, especialidade = :especialidade WHERE id = :id` (30-40) — **interpolação direta de nome de tabela**, mas o valor vem de `login_temp` gravado pelo servidor (não é controlável pelo cliente). Ainda assim é a mesma classe de risco que o projeto fechou em `User::tabelaUsuarioSegura()`.
3. Grava `user_id`, `user_nivel`, `user_nome`, `user_tipo`, `user_table`, `user_orgao`, `user_setor`, `user_especialidade`, `mostrar_modal_eventos` (42-50).
4. Se `$temp['primeiro_acesso']` → `$_SESSION['primeiro_acesso'] = true` (53-56).
5. `unset($_SESSION['login_temp'])` (58).
6. **`session_regenerate_id(true)` na linha 61** (2ª regeneração da mesma autenticação — a 1ª foi na linha 115).
7. Redireciona para `/meu-perfil` (primeiro acesso) ou `/` (64-68).
8. Em exceção: mensagem genérica **+ `unset($_SESSION['login_temp'])`** (70-73) — o usuário perde a Etapa 2 e precisa logar de novo.

### 2.4 Login do Portal do Associado (`modulos/portal/login.php`)

Página **pública por declaração explícita**: `$PERMITIR_ACESSO_ANONIMO = true;` na linha 344, antes do `require includes/header.php` (345).

Pré-condições:
- `session_start()` idempotente (25-27); se já há `associado_id` → `/portal/area` (30-33);
- se há sessão principal, calcula `$isAdminOrDiretoriaExecutiva = PortalAccess::ehPrivilegiado($user)` (43).

**Todo POST passa por CSRF** (linha 56: `if (!CSRFProtection::validatePostRequest())` → mensagem de erro e nenhuma ação executada).

Quatro ações (`$_POST['acao']`):

| Ação | Linha | Comportamento |
|---|---|---|
| `consultar_cpf` | 107-137 | **só** se `$isAdminOrDiretoriaExecutiva`. Busca tolerante por CPF; `session_regenerate_id(true)` (120); grava `associado_id/nome/cpf/email` + `portal_acesso_via = 'consulta_admin'` + `portal_acesso_por = $_SESSION['user_id']` (121-126); audita `PORTAL_ACESSO_PRIVILEGIADO` (128). **Mantém a sessão principal.** |
| `login_cpf` | 140-186 | CPF + `password_verify` contra `associados.senha` (153). Sucesso: `limparTentativas()` (154), `session_regenerate_id(true)` (157), grava `associado_*` + `portal_acesso_via = 'login_comum'` (160-164), **vincula sessão principal apenas se houver vínculo REAL** (169-174, via `usuarioVinculadoAoAssociado`), audita `PORTAL_LOGIN` (176). Falha: `registrarTentativa()` (181). |
| `solicitar_magic_link` | 187-261 | `registrarTentativa()` (198); resolve e-mail de acesso (`emailDeAcessoDoAssociado`, 205-207); `PasswordReset::createToken` (212) → SHA-256 no banco; link `SITE_URL/portal/login?etapa=verificar_token&token=...` (213); envia por `Mailer` (216-227); audita `PORTAL_MAGIC_LINK` (232-237); **só revela o link na tela se `PortalAccess::ehTitularDoCpf()`** (239-248), senão mensagem genérica (251). |
| `verificar_token` | 262-317 | `PasswordReset::getValidToken` (271) → resolve associado por e-mail (276-279) → **`markUsed` atômico** (280-283, `UPDATE ... WHERE id = ? AND used = 0`); sucesso: `session_regenerate_id(true)` (288), grava `associado_*` + `portal_acesso_via = 'magic_link'` (291-295), vínculo real (300-305), audita `PORTAL_LOGIN_MAGIC_LINK` (307). |

**Bloqueio central antes de qualquer ação** (71-101): se `PortalAccess::bloqueioAtivo($user)` (autenticado **e** não privilegiado), calcula o alvo (`cpf_consulta`, `cpf` ou o titular do token) e chama `podeAcessarCpf` / `podeAcessarAssociadoId`. Negado → `MSG_ACESSO_NEGADO`, `$acao = 'bloqueado'` e auditoria `PORTAL_ACESSO_NEGADO`.

**Anti abuso:** `PortalAccess::tentativasRestantes() <= 0` (104) — janela de 900 s, máximo 10 tentativas, **contador por sessão** (`$_SESSION['portal_tentativas']`), não por IP.

> Divergência de UX: a UI diz "O link expira em 30 minutos" (linha 438), mas `PasswordReset::createToken` grava `time() + 3600` — **60 minutos** (`classes/PasswordReset.php:55`).

### 2.5 Sessão do Portal — chaves e ciclo

Gravadas em `modulos/portal/login.php`: `associado_id`, `associado_nome`, `associado_cpf`, `associado_email`, `portal_acesso_via` (e `portal_acesso_por` somente em `consulta_admin`). `modulos/portal/area.php:141` ainda regrava `associado_email` quando o associado altera o próprio e-mail.

### 2.6 Regeneração de ID de sessão — inventário completo (7 ocorrências reais)

| Arquivo:linha | Contexto |
|---|---|
| `login.php:61` | conclusão da Etapa 2 (Gestor) |
| `login.php:115` | autenticação da Etapa 1 |
| `classes/User.php:89` | `User::login()` — **código morto** |
| `modulos/portal/login.php:120` | `consultar_cpf` (Admin/Diretoria assume o portal de outro) |
| `modulos/portal/login.php:157` | `login_cpf` |
| `modulos/portal/login.php:288` | `verificar_token` (link mágico) |
| `modulos/portal/logout.php:22` | logout do portal (com supressão de erro e `function_exists`) |

Não há regeneração ao **elevar privilégio dentro da sessão** — `modulos/portal/login.php:171-173` injeta `user_id` / `user_nivel` / `user_table` na sessão principal a partir da sessão do portal, mas as linhas 157/288 já regeneraram antes, então o ponto está coberto.

### 2.7 Logout

**Principal** — `logout.php:1-12`:

```php
$user = new User();
AuditLog::registrar('LOGOUT', null, null, 'Usuário realizou logout');
$user->logout();                       // classes/User.php:110-112 -> session_destroy()
header("Location: " . SITE_URL . "/login");
```

- `User::logout()` chama **apenas `session_destroy()`** (`classes/User.php:110-112`): **não** limpa `$_SESSION`, **não** expira o cookie de sessão (`setcookie` com `expires` no passado) e **não** regenera o ID.
- É **GET** — logout por CSRF é possível (severidade baixa).

**Portal** — `modulos/portal/logout.php:1-43`: detecta `portal_acesso_via === 'consulta_admin' && !empty($_SESSION['user_id'])` (17-18) para **preservar a sessão principal**; `session_regenerate_id(true)` (20-23); `unset()` explícito de `associado_id`, `associado_nome`, `associado_cpf`, `associado_email`, `portal_acesso_via`, `portal_acesso_por` (26-33); se mantém sessão principal → `/`, senão `session_destroy()` → `/portal/login` (35-43). **Este é o logout correto do sistema.**

### 2.8 Recuperação e redefinição de senha

- `recuperar_senha.php` (124 linhas) — só a view; o submit é `fetch` para `api/recuperar_senha.php` (`recuperar_senha.php:103-107`) **sem token CSRF**.
- `api/recuperar_senha.php` — apenas POST (16-20); valida `FILTER_VALIDATE_EMAIL` (26); **responde sempre a mesma mensagem genérica**, exista ou não a conta (24, 36-39); cria token (41) e envia `SITE_URL/redefinir-senha/{token}` (42); em falha de envio registra o link em `error_log` (58) — **o link de reset vai para o log do servidor**.
- Roteamento: `index.php:19-27` mapeia `/recuperar-senha` e `/redefinir-senha/{token}`.
- `redefinir_senha.php` (133 linhas) — valida o token via `getValidToken` (17); no POST exige `strlen >= 6` e confirmação (26-29); `updatePassword` (35) + `markUsed` (36) + auditoria `SENHA_RESET` (37). **Sem CSRF no formulário** (o token na URL funciona como segredo).
- `PasswordReset` — token `bin2hex(random_bytes(32))` (64 hex), gravado como `hash('sha256')` (`classes/PasswordReset.php:53-54`); invalida tokens anteriores do mesmo e-mail (58-59); `markUsed` atômico (95-97); `updatePassword` com whitelist `['associados','usuarios']` (106).

---

## 3. Fluxo de autorização

### 3.1 Não existe middleware central

Não há `requireLogin()`, `isGestor()` global nem guarda de rota. A varredura por `function (requireLogin|isLoggedIn|isGestor|isAdmin|exigirLogin|verificarLogin|checarNivel)` retorna **2 resultados**, ambos métodos de classe: `ApiAuth::exigirLogin()` e `User::isLoggedIn()`. **Não há função helper global.** Convivem **quatro** mecanismos:

**(A) `includes/header.php:5-19` — autenticação obrigatória por inclusão**

```php
$user = new User();
$permitirAnonimo = !empty($PERMITIR_ACESSO_ANONIMO);
$permitirPortal  = !empty($PERMITIR_SESSAO_PORTAL) && !empty($_SESSION['associado_id']);
if (!$user->isLoggedIn() && !$permitirAnonimo && !$permitirPortal) {
    header("Location: " . SITE_URL . "/login"); exit;
}
```

Isto é **autenticação**, não autorização. Qualquer página que inclua o header passa a exigir login, independentemente de nível. As exceções (`$PERMITIR_ACESSO_ANONIMO`, `$PERMITIR_SESSAO_PORTAL`) são **declaradas pela própria página antes do include** — a decisão de ser pública é da página, não do núcleo. Único uso de `$PERMITIR_ACESSO_ANONIMO` localizado: `modulos/portal/login.php:344`.

**(B) `User::hasPermission()` e comparação direta de `$_SESSION['user_nivel']`**
Há **185 ocorrências de `hasPermission(`** em 90+ arquivos. Mas há também **44 comparações diretas** de `$_SESSION['user_nivel']` com operadores **frouxos**:

```
login.php:11                                    $_SESSION['user_nivel'] == NIVEL_USUARIO
api/certificacao/controller.php:144,174,201     $_SESSION['user_nivel'] == NIVEL_USUARIO
modulos/usuarios/listar.php:18,85               == NIVEL_USUARIO / == NIVEL_ADMIN
modulos/associados/excluir.php:11               $_SESSION['user_nivel'] != NIVEL_ADMIN
modulos/associados/editar.php:23                ($_SESSION['user_nivel'] != NIVEL_ADMIN) || isset($_GET['view'])
modulos/associados/listar.php:201               == NIVEL_ADMIN
modulos/assembleia/{visualizar,resultado,publicar,pautas,encerrar,editar}.php   <= 2   (literal)
modulos/certificacao/certificado.php:62         == NIVEL_USUARIO
modulos/certificacao/cadastrar.php:58           == NIVEL_GESTOR
modulos/caderno_campo/relatorios/atividades.php:74            != 1 && != 2
modulos/caderno_campo/relatorios/atividades_excel.php:66      != 1 && != 2
modulos/caderno_campo/relatorios/form_atividades.php:32,100,147,185   == 1 || == 2
modulos/caderno_campo/propriedades/{editar,listar,historico_conversao,visualizar_imagens}.php  == NIVEL_USUARIO
modulos/caderno_campo/atividade_diaria/{editar,visualizar_imagens}.php  == NIVEL_USUARIO
```

Os literais `<= 2`, `== 1`, `!= 2` (`modulos/assembleia/*`, `modulos/caderno_campo/relatorios/*`) são **hard-coded** e não acompanham a constante. Nenhum deles passa pela canonicalização numérica do `hasPermission()` — são vulneráveis à mesma classe de bug que motivou a correção B-04.

**(C) `ApiAuth` — guarda para APIs/AJAX** (`classes/ApiAuth.php`)
`nivel()` (50-68) deriva o nível de `$_SESSION['user_id']` / `user_nivel` e **também aceita a sessão do Portal**, atribuindo-lhe `NIVEL_USUARIO` (nunca elevado) — linhas 63-65. `ehStaff()` (`<= NIVEL_GESTOR`), `exigirNivel()`, `exigirStaff()`, `exigirProprioAssociado()`, `negar()` (encerra com `exit`).

**Cobertura em `api/` (56 arquivos):**

| Situação | Qtd | Exemplos |
|---|---|---|
| com alguma checagem | 47 | `api/plano_manejo.php`, `api/ocorrencias.php`, `api/relatorios/admin_export.php:29` (`hasPermission(NIVEL_GESTOR)`) |
| **sem nenhuma checagem** | **9** | `api/get_cidades.php`, `api/get_estado_cidade.php`, `api/pre_cadastro.php`, `api/recuperar_senha.php`, `api/pix/webhook/{asaas,gerencianet,mercadopago}.php`, `api/relatorios/gerar.php`*, `api/relatorios/_config.php`* |

`*` `api/relatorios/gerar.php:3-6` inclui `bootstrap.php` e chama `verificarAutenticacao()` (definida em `bootstrap.php:31-37`, que checa apenas `isset($_SESSION['user_id'])`) — logo **está autenticado**, só não usa `hasPermission`. Os 5 primeiros são públicos por desenho (CEP, pré-cadastro, reset e webhooks PIX — cuja verificação de assinatura própria **não foi auditada** neste relatório).

**Cobertura em `modulos/` (220 arquivos):** apenas **11** não possuem *nenhuma* guarda (nem header/menu, nem leitura de sessão, nem `hasPermission`):

```
modulos/agroindustria/rastreabilidade.php          (público POR DESENHO — comentário linha 2)
modulos/producao_animal/rastreabilidade.php        (público POR DESENHO — comentário linha 2)
modulos/caderno_campo/validar_insumo.php
modulos/caderno_campo/lotes_producao/debug_qr.php
modulos/caderno_campo/lotes_producao/teste_qr.php
modulos/caderno_campo/lotes_producao/teste_qrcodejs.php
modulos/caderno_campo/relatorios/exportar_relatorio.php   (apenas dispatcher; o include de destino valida)
modulos/certificacao/editar.php                    (require de cadastrar.php, que valida)
modulos/relatorios/relatorio_associados.php        <<< CRÍTICO — ver §6.4
modulos/roteiros_inspecao/templates/certificado.php
modulos/roteiros_inspecao/templates/declaracao_compromisso.php
```

**(D) `includes/rota_segura.php` + whitelists** — defesa B-02 contra LFI no roteador. `ROTAS_API_RELATORIOS_PERMITIDAS` (30-52, 19 slugs) e `ROTAS_PIX_WEBHOOK_PERMITIDAS` (57-63); `rota_arquivo_seguro()` (74-105) aplica 3 camadas: formato (`^[A-Za-z0-9][A-Za-z0-9_-]*$`, até 64 chars), whitelist estrita e confinamento por `realpath` prefixado.

> **Nota estrutural:** não existe `.htaccess` em `modulos/`. Portanto `https://host/app/modulos/.../arquivo.php` é acessível **diretamente, sem passar pelo `index.php`** — qualquer guarda que venha a ser colocada no roteador não cobre esses acessos.

### 3.2 Verificação de ownership (IDOR)

**O que está correto:**
- `PortalAccess` é a *single source of truth* do Portal (`classes/PortalAccess.php:3-17`). Decisão: `podeAcessarCpf` (391-402) e `podeAcessarAssociadoId` (405-416) retornam `true` para (a) visitante anônimo, (b) Admin/Diretoria Executiva, (c) o próprio titular (CPF normalizado comparado com `hash_equals`, linha 352); `false` em qualquer outro caso. `bloqueioAtivo` (379-383) = autenticado **e** não privilegiado. `ehPrivilegiado` (295-302) = `hasPermission(NIVEL_ADMIN) || isDiretoriaExecutiva()`.
- `ApiAuth::exigirProprioAssociado()` (170-183) — mesma política, nega `403` se `$alvo !== $meu` (ou `<= 0`).
- Páginas com checagem pontual explícita, ex.: `modulos/manejo_animal/animais_visualizar.php:27`: `if (!$user->hasPermission(NIVEL_GESTOR) && (int)$animal['associado_id'] !== (int)$user->getAssociadoId())`.
- Auditoria de tentativas negadas e de acessos privilegiados: `PortalAccess::registrarAcessoNegado` (429-441) e `registrarAcessoPrivilegiado` (444-455), com CPF mascarado (`mascararCpf`, 80-87).

**O que está falho — o padrão sistêmico "null = todos":**

21 páginas usam este idioma de escopo:

```php
$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$registros = $classe->getAll($escopo);
```

E os `getAll($filtroAssociado = null)` correspondentes só filtram **se o valor for truthy**:

```php
// classes/ManejoAnimal.php:13-28  (idêntico em AgroAtividadeDiaria.php:12-32 e outros)
public function getAll($filtroAssociado = null) {
    $sql = "SELECT ... WHERE a.excluido = 0";
    $params = [];
    if ($filtroAssociado) {                  // <-- null OU 0 => SEM FILTRO
        $sql .= " AND a.associado_id = ?";
        $params[] = (int)$filtroAssociado;
    }
    ...
}
```

`User::getAssociadoId()` (`classes/User.php:259-272`) retorna **`null`** quando o usuário logado é da tabela `usuarios` **sem** `associado_id` e sem `associados.user_id` apontando para ele. É exatamente o estado de **toda conta criada por `api/pre_cadastro.php` antes da aprovação da Ficha de Adesão** (`pre_cadastro.php:128-153` insere em `usuarios` sem criar o registro em `associados`), e de contas de equipe sem vínculo. Nesses casos `$escopo = null` → **`getAll()` sem filtro → o usuário de nível 3 vê os dados de todos os produtores**.

As 21 páginas afetadas: `agroindustria/{setores,receitas,higienizacao,atividades,ordens,ocorrencias}_listar.php`, `agroindustria/ordens_cadastrar.php`, `manejo_animal/animais_{listar,cadastrar,editar}.php`, `producao_animal/{ambientes,lotes,alimentacao,reproducao,sanidade}_{listar,cadastrar,editar}.php`.

> Este é o achado de autorização mais importante do núcleo: **o default do modelo de escopo é "todos", não "nenhum"**.

**Outros pontos de IDOR/ownership (amostragem):**
- `modulos/relatorios/relatorio_associados.php` — sem nenhuma checagem (§6.4).
- `modulos/usuarios/editar.php:13` usa `$_GET['id']` cru (sem `decodeId`), mitigado por papel: linhas 40-48 bloqueiam Gestor para alvos `nivel <= 2` ou auto-edição; linha 89 limita `$niveisPermitidos`.
- `modulos/associados/excluir.php:11` — `!= NIVEL_ADMIN` + CSRF (`validatePostRequest`, linha 18). Funcionalmente OK.
- `index.php:95-119` passa **IDs numéricos crus** aos módulos (`is_numeric($parts[1])` → `$_GET['id'] = $parts[1]`). `decodeId()` existe em `config/config.php:68-81`, mas é usado em **apenas 13 pontos**, concentrados em `modulos/associados/ficha.php` (5) e `index.php` (4). **A maioria das URLs trafega IDs sequenciais em claro** — a "criptografia de IDs" é a exceção, não a regra.

### 3.3 Autorização no menu é apenas cosmética

`includes/menu.php` decide o que renderizar com `$user->hasPermission(NIVEL_ADMIN)` (46, 112, 217), `hasPermission(NIVEL_GESTOR)` (103, 193, 213, 302, 415), `podeAcessarDiretoria()` (20), `isDiretoriaExecutiva()` (46). É **exibição condicional** — não impede acesso direto à URL. Depende de `$user` já estar definido (por `includes/header.php:5`).

---

## 4. Modelo de segurança

### 4.1 CSRF

**Geração** — `classes/CSRFProtection.php`:
- Nome do campo/sessão: constante `CSRF_TOKEN_NAME = 'csrf_token'` (`config/config.php:47`).
- `generateToken()` (6-13): `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))` — **256 bits CSPRNG, gerado uma única vez por sessão** (não rotaciona por formulário, não expira).
- `getTokenField()` (15-22): `<input type="hidden" name="csrf_token" value="...">`, com `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

**Validação** — dois caminhos:
- `validatePostRequest()` (24-38): exige `REQUEST_METHOD === 'POST'`, ambas as strings não vazias e `hash_equals($sessionToken, $postedToken)` (**comparação em tempo constante**).
- `validateToken($token)` (57-69): valida token arbitrário (uso em APIs com header).
- `tokenFromRequest()` (77-109): lê de `HTTP_X_CSRF_TOKEN` → `getallheaders()['X-CSRF-Token']` → `$_POST` → `$_GET` → **corpo JSON** (`json_decode(file_get_contents('php://input'))`).

**Injeção no cliente** — `includes/header.php:99-131`: expõe `window.CSRF_TOKEN` (102) e faz **monkey-patch de `window.fetch`** (110-131) injetando `X-CSRF-Token` automaticamente em `POST/PUT/DELETE/PATCH`. Consequência perversa: **o cliente sempre envia o token, o que mascara no front-end quais endpoints o servidor realmente valida**.

**Cobertura medida em `modulos/` + `api/` (86 handlers de POST identificados):**

- **39 (45%)** referenciam `CSRFProtection` / `validatePostRequest`.
- **47 (55%)** **não têm nenhuma validação CSRF**. Lista completa:

```
agroindustria/{atividades_cadastrar,atividades_editar,categorias_cadastrar,categorias_editar,
               higienizacao_cadastrar,ocorrencias_cadastrar,ocorrencias_editar,ordens_cadastrar,
               receitas_cadastrar,receitas_editar,setores_cadastrar,setores_editar}.php
associacoes/{cadastrar,editar}.php
caderno_campo/relatorios_admin.php, caderno_campo/relatorios_tabs.php
caderno_campo/categorias_atividades/{cadastrar,editar}.php
caderno_campo/insumos/{cadastrar,editar}.php
caderno_campo/lavouras/{cadastrar,editar}.php
caderno_campo/lotes_producao/editar.php
caderno_campo/produtos/editar.php
caderno_campo/propriedades/{cadastrar,editar,historico_conversao}.php
certificacao/certificado.php
contas/{cadastrar,editar}.php
eventos/editar.php
manejo_animal/animais_{cadastrar,editar,visualizar}.php
producao_animal/{alimentacao,ambientes,lotes,reproducao,sanidade}_{cadastrar,editar}.php
usuarios/configuracoes.php, usuarios/perfil.php, usuarios/salvar_notificacoes.php
```

Destaques de impacto: `modulos/usuarios/perfil.php:57-93` (**altera nome, e-mail de login e senha do próprio usuário sem token**), `modulos/associacoes/{cadastrar,editar}.php`, e toda a família `producao_animal/*` / `manejo_animal/*`.

**CSRF nos endpoints `api/` (13 handlers de POST):**

| CSRF | Auth | Arquivo |
|---|---|---|
| sim | sim | `api/certificadoras.php` |
| sim | sim | `api/ficha_adesao.php` |
| sim | sim | `api/nao_conformidades.php` |
| sim | sim | `api/ocorrencias.php` |
| sim | sim | `api/plano_manejo.php` |
| sim | sim | `api/roteiros_inspecao.php` |
| sim | sim | `api/toggle_presenca.php` |
| sim | sim | `api/certificacao/controller.php` |
| nao | sim | `api/get_eventos_pendentes.php` |
| nao | sim | `api/relatorios_controller.php` |
| nao | sim | `api/certificadoras/controller.php` |
| nao | nao | `api/pre_cadastro.php` (público — esperado) |
| nao | nao | `api/recuperar_senha.php` (público — dispara e-mail, vetor de spam) |

### 4.2 Sanitização (`classes/Sanitizer.php`, 200 linhas, 14 métodos estáticos)

| Método | Linha | Comportamento |
|---|---|---|
| `sanitizeString` | 15-21 | `strip_tags` → `trim` → remove `[\x00-\x1F\x7F]` |
| `sanitizeInt` | 28-30 | `(int) filter_var(FILTER_SANITIZE_NUMBER_INT)` |
| `sanitizeFloat` | 37-39 | `FILTER_SANITIZE_NUMBER_FLOAT` + `ALLOW_FRACTION` |
| `sanitizeEmail` / `validateEmail` | 46-57 | `FILTER_SANITIZE_EMAIL` / `FILTER_VALIDATE_EMAIL` |
| `sanitizeMoney` | 73-94 | Detecta formato BR (vírgula), centavos da máscara ("5850" → 58.50) ou decimal com ponto |
| `sanitizeUrl` / `validateUrl` | 101-112 | `FILTER_SANITIZE_URL` / `FILTER_VALIDATE_URL` |
| `sanitizeArray` | 119-131 | Recursivo; só strings/escalares passam por `sanitizeString` |
| `sanitizeName` | 142-158 | Espaços normalizados, mantém letras/acentos/números e apóstrofo-hífen-ponto, colapsa `..` e `--`, Title Case (mbstring) |
| `sanitizePhone` | 163-167 | Apenas dígitos |
| `sanitizeCpfCnpj` | 172-179 | 11 ou 14 dígitos, senão vazio |
| `sanitizeCPF` | 185-189 | Wrapper de compatibilidade: só 11 dígitos |
| `sanitizeAddress` | 194-199 | `strip_tags` + normaliza espaços |

**Observações:** `sanitizeString` usa `strip_tags` — **não escapa HTML**. A defesa de saída depende de `htmlspecialchars` caso a caso. Não há sanitização automática global de `$_POST` / `$_GET` (nenhum `array_walk` sobre superglobais no núcleo).

### 4.3 Prepared statements versus interpolação

O padrão dominante é PDO com prepared statements (`Database.php:10-19` configura `ERRMODE_EXCEPTION`, `DEFAULT_FETCH_MODE = FETCH_ASSOC`, `utf8mb4`). As interpolações existentes são, com exceção verificada, **controladas**:

| Local | Interpolado | Avaliação |
|---|---|---|
| `classes/User.php:245,479,547` | `{$table}` | protegido por `tabelaUsuarioSegura()` (`TABELAS_USUARIO = ['associados','usuarios']`, linhas 13, 31-52) |
| `classes/PasswordReset.php:110` | `{$tabela}` | whitelist inline `in_array($tabela, ['associados','usuarios'], true)` (linha 106) |
| `classes/PortalAccess.php:98` | `{$coluna}` | validado por regex `^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$` (linhas 95-97) |
| `modulos/usuarios/perfil.php:27-29,31,38,74,84` | `{$user_table}` | `in_array(..., ['usuarios','associados'], true)` antes de usar |
| `login.php:34` | `{$table}` | vem de `$_SESSION['login_temp']` (server-side) — **sem whitelist** |
| `api/get_presenca_evento.php:61,72`, `modulos/eventos/acompanhar_presenca.php:54,65`, `modulos/assembleia/visualizar.php:55` | `IN ($placeholders)` | `$placeholders` = `implode(',', array_fill(0, count($ids), '?'))` |
| `api/relatorios/*.php`, `classes/ReportManager.php:551` | `$whereClause` | construído por concatenação **a partir de filtros** — requer auditoria caso a caso (não auditado aqui) |

**Proteção de tabela de usuário** (`User::tabelaUsuarioSegura`, `classes/User.php:31-52`) é a defesa B-01: normaliza, valida contra `TABELAS_USUARIO`, registra em `error_log` a tentativa inválida e cai no padrão `'associados'`. Deve ser sempre chamada antes de interpolar `$_SESSION['user_table']`.

### 4.4 Criptografia de IDs (`encodeId` / `decodeId`)

`config/config.php:55-81`:

```php
function encodeId($id) {
    $data = (string)(int)$id;
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', ID_ENCODE_KEY, 0, $iv);
    return rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');
}
function decodeId($encoded) { /* base64url -> IV(16) + ciphertext -> AES-256-CBC -> ctype_digit */ }
```

Avaliação técnica:
- **Positivo:** AES-256-CBC com IV aleatório por chamada (`random_bytes`), remove `=`, retorna `0` em qualquer falha e valida `ctype_digit` na saída (77).
- **Frágil:** modo **CBC sem autenticação (sem HMAC/AEAD)**. `decodeId` não detecta adulteração do ciphertext — padding inválido retorna `false`, mas manipulação de blocos pode produzir plaintexts válidos. Para IDs de 1 a 6 dígitos o espaço é trivialmente enumerável **se a chave for conhecida** (e ela é, por padrão — ver abaixo).
- **Chave padrão insegura:** `ID_ENCODE_KEY` cai em `'troque-esta-chave-em-producao'` quando `config/secrets.php` e a env var `ID_ENCODE_KEY` não existem (`config/config.php:51-53`). `config/secrets.example.php:9` apenas sugere `'gere-uma-chave-aleatoria-longa'`.
- **Uso marginal:** `decodeId(` aparece em apenas **13 pontos** no repositório inteiro (5 em `modulos/associados/ficha.php`, 4 em `index.php`, 1 em `modulos/caderno_campo/propriedades/cadastrar.php`, 1 em `modulos/associados/ficha_adesao_visualizar.php`). A maioria das rotas do `index.php` usa números crus. **Não há obscuridade alguma na maior parte do sistema.**

### 4.5 Hashing de senha

- **Algoritmo:** `password_hash($senha, PASSWORD_DEFAULT)` — 8 ocorrências: `classes/Associado.php` (2), `api/pre_cadastro.php:103`, `classes/PasswordReset.php:108`, `modulos/associados/cadastrar.php`, `modulos/usuarios/perfil.php:73`, `modulos/usuarios/editar.php:137`, `modulos/usuarios/cadastrar.php:92`.
- **Verificação:** `password_verify()` — 8 ocorrências: `login.php` (3), `classes/User.php` (2), `modulos/portal/login.php` (2), `modulos/usuarios/perfil.php:68`.
- **`md5` / `sha1` para senha:** **nenhum**. A única ocorrência é `dev-utils/migrate_visita_hashid.php` (hash de identificador de visita, não senha).
- Comparações de segredos/tokens usam `hash_equals` (`CSRFProtection:37,68`; `PortalAccess:352`).
- **Política de senha:** mínimo de **6 caracteres**, verificado no cliente (`login.php:921`) e no servidor (`api/pre_cadastro.php:75`, `redefinir_senha.php:26`). **Não há** verificação de complexidade, de senhas vazadas nem `password_needs_rehash`. Não há migração de hash em login.

### 4.6 Auditoria (`classes/AuditLog.php`, 28 linhas)

`AuditLog::registrar($acao, $tabela, $registro_id, $descricao)` (13-27) faz `INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip)`, com `user_id` de `$_SESSION['user_id']` (16) e `ip` de `REMOTE_ADDR` (17). Captura `PDOException` e retorna `false` (23-26).

- **69 chamadas em 58 arquivos**, cerca de 62 ações distintas (prefixos `AGRO_*`, `PA_*`, `IDENTIDADE_*`, `PORTAL_*`, `MANEJO_*`, `OCORRENCIA_*`, `ACESSO_*`, `SENHA_RESET`, `LOGOUT`).
- **ATENÇÃO — não existe ação `LOGIN`.** `login.php` — o fluxo de autenticação principal — **não registra nada** em `audit_log`: nem sucesso, nem falha, nem bloqueio por conta pendente. A única evidência de login é `PORTAL_LOGIN` / `PORTAL_LOGIN_MAGIC_LINK` (`modulos/portal/login.php:176,307`) e `LOGOUT` (`logout.php:7`). O docblock de `AuditLog` (linha 7) cita `LOGIN` como exemplo, mas ele nunca é emitido.
- Não há registro automático em CUD (sem trigger/observador); a cobertura depende de o autor da página lembrar de chamar `registrar()`.
- Não há **trilha de leitura** de dados sensíveis (LGPD): apenas `PORTAL_ACESSO_PRIVILEGIADO` / `PORTAL_ACESSO_NEGADO` cobrem o caso do Portal.

### 4.7 Infraestrutura

**`classes/Database.php`** — Singleton (36 linhas): `private __construct` (8), `getInstance()` (25-30), `getConnection()` (32-34). DSN `mysql:host=...;dbname=...;charset=utf8mb4` (11); `ERRMODE_EXCEPTION`; `MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"` (17). **Em falha de conexão: `die("Erro de conexão: " . $e->getMessage())` (linha 21) — vaza host, usuário e detalhes do driver na resposta HTTP.** Não há pool, não há retry e `PDO::ATTR_EMULATE_PREPARES` **não** é desativado (prepared statements emulados no cliente).

**`config/config.php`** — credenciais **hard-coded e versionadas**: `DB_HOST = 'localhost'`, `DB_USER = 'root'`, `DB_PASS = ''`, `DB_NAME = 'sisagro'` (11-14). `SITE_URL` = `'http://localhost/app'` (19), com a linha de produção comentada (18). Segredos externos vêm de `config/secrets.php` (não versionado, 35-37) ou de env (`RESEND_API_KEY` 40-42, `ID_ENCODE_KEY` 51-53). Não há `.env` nem phpdotenv no núcleo.

**Erros** — `config/error_config.php` (56 linhas), correção B-03: `APP_DEBUG` resolvido por (1) `config/local.php` não versionado, (2) env `APP_DEBUG`, (3) **default `false`** (44-49). `display_errors` / `display_startup_errors` seguem `APP_DEBUG`; `log_errors = 1` e `error_reporting(E_ALL)` sempre (53-56). **Bom.**

**`.htaccess` raiz (65 linhas):** `Options +FollowSymLinks -MultiViews -Indexes`; `RewriteBase /app/`; front-controller `RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]` (24); bloqueio de dotfiles (30-33); **bloqueio por extensão** `sql|log|env|bak|old|orig|ini|yml|yaml|cjs|mjs|ps1|sh|sqlite|db|dump|md` (47-55) — protege os ~13 MB de dumps em `sql/_backup/`; **bloqueio por nome** `^(check_.*|run_.*|debug_.*|fix_.*|test_.*|seed_.*|schema|comparar_backup|_tmp_.*|add_sigla|map_cidades_micro|municipio_micro_map|update_associados_regiao|composer\.(json|lock)|package(-lock)?\.json)` (57-64).

**`.htaccess` por diretório:** `config/`, `includes/`, `classes/`, `dev-utils/`, `logs/`, `sql/`, `storage/`, `tmp/`, `tools/`, `scripts/`, `producao_bkp/`, `recicla/`, `sinporg/` → `Require all denied`. `uploads/` → engine PHP desligado + deny de extensões executáveis + allow apenas estáticos. `api/` → **`Require all granted`** e **CORS `Access-Control-Allow-Origin: *`** com `Allow-Methods GET, POST, PUT, PATCH, DELETE, OPTIONS` e `Allow-Headers Content-Type, Authorization`.

> `Access-Control-Allow-Credentials` **não** é enviado, então o curinga `*` não permite leitura cross-origin com cookies (o browser bloqueia). Ainda assim, `X-CSRF-Token` não está em `Allow-Headers`, o que barra o preflight de requisições cross-origin que tentem enviar o token.
> **Não existe `.htaccess` em `modulos/`** — ver §3.1(D).

**Ponte PHP→JWT (`api/legacy/session-bridge.php`, 119 linhas, marcada como TEMPORÁRIA):** lê `sinporg/.env` (58) buscando `JWT_ACCESS_SECRET`; valida que a sessão PHP está ativa (71-109) e reconsulta **o banco** para confirmar `status = 'ativo'` — não confia só na sessão (76-78, 94-96); emite HS256 com `iat` / `exp` e TTL de `JWT_ACCESS_TTL_SECONDS` (default 900 s) (111-119); propaga `certificadoraId`, `associadoId`, `nivel`. A checagem de segredo fraco `'dev-apenas-trocar'` (60-65) está implementada de forma **incompleta**: se o segredo for exatamente esse valor e não vazio, o `if` interno não responde 500 e o código segue adiante.

**`Mailer`** (`classes/Mailer.php`, 81 linhas) — SDK Resend via `vendor/autoload.php` (5); exige chave iniciando com `re_` (21-23); `layout()` monta HTML com `htmlspecialchars($titulo)` (64) mas **insere `$conteudoHtml` cru** (69) — os chamadores devem escapar, e o fazem (`htmlspecialchars` em `api/recuperar_senha.php:46` e `modulos/portal/login.php:218-220`).

---

## 5. Lista COMPLETA das chaves de sessão

Varredura exaustiva de 510 linhas com `$_SESSION[...]` em todo o `*.php`. **33 chaves distintas.**

### 5.1 Identidade / sessão principal

| Chave | Escrita em (arquivo:linha) | Tipo |
|---|---|---|
| `user_id` | `login.php:42,138,158`; `classes/User.php:92`†; `modulos/portal/login.php:171,302` | int — identidade primária |
| `user_nivel` | `login.php:43,139,159`; `classes/User.php:93`†; `modulos/portal/login.php:172,303` | int 1, 2 ou 3 |
| `user_nome` | `login.php:44,140,160`; `classes/User.php:94`†; `modulos/usuarios/perfil.php:80,89`; `modulos/associados/meu_perfil.php:217` | string |
| `user_tipo` | `login.php:45,141,161`; `classes/User.php:95`† | string (admin/gestor/usuario; forçado 'gestor' no ramo Gestor) |
| `user_tipo_usuario` | `classes/User.php:96`†; `completar_perfil.php:52` | string — **NÃO gravado pelo `login.php`** |
| `user_area_producao` | `classes/User.php:97`† | CSV — **NÃO gravado pelo `login.php`** |
| `user_table` | `login.php:46,142,162`; `classes/User.php:98`†; `modulos/portal/login.php:173,304` | 'usuarios' ou 'associados' |
| `user_orgao` | `login.php:47,143` | string (só Gestor) |
| `user_setor` | `login.php:48,144` | string (só Gestor) |
| `user_especialidade` | `login.php:49,145` | string (só Gestor) |
| `user_{orgao,setor,especialidade,nicho}` | `classes/User.php:472` (dinâmico, em `updatePerfil`) | espelho do banco |
| `login_temp` | `login.php:126` (+ `['primeiro_acesso']` :134) | array — estado intermediário da Etapa 2 |
| `primeiro_acesso` | `login.php:55,190` | bool — flag de UI |
| `mostrar_modal_eventos` | `login.php:50,146,163` (lida em `includes/header.php:133`, consumida via `api/limpar_modal_eventos.php`) | bool |
| `user_config` | `modulos/usuarios/configuracoes.php:21` (lida :31) | array de preferências |
| `user_notif_config` | `modulos/usuarios/salvar_notificacoes.php:38` | array (nunca lida) |
| `whatsapp_links` | `modulos/comunicacao/index.php:125` | array (nunca lida) |
| `whatsapp_cluster` | `modulos/comunicacao/index.php:126` | string (nunca lida) |

† escrita dentro de `User::login()` — **código morto** (0 chamadas). Essas chaves **não existem em produção** por essa via.

### 5.2 Portal do Associado

| Chave | Escrita em | Tipo |
|---|---|---|
| `associado_id` | `modulos/portal/login.php:121,160,291` | int — identidade do portal (lida em **13 arquivos**) |
| `associado_nome` | `modulos/portal/login.php:122,161,292` | string |
| `associado_cpf` | `modulos/portal/login.php:123,162,293` | string (cru, sem normalização) |
| `associado_email` | `modulos/portal/login.php:124,163,294`; `modulos/portal/area.php:141` | string |
| `portal_acesso_via` | `modulos/portal/login.php:125,164,295` (consulta_admin / login_comum / magic_link); removida em `modulos/portal/logout.php:7` | string de auditoria |
| `portal_acesso_por` | `modulos/portal/login.php:126` | int — `user_id` do Admin/Diretoria que impersonou |
| `portal_tentativas` | `classes/PortalAccess.php:479` (`registrarTentativa`), limpa em `:486`, limitada por `MAX_TENTATIVAS = 10` / `JANELA_TENTATIVAS = 900` | array de timestamps |

### 5.3 Segurança

| Chave | Escrita em | Observação |
|---|---|---|
| `csrf_token` | `classes/CSRFProtection.php:9` (via constante `CSRF_TOKEN_NAME`, `config/config.php:47`); `dev-utils/test_api_ficha.php:14` (redundante) | `bin2hex(random_bytes(32))`, uma por sessão |

### 5.4 Flash / mensagens de UI (efêmeras)

| Chave | Escrita em | Qtd |
|---|---|---|
| `success_message` | `modulos/assembleia/*`, `modulos/mensalidades/*`, `modulos/caixa/*`, `modulos/associados/*`, `modulos/caderno_campo/propriedades/cadastrar.php:136` | 23 sites |
| `error_message` | `modulos/assembleia/*`, `modulos/mensalidades/*`, `modulos/caixa/excluir.php`, `modulos/associados/carteirinha*`, `modulos/associados/meu_perfil.php:141` | 22 sites |
| `erro` | `relatorios/financeiro.php:32`, `relatorios/eventos.php:94` | 2 sites (nunca lida) |

### 5.5 Chaves MORTAS (lidas mas nunca escritas / apenas em comentários)

| Chave | Evidência |
|---|---|
| `nivel` | aparece **apenas** no comentário `includes/header.php:84` ("antes lia `$_SESSION['nivel']`, que nunca é escrita") |
| `user_associado_id` | aparece **apenas** no comentário `modulos/associados/listar.php:59` ("chave que nunca é gravada") |
| `relatorio_financeiro_totais` | lida em `relatorios/templates/relatorio_financeiro.php:20-23`, **nunca gravada** — bloco morto |

---

## 6. Gaps e riscos identificados

### 6.1 CRÍTICO — `$_SESSION['user_tipo_usuario']` nunca é gravado pelo login real

**Evidência:** as únicas escritas são `classes/User.php:96` (dentro de `User::login()`, que tem **0 chamadas** em todo o repositório) e `completar_perfil.php:52` (página praticamente inalcançável — `hasPerfilCompleto()` agora retorna `true` com qualquer dado de contato, ver `classes/User.php:435-446`).
**Quem lê:** `includes/perfil.php:17,25` (`dashboard_destino()`), `index.php:874,877`, `classes/User.php:162` (`perfilDashboard()`).
**Impacto:** para **todo** usuário de nível 3, `$tipo` é sempre vazio → `dashboard_destino()` retorna **sempre** `/dashboard/agricultor`. Um `produtor_agroindustria` ou `agricultor_agroindustria` é mandado para o dashboard errado; o link "Início" do menu (`includes/menu.php:14`) e o bottom-nav (`includes/footer.php:23`) apontam consistentemente para o destino errado. `includes/menu.php:15` tem o mesmo defeito (`$ehUsuarioAgroindustria` sempre `false`).
**Atenuação parcial:** `index.php:878-886` cobre o acesso **manual** a `/dashboard/agroindustria` (cai em `temAgroindustria()`), mas o roteamento default permanece quebrado.
**Correção mínima:** adicionar `$_SESSION['user_tipo_usuario'] = $userData['tipo_usuario'] ?? ($userData['tipo'] ?? '');` nos três ramos do `login.php` (linhas 42-50, 138-146, 158-163) — ou passar a usar `User::login()` e remover a duplicação.

### 6.2 ALTO — `usuarios.nivel` com `DEFAULT 1` (= Admin)

**Evidência:** `sinporg/prisma/migrations/0_baseline/migration.sql:1493`: `nivel INTEGER NOT NULL DEFAULT 1`. E `NIVEL_ADMIN = 1` (`config/config.php:23`).
**Impacto:** qualquer `INSERT INTO usuarios` que omita `nivel` cria um **Administrador silencioso**. Os caminhos auditados (`api/pre_cadastro.php:117,124,140,147`; `modulos/usuarios/cadastrar.php:83-96`) **sempre** informam `nivel` explicitamente — mas o default permanece como armadilha para script futuro, migração ou seed.
**Correção:** `ALTER TABLE usuarios ALTER COLUMN nivel SET DEFAULT 3` (ou remover o default, forçando explicitação).

### 6.3 ALTO — Dois campos de status em `associados` (`status` versus `ativo`) e `tipo` com capitalização divergente

**Evidência:**
- `associados` tem **`status` ENUM('ativo','inativo','pendente')** *e* **`ativo` BOOLEAN** (`migration.sql:344-345`). `PortalAccess::buscarAssociadoAtivoPorCpf` filtra só por `status = 'ativo'` (`classes/PortalAccess.php:134`), `associadoIdPorEmail` idem (180, 193), `User::login()` idem (`classes/User.php:68`), `PasswordReset::findUserByEmail` idem (`classes/PasswordReset.php:26`) — porém `api/legacy/session-bridge.php:76` valida **`$a['ativo']`** e ignora `status`. Um associado com `ativo = 1` e `status = 'inativo'` entra no Portal mas recebe JWT da ponte Node (ou o inverso).
- `usuarios.tipo` é ENUM **minúsculo** ('admin','gestor','usuario'); `associados.tipo` é ENUM **capitalizado** ('Admin','Gestor','Usuario') (`migration.sql:346`). Como `$_SESSION['user_tipo']` recebe `$userData['tipo']` sem normalização (`login.php:161`), o valor em sessão **depende da tabela de origem** — qualquer comparação `=== 'admin'` falha silenciosamente para quem entrou pela tabela `associados`.
- `associados.nivel` é `INTEGER NOT NULL` **sem default** (`migration.sql:343`) — INSERT que o omita falha (positivo), mas não há CHECK restringindo a 1/2/3.

### 6.4 CRÍTICO — `GET /relatorio-associados` expõe CPF, e-mail e telefone de **todos** os associados sem autenticação

**Evidência:**
- Rota: `index.php:513-517` → `require modulos/relatorios/relatorio_associados.php` (sem nenhuma checagem antes).
- Arquivo: `modulos/relatorios/relatorio_associados.php` **não lê `$_SESSION`, não inclui `header.php`, não chama `hasPermission`** (varredura confirmada: 0 referências a sessão/auth em 153 linhas).
- Consulta: `SELECT a.id, CONCAT(a.nome,' ',a.sobrenome), a.cpf, a.email, a.telefone, ass.nome FROM associados a LEFT JOIN associacoes ass ... ORDER BY a.nome` (linhas 26-37).
- Saída: PDF com mPDF (117-126), colunas **Nome Completo / CPF / E-mail / Telefone / Associação** (74-79).

**Impacto:** qualquer visitante anônimo que acesse `/{SITE_URL}/relatorio-associados` baixa um PDF com a **base completa de dados pessoais e CPFs** de todos os produtores. Violação direta de LGPD e vetor de fraude.
**Agravante:** o irmão `api/relatorios/relatorio_associados.php` **tem** guarda (`!isset($_SESSION['user_id']) || !isset($_SESSION['user_nivel'])`, linha 11) — a proteção existe, mas não nesta rota.
**Correção imediata:** inserir `if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }` no topo (ou `ApiAuth::exigirStaff()`) e remover a rota pública.

### 6.5 ALTO — `login.php` sem rate limiting e sem auditoria de autenticação

**Evidência:** o fluxo da Etapa 1 (`login.php:78-221`) não tem contador de tentativas, atraso progressivo, CAPTCHA nem lockout; em falha apenas define `$error_message` (214). Comparado ao Portal, que tem `PortalAccess::registrarTentativa()` com janela de 900 s / 10 tentativas (`classes/PortalAccess.php:466-487`), a tela principal está **desprotegida**. E `AuditLog::registrar('LOGIN', ...)` **não existe em lugar nenhum** — nem sucesso, nem falha (69 chamadas de auditoria inventariadas; nenhuma é `LOGIN`).
**Impacto:** credential stuffing / força bruta ilimitada na tabela `usuarios` (que inclui **todos** os Admins) sem deixar rastro investigável. Como `associados` e `usuarios` compartilham o e-mail, a superfície de ataque é maior.

### 6.6 ALTO — CSRF ausente em 47 de 86 handlers POST (55%)

**Evidência:** lista completa em §4.1. Destaques: `modulos/usuarios/perfil.php` (**muda e-mail de login e senha sem token**), `modulos/associacoes/{cadastrar,editar}.php`, `modulos/contas/{cadastrar,editar}.php`, `modulos/eventos/editar.php`, e **toda** a família `producao_animal/*` (12 arquivos) e `manejo_animal/animais_*.php` (3).
**Atenuação parcial:** o monkey-patch de `window.fetch` (`includes/header.php:110-131`) injeta `X-CSRF-Token` automaticamente, mas **isso não é validação** — um formulário HTML hospedado em domínio externo contorna o mecanismo. A defesa real é server-side e está ausente nesses 47 pontos.

### 6.7 ALTO — Padrão "escopo null = todos" em 21 páginas (IDOR horizontal sistêmico)

**Evidência:** §3.2. `User::getAssociadoId()` devolve `null` para contas `usuarios` sem vínculo (`classes/User.php:259-272`), e `getAll($filtroAssociado)` só filtra se truthy (`classes/ManejoAnimal.php:21`, `classes/AgroAtividadeDiaria.php:20`). Contas criadas por `api/pre_cadastro.php` ficam **exatamente** nesse estado até a aprovação da Ficha de Adesão (`pre_cadastro.php:128-153`).
**Impacto:** usuário de nível 3 vê animais, lotes, sanidade, reprodução, alimentação, ambientes, ordens de produção, receitas, setores, higienização, ocorrências e atividades de **todos** os produtores.
**Correção:** inverter o default — `getAll()` deve **exigir** o escopo e as páginas devem negar acesso quando `getAssociadoId()` for falsy, em vez de degradar para "sem filtro".

### 6.8 MÉDIO — Credenciais de banco hard-coded e `die()` vazando detalhes do driver

**Evidência:** `config/config.php:11-14` (`root` / senha vazia versionados) e `classes/Database.php:20-22` (`die("Erro de conexão: " . $e->getMessage())`). `APP_DEBUG = false` não ajuda: o `die()` é incondicional e ocorre **antes** de qualquer supressão de saída.
**Impacto:** em queda do MySQL, a resposta HTTP revela host, usuário, nome do banco e mensagem do driver. Combinado com `SITE_URL = 'http://localhost/app'` (linha 19) e `DB_PASS` vazio, o repositório não está pronto para produção.

### 6.9 MÉDIO — `ID_ENCODE_KEY` default previsível e AES-CBC sem autenticação

**Evidência:** `config/config.php:52` (`'troque-esta-chave-em-producao'`) e `58-81` (AES-256-CBC sem MAC). Uso restrito a 13 pontos.
**Impacto:** se a env var/chave não for definida em produção, qualquer ID codificado é decifrável (chave pública no código) e o ciphertext é maleável. A "criptografia de IDs" não oferece confidencialidade nem integridade.
**Correção:** exigir a chave (fail-fast se ausente), usar AES-256-GCM, e registrar no PRD que `encodeId` **não é** controle de acesso.

### 6.10 MÉDIO — `User::login()` é código morto e duplica a lógica de `login.php`

**Evidência:** 0 chamadas a `->login(`; a lógica existe em duas cópias (`login.php:95-111` e `classes/User.php:63-103`), com diferenças: `User::login()` grava `user_tipo_usuario` e `user_area_producao` (que o `login.php` não grava — §6.1) e faz `session_regenerate_id` apenas se a sessão estiver ativa (88-90). A duplicação já produziu divergência de comportamento real.

### 6.11 MÉDIO — `logout.php` não invalida cookie nem limpa a sessão

**Evidência:** `classes/User.php:110-112` (`session_destroy()` apenas) e `logout.php:6-10`. Não há `setcookie(session_name(), '', time()-42000)` nem `session_regenerate_id()`. O arquivo de sessão no servidor é destruído (positivo), mas o cookie permanece no browser.
**Agravante:** `logout.php` é **GET** — logout forçado via `<img src>` é possível (severidade baixa).

### 6.12 MÉDIO — Assimetria de status HTTP nas negações

**Evidência:** `ApiAuth::negar()` (`classes/ApiAuth.php:186-194`) retorna **sempre** `403` para falta de privilégio (linhas 149, 162, 181) e `401` só para falta de login (136) — porém as páginas de módulo, ao negar, fazem `echo "<div class='alert alert-danger'>...</div>"` **com HTTP 200** (ex.: `modulos/dashboard/dashboard_admin.php:9`, `modulos/usuarios/editar.php:8,45`), e `PortalAccess` não emite status algum, apenas grava mensagem na sessão (`MSG_ACESSO_NEGADO`, `classes/PortalAccess.php:28`). Isso quebra monitoramento, permite enumeração por conteúdo e engana varreduras automatizadas.

### 6.13 MÉDIO — `api/` com CORS curinga

**Evidência:** `api/.htaccess:13-18`. Não é explorável para leitura autenticada por cookie (falta `Access-Control-Allow-Credentials`), mas é configuração permissiva que vira vulnerabilidade no momento em que alguém adicionar `Allow-Credentials: true` — e `Authorization` já está em `Allow-Headers` (linha 19), habilitando consumo cross-origin por tokens.

### 6.14 BAIXO — Diversos

| # | Achado | Evidência |
|---|---|---|
| a | Arquivos de teste **não bloqueados** pelo `.htaccess` raiz | `modulos/caderno_campo/lotes_producao/teste_qr.php` e `teste_qrcodejs.php` **não** casam com `test_.*` (é `teste_`). `debug_qr.php` casa com `debug_.*` e está bloqueado. Os três não têm guarda de sessão |
| b | Sem proteção de sessão contra inatividade | `config/config.php:94-100`: `lifetime = 0`, sem `gc_maxlifetime`, sem timestamp de último acesso validado |
| c | Token CSRF não rotaciona | `CSRFProtection::generateToken()` (6-13) só gera se vazio — o mesmo token vale para toda a sessão, inclusive após login/logout |
| d | `api/recuperar_senha.php` sem CSRF | linhas 16-29: dispara e-mail a partir de qualquer origem (spam de reset) |
| e | `api/pre_cadastro.php` sem CSRF e sem rate limit | linhas 12-16: cria contas pendentes em massa |
| f | Link de reset vai para o log do servidor em falha de e-mail | `api/recuperar_senha.php:58` |
| g | Inconsistência de expiração do link mágico | UI diz 30 min (`modulos/portal/login.php:438`); código define 60 min (`classes/PasswordReset.php:55`) |
| h | `completar_perfil.php` sem CSRF permite trocar `tipo_usuario` para `produtor_agroindustria` | `completar_perfil.php:26-52` + `classes/User.php:186` libera o módulo de agroindústria |
| i | `modulos/usuarios/perfil.php` permite trocar o e-mail de login sem confirmação de senha | `modulos/usuarios/perfil.php:82-90` |
| j | `session-bridge.php` aceita segredo fraco | `api/legacy/session-bridge.php:60-65` |
| k | `user_notif_config`, `whatsapp_links`, `whatsapp_cluster`, `erro` são gravadas e nunca lidas | §5.1 / §5.4 |
| l | `relatorio_financeiro_totais` é lida e nunca gravada | `relatorios/templates/relatorio_financeiro.php:20-23` |
| m | Sem `password_needs_rehash` / política de força | mínimo de 6 caracteres (`api/pre_cadastro.php:75`, `redefinir_senha.php:26`) |
| n | `modulos/usuarios/listar.php:13` redireciona para `/login.php` (com extensão), URL inexistente no roteador amigável | linha 13 |
| o | Ausência de `.htaccess` em `modulos/` → acesso direto a `/app/modulos/**/*.php` | §3.1(D) |
| p | `PDO::ATTR_EMULATE_PREPARES` não desativado | `classes/Database.php:14-18` |

---

## 7. Tabela de métodos públicos (para o PRD)

### 7.1 `classes/User.php` — identidade, sessão e decisão de nível

| Assinatura | Linha | Propósito |
|---|---|---|
| `const TABELAS_USUARIO = ['associados','usuarios']` | 13 | Whitelist de tabelas de identidade (defesa B-01) |
| `const TABELA_USUARIO_PADRAO = 'associados'` | 14 | Fallback quando a sessão está adulterada |
| `const TIPOS_VINCULO_DIRETORIA = [...]` | 278-287 | 8 cargos que dão acesso à Diretoria |
| `__construct()` | 18 | Obtém a conexão PDO do Singleton |
| `static tabelaUsuarioSegura(?string $tabela = null, ?string $padrao = null): string` | 31 | Normaliza e valida `$_SESSION['user_table']` contra a whitelist |
| `login($email, $senha): bool` | 54 | **Morto** — login unificado (usuarios depois associados) + regeneração de sessão |
| `isLoggedIn(): bool` | 106 | `isset($_SESSION['user_id'])` |
| `logout(): void` | 110 | `session_destroy()` (não limpa cookie) |
| `hasPermission($required_level): bool` | 114 | Núcleo da autorização; nível menor = mais poder |
| `isGestorOuAdmin(): bool` | 147 | Atalho para `hasPermission(NIVEL_GESTOR)` |
| `perfilDashboard(): string` | 157 | admin, gestor, agricultor ou agroindustria |
| `temAgroindustria(): bool` | 177 | `tipo_usuario`, `tem_agroindustria` ou existência de registro em `agroindustrias` |
| `areaProducao(): array` | 209 | `['animal','vegetal','agroindustria']` do usuário |
| `getCurrentUser(): array\|false` | 236 | Linha completa da tabela de origem via `tabelaUsuarioSegura()` |
| `getAssociadoId(): int\|null` | 259 | ID do associado (ATENÇÃO: `null` vira "sem filtro" em `getAll`) |
| `getAssociadoIdResolvido(): int` | 299 | Cobre 4 formas de sessão/vínculo; `0` quando não há |
| `getAssociacoesDiretoria(): int[]` | 337 | Associações onde há cargo de diretoria/comissão |
| `isMembroDiretoria(int $associacaoId = 0): bool` | 363 | Membro de uma ou de qualquer diretoria |
| `podeAcessarDiretoria(): bool` | 380 | Admin **ou** membro de diretoria |
| `isDiretoriaExecutiva(): bool` | 392 | Presidente/VP/Diretor/Secretário/Tesoureiro ou Admin |
| `hasPerfilCompleto(): bool` | 420 | Exige orgao+setor+especialidade (Gestor) ou qualquer dado de contato (Usuário) |
| `updatePerfil($dados): bool` | 456 | Atualiza orgao/setor/especialidade/nicho e espelha na sessão |
| `updateCurrentUser($dados): bool\|string` | 501 | Atualiza só colunas existentes, bloqueia id/senha/datas, converte vazio em NULL em UNIQUEs |

### 7.2 `classes/AcessoPendente.php` — confirmação de privilégios do pedido de acesso

| Assinatura | Linha | Propósito |
|---|---|---|
| `const NIVEIS_VALIDOS = [1,2,3]` | 33 | Níveis que o Admin pode conceder |
| `const ROTULOS = [1=>'Admin',2=>'Gestor/Fiscal',3=>'Usuário']` | 36 | Rótulos de UI |
| `__construct()` | 54 | Conexão PDO |
| `static rotulo($nivel): string` | 60 | Nome legível do nível |
| `static nivelSolicitado(array $usuario): int` | 67 | Nível pedido (com fallback para pedidos antigos) |
| `static podeConceder(?User $quem, $nivel): bool` | 81 | Só Admin concede, e só níveis válidos |
| `solicitacoesPendentes(): array` | 94 | Contas `usuarios.status = 'pendente'` |
| `solicitacao($usuarioId): ?array` | 113 | Uma solicitação por ID |
| `ultimasAnalises($limit = 10): array` | 129 | Histórico de confirmações/recusas (limite 1 a 50) |
| `confirmarPrivilegios($usuarioId, $nivelConcedido, array $dados = [], ?User $admin = null): array` | 159 | **Método principal** — ativa a conta, define nível/tipo/órgão/setor/especialidade, audita `ACESSO_CONFIRMADO`, tolera concorrência |
| `negarSolicitacao($usuarioId, $motivo, ?User $admin = null): array` | 256 | Marca inativo, grava motivo, audita `ACESSO_NEGADO` |

### 7.3 `classes/PortalAccess.php` — guarda central do Portal do Associado

| Assinatura | Linha | Propósito |
|---|---|---|
| `const MSG_ACESSO_NEGADO` | 28 | Mensagem única de negação |
| `const JANELA_TENTATIVAS = 900` / `MAX_TENTATIVAS = 10` | 31-32 | Anti abuso por sessão |
| `static normalizarCpf($cpf): string` | 53 | 11 dígitos, tolera zeros à esquerda |
| `static formatarCpf($cpf): string` | 70 | Formata 000.000.000-00 |
| `static mascararCpf($cpf): string` | 80 | Mascara para logs (LGPD) |
| `static cpfSqlExpr(string $coluna = 'cpf'): string` | 93 | Expressão SQL que extrai dígitos (coluna validada por regex) |
| `static variantesCpf($cpf): array` | 107 | Variantes com e sem zeros à esquerda |
| `static buscarAssociadoAtivoPorCpf($cpf): ?array` | 127 | Busca tolerante de associado ativo |
| `static cpfDoAssociadoId($associadoId): string` | 148 | CPF normalizado por ID |
| `static associadoIdPorEmail($email): int` | 173 | Resolve associado por e-mail de cadastro **ou** de conta vinculada |
| `static emailDeAcessoDoAssociado($associadoId, $emailCadastro = ''): string` | 209 | E-mail que recebe o link mágico |
| `static usuarioVinculadoAoAssociado($associadoId): ?array` | 229 | Vínculo **real** (não por e-mail) — evita escalonamento |
| `static associadoIdDoToken($token): int` | 256 | Titular de um token de link mágico válido |
| `static temSessaoPortal(): bool` | 279 | `!empty($_SESSION['associado_id'])` |
| `static autenticado(?User $user = null): bool` | 285 | Sessão principal **ou** de portal |
| `static ehPrivilegiado(?User $user = null): bool` | 295 | Admin **ou** Diretoria Executiva |
| `static cpfDoUsuario(?User $user = null): string` | 309 | CPF do autenticado (portal, vínculo ou própria linha) |
| `static ehTitularDoCpf($cpf, ?User $user = null): bool` | 341 | Comparação `hash_equals` de CPF normalizado |
| `static ehTitularDoAssociadoId($associadoId, ?User $user = null): bool` | 356 | Titularidade por ID de associado |
| `static bloqueioAtivo(?User $user = null): bool` | 379 | Autenticado **e** não privilegiado → guarda restritiva ligada |
| `static podeAcessarCpf($cpf, ?User $user = null): bool` | 391 | Anônimo, privilegiado ou titular |
| `static podeAcessarAssociadoId($associadoId, ?User $user = null): bool` | 405 | Mesma regra por ID |
| `static podeConsultarOutros(?User $user = null): bool` | 419 | Modo administrativo disponível |
| `static registrarAcessoNegado($acao, $cpfAlvo = '', $associadoAlvo = 0): void` | 429 | Audita `PORTAL_ACESSO_NEGADO` com CPF mascarado |
| `static registrarAcessoPrivilegiado($associadoId, $detalhe = ''): void` | 444 | Audita `PORTAL_ACESSO_PRIVILEGIADO` |
| `static ip(): string` | 457 | `REMOTE_ADDR` (ou 'cli') |
| `static tentativasRestantes(): int` | 466 | Tentativas restantes na janela |
| `static registrarTentativa(): bool` | 474 | Registra tentativa; `true` se excedeu o limite |
| `static limparTentativas(): void` | 484 | Zera o contador em caso de sucesso |

### 7.4 `classes/ApiAuth.php` — guarda para endpoints (API/AJAX)

| Assinatura | Linha | Propósito |
|---|---|---|
| `static iniciar(): void` | 26 | `session_start()` idempotente |
| `static nivel(): int` | 50 | Nível efetivo (0 = anônimo; Portal = 3, nunca elevado) |
| `static autenticado(): bool` | 71 | `nivel() > 0` |
| `static ehAdmin(): bool` | 78 | `nivel === 1` |
| `static ehStaff(): bool` | 84 | Admin ou Gestor |
| `static ehDiretoriaExecutiva(): bool` | 97 | Delega a `User::isDiretoriaExecutiva()` |
| `static associadoId(): int` | 111 | `User::getAssociadoIdResolvido()` com tratamento de exceção |
| `static exigirLogin(bool $json = true): void` | 133 | `401` e `exit` se anônimo |
| `static exigirStaff(bool $json = true): void` | 145 | `403` se não for Admin/Gestor |
| `static exigirNivel(int $nivelNecessario, bool $json = true): void` | 157 | `403` se nível insuficiente |
| `static exigirProprioAssociado($associadoId, bool $json = true): void` | 170 | Defesa padrão contra IDOR (staff/diretoria ou dono) |
| `static negar(int $codigo, string $mensagem, bool $json = true): void` | 186 | Responde e encerra com `exit` |

### 7.5 `classes/CSRFProtection.php` — tokens CSRF

| Assinatura | Linha | Propósito |
|---|---|---|
| `static generateToken()` | 6 | Cria ou retorna `bin2hex(random_bytes(32))` na sessão |
| `static getTokenField()` | 15 | Campo hidden `csrf_token` escapado |
| `static validatePostRequest()` | 24 | Exige POST e `hash_equals` contra a sessão |
| `static getToken()` | 44 | Atalho para `generateToken()` (exposto a JS no header) |
| `static validateToken($token)` | 57 | Valida token arbitrário (header/corpo) |
| `static tokenFromRequest()` | 77 | Extrai o token de header, `$_POST`, `$_GET` ou corpo JSON |

### 7.6 `classes/Sanitizer.php` — higienização e validação de entrada

| Assinatura | Linha | Propósito |
|---|---|---|
| `static sanitizeString($input): string` | 15 | Remove tags, faz trim e elimina caracteres de controle |
| `static sanitizeInt($input): int` | 28 | Inteiro por `FILTER_SANITIZE_NUMBER_INT` |
| `static sanitizeFloat($input): float` | 37 | Float com fração permitida |
| `static sanitizeEmail($input): string` | 46 | Limpa e-mail |
| `static validateEmail($input): string\|false` | 55 | `FILTER_VALIDATE_EMAIL` |
| `static sanitizeMoney($input): float` | 73 | Converte máscara BR, centavos ou decimal para float em reais |
| `static sanitizeUrl($input): string` | 101 | Limpa URL |
| `static validateUrl($input): string\|false` | 110 | `FILTER_VALIDATE_URL` |
| `static sanitizeArray(array $data): array` | 119 | Sanitização recursiva de arrays |
| `static sanitizeName(string $name): string` | 142 | Normaliza espaços, remove símbolos, aplica Title Case UTF-8 |
| `static sanitizePhone(string $phone): string` | 163 | Apenas dígitos |
| `static sanitizeCpfCnpj(string $value): string` | 172 | 11 (CPF) ou 14 (CNPJ) dígitos, senão vazio |
| `static sanitizeCPF(string $value): string` | 185 | Wrapper de compatibilidade: só 11 dígitos |
| `static sanitizeAddress(string $address): string` | 194 | Remove tags e normaliza espaços |

### 7.7 `classes/PasswordReset.php` — recuperação de senha

| Assinatura | Linha | Propósito |
|---|---|---|
| `__construct()` | 10 | Conexão PDO |
| `findUserByEmail($email): array\|false` | 20 | Localiza conta ativa e devolve tabela, id, nome e e-mail |
| `createToken($email): string` | 48 | Gera token de 64 hex, grava SHA-256, expira em 60 min e invalida anteriores |
| `getValidToken($token): array\|false` | 72 | Valida por hash, `used = 0` e `expires_at > NOW()` |
| `markUsed($id): bool` | 91 | Consumo atômico, evitando TOCTOU |
| `updatePassword($tabela, $userId, $novaSenha): bool` | 105 | Troca a senha com whitelist de tabela e `password_hash` |
| `purgeOldTokens(): bool` | 117 | Remove tokens expirados ou usados (manutenção) |

### 7.8 `classes/Mailer.php` — e-mail transacional (Resend)

| Assinatura | Linha | Propósito |
|---|---|---|
| `static send($to, $subject, $html, $text = null, $attachments = []): array` | 19 | Envia via SDK Resend; retorna success, id e error |
| `static layout($titulo, $conteudoHtml): string` | 56 | Template HTML padrão (header verde e rodapé SINPORG) |

### 7.9 `classes/AuditLog.php` — trilha de auditoria

| Assinatura | Linha | Propósito |
|---|---|---|
| `static registrar($acao, $tabela = null, $registro_id = null, $descricao = null): bool` | 13 | Insere em `audit_log` com user_id da sessão e REMOTE_ADDR |

### 7.10 `classes/Database.php` — conexão PDO (Singleton)

| Assinatura | Linha | Propósito |
|---|---|---|
| `static getInstance(): Database` | 25 | Instância única, criada sob demanda |
| `getConnection(): PDO` | 32 | PDO configurado com ERRMODE_EXCEPTION, FETCH_ASSOC e utf8mb4 |

---

## 8. Síntese executiva para o PRD

| Dimensão | Situação | Evidência-núcleo |
|---|---|---|
| **Modelo de identidade** | Duas tabelas com vínculo 1:1 bidirecional e login unificado por e-mail (precedência `usuarios`) | `migration.sql:322,1475`; `login.php:95-111` |
| **Login do Portal** | CPF+senha **ou** link mágico SHA-256 de uso único; guarda central `PortalAccess` | `modulos/portal/login.php`; `classes/PortalAccess.php` |
| **Níveis** | 1 Admin > 2 Gestor > 3 Usuário (menor = mais poder); regra canônica em `User::hasPermission()` | `config.php:23-25`; `User.php:114-141` |
| **Autorização** | **Sem middleware**; 4 mecanismos paralelos (header, hasPermission, ApiAuth, rota_segura); menu é cosmético | §3.1 |
| **Multi-tenant** | **Inexistente como fronteira** — apenas branding via `certificadoras.gestor_id` | §1.5 |
| **CSRF** | Token de 256 bits com `hash_equals`, mas cobertura de apenas **45%** dos handlers POST | §4.1 |
| **Hashing** | `password_hash` / `password_verify`, sem md5 ou sha1 | §4.5 |
| **IDs em URL** | `encodeId` / `decodeId` em AES-256-CBC **sem autenticação** e com **chave default pública**; usado em só 13 pontos | §4.4 |
| **SQL** | PDO com prepared statements; interpolações residuais são whitelistadas | §4.3 |
| **Auditoria** | Funcional (`audit_log` com IP e user_id) mas **sem evento de LOGIN** | §4.6 |
| **Riscos críticos** | (1) `/relatorio-associados` público com CPF de todos; (2) `user_tipo_usuario` nunca gravado; (3) escopo null = "todos" em 21 páginas; (4) `nivel DEFAULT 1` | §6.1, §6.2, §6.4, §6.7 |

---

*Relatório gerado por engenharia reversa estática. Todas as referências `arquivo:linha` foram verificadas por leitura direta do código-fonte. Itens marcados como não lidos em §0.3 não fundamentam nenhuma conclusão deste documento.*
