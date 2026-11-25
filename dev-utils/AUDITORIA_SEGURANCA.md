# Auditoria de Segurança — SINPORG (app)

Data: 2026-08-04
Escopo: CSRF em formulários JS/fetch e validação de entrada nas APIs.

## Resumo

| Item | Estado antes | Estado depois |
|------|--------------|---------------|
| CSRF em APIs REST de escrita | ❌ Ausente (só login por sessão) | ✅ Obrigatório (token em header/form) |
| CSRF em forms PHP tradicionais | ✅ Já existia (classe CSRFProtection) | ✅ Mantido |
| Injeção de SQL (SQLi) | ✅ Prepared statements + whitelists | ✅ Mantido |
| Validação de entrada (campos obrigatórios) | ⚠️ Parcial por API | ⚠️ Mantido (fora do escopo de refactor) |

## Vulnerabilidade corrigida: CSRF em APIs

Todas as APIs de escrita (POST/PUT/DELETE/PATCH) exigiam `isLoggedIn()`, mas
NENHUMA validava token CSRF. Como a sessão via cookie é enviada automaticamente,
um site malicioso podia disparar escritas em nome de um usuário logado.

### Correção
1. `classes/CSRFProtection.php`: adicionados `getToken()`, `validateToken($token)`
   e `tokenFromRequest()` (lê token de header `X-CSRF-Token`, `$_POST`, `$_GET`
   ou corpo JSON, nesta ordem).
2. `includes/header.php`: expõe `window.CSRF_TOKEN` e instala um **wrapper global
   de `window.fetch`** que injeta o header `X-CSRF-Token` em toda requisição de
   escrita (POST/PUT/DELETE/PATCH). Assim todos os callers existentes passam a
   enviar o token automaticamente, sem editar arquivo por arquivo.
3. Guard CSRF adicionado nas APIs:
   - `api/certificadoras.php` (POST/PUT/DELETE)
   - `api/certificacao/controller.php` (POST/PUT/DELETE/PATCH)
   - `api/plano_manejo.php` (POST/PUT/DELETE/PATCH)
   - `api/roteiros_inspecao.php` (POST/PUT/DELETE/PATCH)
   - `api/nao_conformidades.php` (POST/PUT/DELETE)
   - `api/ocorrencias.php` (PATCH; também POST/PUT/DELETE)
   - `api/toggle_presenca.php` (reforço do header X-Requested-With existente)

Requisições sem token válido recebem HTTP 403.

## Validação de entrada (SQLi)
O código já usa PDO com `prepare()`/`execute()` e parâmetros bound em todo o
backend. Listas dinâmicas de colunas (`implode` de `$fields`) são construídas a
partir de **whitelists** (`$allowed`, mapas fixos). Nenhuma interpolação de
`$_GET`/`$_POST` direta em SQL foi encontrada. Recomenda-se, para evolução:
validação de tipo/comprimento nos campos de cada API (ex.: UF = 2 chars,
e-mail válido) e sanitização de saída (htmlspecialchars) onde ainda faltar.

## Testes
`dev-utils/_csrf_selftest.php` valida a lógica de token (geração, validação
positiva/negativa, leitura por header/POST/ausente). Executar:
`php dev-utils/_csrf_selftest.php`
