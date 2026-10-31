# Auditoria de Segurança — SINPORG

**Data:** 05/08/2026
**Ambiente:** Windows / XAMPP (Apache 2.4.58, PHP 8.2.12), MySQL `sisagro`
**Escopo:** `C:\xampp\htdocs\app` (SITE_URL `http://localhost/app`)

---

## 1. Vulnerabilidades Críticas — Corrigidas e Validadas

### 1.1 RCE por upload de arquivos arbitrários (CRÍTICA — comprovada)
- **Antes:** `modulos/caderno_campo/atividade_diaria/cadastrar.php` e `editar.php` aceitavam qualquer extensão via `move_uploaded_file`, sem validação de conteúdo.
- **Prova de conceito realizada:** upload de `atividade_*.php` → acesso via `http://localhost/uploads/atividades/atividade_*.php` executou código (`POC_RCE_*`, HTTP 200). Artefatos removidos após o teste.
- **Correção:** uploads agora usam `ImageUploader` (valida MIME + `getimagesize` + re-encoda a imagem via GD). Arquivo com extensão/ conteúdo não-imagem é rejeitado.
- **Validação:** upload de `.php` rejeitado (nenhum arquivo criado); upload de PNG legítimo salvo e servido (HTTP 200, `image/png`).

### 1.2 Bug de caminho no upload de atividades
- **Antes:** `__DIR__ . '/../../../../uploads/atividades/'` gravava **fora** do webroot do app, em `C:\xampp\htdocs\uploads\atividades`, quebrando as imagens exibidas (`uploads/atividades/...`).
- **Correção:** caminho corrigido para `../../../uploads/atividades/` (dentro do app).
- **Nota:** os 5 PNGs órfãos de `C:\xampp\htdocs\uploads\atividades` foram movidos para `app/uploads/atividades/`, restaurando as imagens de atividades existentes.

### 1.3 Diretórios de upload acessíveis sem proteção
- **Antes:** `uploads/.htaccess` = `Order Allow,Deny` + `Allow from all` → qualquer arquivo em `uploads/` era servido, e PHP em `uploads/` era executado.
- **Correção:** `uploads/.htaccess` e `uploads/certificados/.htaccess` agora desabilitam o engine PHP e negam acesso a extensões executáveis (`php*`, `phtml`, `phar`, `pl`, `py`, `cgi`, `sh`, etc.), mantendo estáticos (imagens/PDFs) acessíveis.
- **Validação:** `uploads/atividades/test.php` → HTTP 403; PNG → sem bloqueio.

### 1.4 CSRF ausente em cadastro/edição de atividade
- **Correção:** `cadastrar.php` e `editar.php` validam `CSRFProtection::validatePostRequest()` e o formulário inclui `getTokenField()`.
- **Validação:** POST sem token → "Requisição inválida (token de segurança ausente ou expirado)".

### 1.5 IDOR (troca de dono) no cadastro/edição de atividade
- **Antes:** `associado_id`, `propriedade_id` e `lavoura_id` vinham do POST sem validação; usuário comum podia atribuir atividade a outro produtor.
- **Correção:** para não-gestores, `associado_id` é forçado ao próprio associado e propriedade/lavoura são validadas contra o associado logado.
- **Validação:** POST de Roselei (assoc 28) com `associado_id=30, propriedade_id=11` (de Vilceu) foi gravado como `associado_id=28, propriedade_id=null`. Bloqueado.

### 1.6 Stored XSS via logo SVG
- **Correção:** `api/certificadoras/controller.php` — `svg` removido da whitelist de extensões; agora só `jpg/jpeg/png` + verificação `getimagesize()`.

### 1.7 RCE e caminho errado no upload de ocorrências e croqui
- **Ocorrências** (`modulos/caderno_campo/ocorrencias/cadastrar.php` e `editar.php`): mesmo padrão de `cadastrar.php` — `move_uploaded_file` sem validação e caminho `../../../../uploads/ocorrencias/` gravando fora do webroot.
- **Croqui** (`modulos/plano_manejo/upload_croqui.php`): MIME validado só pelo `$_FILES['type']` (forjável) e extensão do nome do arquivo usada diretamente → permitia salvar `.php` em `uploads/planos_manejo/`; sem CSRF e sem checagem de ownership do `plano_id` (qualquer usuário logado podia sobrescrever o croqui de qualquer plano).
- **Correção:** uploads de ocorrências agora usam `ImageUploader` (validação + re-encode GD) e caminho `../../../uploads/ocorrencias/`. Croqui valida conteúdo real (`finfo` para PDF, `getimagesize` para imagens), força extensão a partir do conteúdo validado, exige CSRF e valida ownership (`plano.associado_id` = associado do usuário para não-gestores).

---

## 2. Pontos Já Seguros (confirmados)

- **Uploads de imagem** via `ImageUploader` (validação + re-encode GD) — seguro.
- **Assinatura de certificado** (`api/certificacao/controller.php`) — nome de arquivo força `.pdf`.
- **Senhas:** `password_hash`/`password_verify` em login, cadastro e perfil.
- **SQL:** prepared statements nas operações principais; `User::updatePerfil` com whitelist de campos.
- **Relatórios de conformidade** (`ReportConformidade`) usam campos fixos (sem injection).

---

## 3. Recomendações para Produção (não bloqueantes, prioridade MÉDIA/BAIXA)

1. **Sessão:** adicionar `session_regenerate_id(true)` após autenticação no `login.php` (evita fixação de sessão) e definir cookies com `HttpOnly`, `Secure` e `SameSite=Lax` (ex.: `session_set_cookie_params`) em `config/config.php`.
2. **Brute-force:** implementar rate-limit/throttle no `login.php` (bloqueio após N tentativas).
3. **Credenciais do banco:** não usar `root` sem senha; criar usuário dedicado e mover credenciais para fora do webroot (ex.: variáveis de ambiente).
4. ~~**`display_errors`:** remover `ini_set('display_errors', 1)`~~ — **concluído** (ver seção 6, B-03).
5. **Scripts de dev no webroot:** remover ou proteger `dev-utils/`, `check_schema.php`, `add_sigla.php`, `api/uploads/` antes de ir a produção.
6. **Relatórios órfãos:** `relatorios/*.php` redirecionam 302; apagar ou consolidar.
7. **Cobertura CSRF:** revisar os ~69 formulários restantes e aplicar `getTokenField()`/`validatePostRequest()` onde faltar (especialmente POSTs em `modulos/eventos/excluir.php`, `modulos/certificacao/*`, `api/certificadoras`).
8. **Caminho orfão de uploads:** ~~mover os 5 PNGs~~ — concluído; os PNGs estão em `app/uploads/atividades/`.
9. **HTTPS:** quando houver domínio/certificado, forçar HTTPS (afeta cookies `Secure` e URLs `SITE_URL`).
10. **Auditoria de XSS:** varrer `echo` de variáveis sem `htmlspecialchars()` em páginas de listagem/formulários.

---

## 4. Arquivos Alterados nesta Auditoria

- `modulos/caderno_campo/atividade_diaria/cadastrar.php` — RCE, caminho, CSRF, IDOR
- `modulos/caderno_campo/atividade_diaria/editar.php` — RCE, caminho, CSRF, IDOR
- `modulos/caderno_campo/ocorrencias/cadastrar.php` — RCE, caminho (ImageUploader)
- `modulos/caderno_campo/ocorrencias/editar.php` — RCE, caminho (ImageUploader)
- `modulos/plano_manejo/upload_croqui.php` — validação de conteúdo, extensão segura, CSRF, ownership
- `uploads/.htaccess` — bloqueio de execução de scripts
- `uploads/certificados/.htaccess` — bloqueio de execução de scripts
- `api/certificadoras/controller.php` — remoção de SVG + validação de conteúdo

## 5. Validações Executadas (resumo)

| Teste | Resultado |
|---|---|
| Upload `.php` (RCE) | Bloqueado — nenhum arquivo criado |
| Upload PNG legítimo | OK — salvo em `app/uploads/atividades`, servido 200 `image/png` |
| Execução de PHP em `uploads/` | 403 Forbidden |
| POST sem CSRF | Rejeitado |
| IDOR `associado_id`/`propriedade_id` | Neutralizado (forçado para o próprio associado) |

---

## 6. Bugs Críticos do PRD — B-01, B-02 e B-03 (corrigidos)

Referência: `recicla/PRD.md`, seção "3.1 Críticos".

### B-01 — SQL Injection via `$_SESSION['user_table']` (whitelist de tabela)

- **Antes:** o nome da tabela do usuário vinha da sessão e era interpolado direto na
  query (o PDO não permite parametrizar nome de tabela). Uma sessão adulterada
  executava SQL arbitrário.
- **Correção:** `classes/User.php` ganhou a whitelist `User::TABELAS_USUARIO`
  (`associados`, `usuarios`) e o método `User::tabelaUsuarioSegura()`, que normaliza,
  valida e registra em log qualquer valor fora da lista, devolvendo sempre um nome
  seguro.
- **Call sites corrigidos:** `User::getCurrentUser()`, `User::updatePerfil()`,
  `User::updateCurrentUser()` (inclui o `SHOW COLUMNS FROM`), `api/relatorios_admin.php`
  e `login.php` (etapa 2 do gestor).
- **Já seguros (verificados):** `modulos/usuarios/perfil.php`,
  `classes/PasswordReset.php`, `modulos/associados/pendentes.php`.
- **Validação:** payloads como `usuarios WHERE 1=1 UNION SELECT senha FROM associados --`,
  `associados; DROP TABLE associados; --`, `information_schema.tables` e `mysql.user`
  são todos descartados e logados.

### B-02 — Path Injection / LFI nas rotas dinâmicas (whitelist de arquivos)

- **Antes:** `index.php` montava `__DIR__ . '/api/relatorios/' . $parts[2] . '.php'` e
  incluía o resultado se `file_exists()`. Uma URL com `../` saía do diretório e
  executava qualquer `.php` do servidor. A rota `/api/pix/webhook/<provedor>` tinha
  exatamente o mesmo padrão.
- **Correção:** novo `includes/rota_segura.php` com `rota_arquivo_seguro()`, aplicando
  três camadas: (1) formato do slug (`^[A-Za-z0-9][A-Za-z0-9_-]*$`, máx. 64 chars),
  (2) whitelist explícita (`ROTAS_API_RELATORIOS_PERMITIDAS`,
  `ROTAS_PIX_WEBHOOK_PERMITIDAS`) e (3) confinamento por `realpath()` dentro do
  diretório base. Includes internos (`bootstrap.php`, `_config.php`) ficaram fora da
  whitelist de propósito.
- **Validação (HTTP):** `/api/relatorios/../../config/config`, a variante
  URL-encoded, `/api/relatorios/bootstrap` e `/api/pix/webhook/../../../config/config`
  retornam **404** sem incluir nada; rotas legítimas continuam funcionando.

### B-03 — `display_errors` ativo em produção

- **Antes:** ~25 arquivos com `ini_set('display_errors', 1)` fixo, expondo caminhos
  absolutos, SQL e stack traces — e corrompendo respostas JSON e downloads PDF/Excel.
- **Correção:** novo `config/error_config.php` com a flag única `APP_DEBUG`
  (padrão **false** = produção: `display_errors` off, `log_errors` on). É carregado
  automaticamente por `config/config.php` e substituiu o bloco de debug em todos os
  arquivos.
- **Como ligar o debug em desenvolvimento:** `SetEnv APP_DEBUG 1` no Apache/.htaccess,
  ou um `config/local.php` (não versionado) com `define('APP_DEBUG', true);`.
- **Validação:** com `APP_DEBUG` ausente → `display_errors=0` mesmo com `-d display_errors=1`;
  com `APP_DEBUG=1` → `display_errors=1`.

### Achados extras durante a validação

| Achado | Situação |
|---|---|
| `api/relatorios/rapido.php` usava `__DIR__ . '/../../../config/...'` (aponta para fora do app) → fatal 500 no relatório rápido da tela de Relatórios | **Corrigido** para `../../` |
| `api/comunicacao/whatsapp_links.php` e `api/regionalizacao/microrregioes.php` têm o mesmo caminho errado (`../../../`) | **Pendente** — não alterado |
| `api/relatorios/visao-geral.php` responde 200 sem autenticação e quebra em `Unknown column 'status_lote'` | **Pendente** — corresponde ao B-09 do PRD |
| `index.html` na raiz contém uma cópia antiga do roteador PHP (servida como HTML, vaza código-fonte) | **Pendente** — avaliar remoção |
