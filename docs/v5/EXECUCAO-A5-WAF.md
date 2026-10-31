# Execução do item 5 — WAF e compensações de borda (P0)

> **Data:** 2026-10-27 · **Escopo:** XAMPP local (`C:\xampp`) e aplicação em `C:\xampp\htdocs\app`
> **Status:** **executado no ambiente local** (Apache + aplicação). **WAF de borda externo não
> configurado** (sem credenciais Cloudflare/AWS/Fastly) e **TraceEnable Off pendente de restart**.
> **Nenhum valor de segredo é exibido neste relatório.**

---

## 1. Resumo

| Camada | O que foi feito | Status |
|---|---|---|
| Apache `.htaccess` | limite de corpo, bloqueio de métodos, travessia, scanners, diretórios internos | **ativo** |
| Cabeçalhos HTTP | `X-Powered-By` removido; `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` | **ativo** |
| WAF de aplicação | `config/request_guard.php` no bootstrap (métodos, travessia, UA, tamanho, cookie de sessão) | **ativo** |
| `httpd.conf` | `TraceEnable Off`, `ServerTokens Prod`, `ServerSignature Off` | **configurado; requer restart** |
| ModSecurity / mod_evasive | **não instalados** no XAMPP | lacuna |
| WAF de borda (Cloudflare/AWS/Fastly) | regras documentadas; **sem credenciais para ativar** | pendente externo |

---

## 2. Controles implementados

### 2.1 Apache — `.htaccess`

| Regra | Efeito |
|---|---|
| `LimitRequestBody 33554432` | corpo máximo de 32 MB, coerente com `post_max_size=32M` |
| `RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)$` + `[F]` | bloqueio de métodos de diagnóstico via mod_rewrite |
| `RewriteCond %{THE_REQUEST} ... (\.\.|%2e%2e|%00)` | bloqueio de travessia/codificação suspeita na URL |
| UA `sqlmap|nikto|nmap|masscan|zgrab|acunetix|nessus|dirbuster|gobuster|wpscan` | bloqueio de scanners conhecidos |
| `^ (vendor|dev-utils|sql|tmp|storage|logs|config|node_modules|api/legacy)(/|$)` | nega acesso web direto a diretórios internos |
| `<IfModule mod_headers.c>` | `X-Powered-By` removido; `X-Content-Type-Options: nosniff`; `X-Frame-Options: SAMEORIGIN`; `Referrer-Policy`; `Permissions-Policy` |

> CSP não foi aplicada de propósito: o sistema usa scripts inline e a CSP quebraria telas.

### 2.2 Aplicação — `config/request_guard.php`

Incluído no início de `config/config.php` (antes de sessão e regras). Bloqueia:

- métodos `TRACE`/`TRACK`;
- travessia (`../`, `%2e%2e`, `%00`) em URI/query;
- user-agents de scanners conhecidos;
- `Content-Length` acima de 32 MB (413);
- cookie `PHPSESSID` malformado (remove o cookie e registra; não bloqueia a navegação);
- remove `X-Powered-By` também no PHP.

Auditoria: `logs/security.log` (negado pela web), com IP, método, caminho, motivo e UA truncado —
sem corpo, query ou dados pessoais.

### 2.3 `httpd.conf` (pendente de restart)

Adicionado após `ServerRoot`:

```
ServerTokens Prod
ServerSignature Off
TraceEnable Off
```

Validação: `httpd -t` = **Syntax OK**. O serviço Apache2.4 é controlado pelo Windows (SYSTEM) e
requer elevação para restart; a ativação fica pendente.

---

## 3. Testes executados

| Teste | Esperado | Resultado |
|---|---|---|
| `GET /app/login.php` | 200 | **200** |
| `GET /app/api/get_cidades.php` | 200 | **200** |
| `GET /app/assets/css/style.css` | 200 | **200** |
| `GET /app/index.php?url=api/relatorios/../../config/config` | 403 | **403** |
| `User-Agent: sqlmap` / `nikto` | 403 | **403** |
| `GET /app/vendor/autoload.php` | 403 | **403** |
| `GET /app/config/secrets.php` | 403 | **403** |
| `GET /app/logs/security.log` | 403 | **403** |
| `GET /app/tmp/` | 403 | **403** |
| Cookie `PHPSESSID=../../evil` | 200 + registro | **200** + registro em `security.log` |
| `TRACE /app/login.php` | 403 | **200** (Apache responde antes do PHP; `TraceEnable Off` só após restart) |
| Cabeçalhos de segurança | presentes | **presentes**; `X-Powered-By` ausente |

Evidência do WAF de aplicação (preservada em
`C:\xampp\incident-2026-10-27\logs\security-waf-test.log`):

```
[2026-10-28T01:10:33+01:00] ip=::1 metodo=GET caminho=/app/login.php motivo=cookie_sessao_invalido ua=curl/8.21.0
```

> O `500` registrado em `/app/login.php` às 21:10:49 foi **causado pelo teste interno** da diretiva
> `<Limit>` (mensagem do Apache: “TRACE cannot be controlled by `<Limit>`”); a diretiva foi removida
> e a página voltou a 200.

---

## 4. Observação sobre o `php.ini` no Apache

Durante a validação, o Apache já apresentava o `php.ini` endurecido ativo:

| Diretiva | Valor efetivo no Apache |
|---|---|
| `expose_php` | desligado |
| `disable_functions` | `exec,shell_exec,system,passthru,popen,dl` |
| `display_errors` | Off |
| `post_max_size` / `upload_max_filesize` | 32M / 30M |
| `session.upload_progress.enabled` | 0 |
| `session.use_strict_mode` | 1 |
| `session.cookie_httponly` / `samesite` | 1 / Lax |

Ou seja, o item 4 está **efetivo no Apache**; o que resta é o novo restart para carregar
`TraceEnable Off`/`ServerTokens`/`ServerSignature`.

---

## 5. WAF de borda — o que falta e como configurar

Não há credenciais nem acesso ao provedor de borda neste ambiente; nada foi ativado externamente.
Recomendação para produção:

### Cloudflare (exemplo de regras)

```
# Bloquear travessia e diretórios internos
(http.request.uri.path contains "../") or
(http.request.uri.path contains "%2e%2e") or
(http.request.uri.path contains "/app/config/") or
(http.request.uri.path contains "/app/vendor/") or
(http.request.uri.path contains "/app/logs/") or
(http.request.uri.path contains "/.env") or
(http.request.uri.path contains "/wp-")
→ Block

# Rate limit no login e APIs
http.request.uri.path eq "/app/login.php"
→ Rate limit 10 req/min por IP
```

Ativar também: Managed Rules (OWASP), Bot Fight Mode, WAF de corpo/upload, alertas de 5xx.

### AWS WAF

- AWSManagedRulesCommonRuleSet + KnownBadInputsRuleSet;
- rate-based rule (ex.: 100 req/5 min por IP) no login e `/app/api/*`;
- logs para S3/CloudWatch com retenção.

### Fastly

- VCL com bloqueio de path traversal, UA de scanner e rate limit;
- logging de borda para o bucket de incidente.

---

## 6. Lacunas e riscos residuais

| Lacuna | Impacto | Mitigação |
|---|---|---|
| `TraceEnable Off` não ativo até restart | TRACE responde 200 no Apache | reiniciar Apache2.4 (admin); TRACE não é usado pela aplicação |
| Sem ModSecurity/mod_evasive | sem inspeção de corpo/rate limit local | WAF de borda em produção; monitorar `security.log` |
| WAF de aplicação só após bootstrap | não inspeciona corpo multipart | `LimitRequestBody`, `post_max_size`, patch do PHP |
| Sem rate limit local | brute force/abuso | rate limit no WAF de borda e no login (evolução) |
| `.htaccess` depende de `AllowOverride` | em host sem override, regras não valem | garantir config no vhost em produção |
| WAF não substitui patch | CVE é do runtime | PHP 8.4.27+ já ativo |

---

## 7. Arquivos e evidências

| Artefato | Caminho |
|---|---|
| Regras Apache | `.htaccess` (blocos WAF/headers) |
| WAF de aplicação | `config/request_guard.php` |
| Bootstrap | `config/config.php` (include do guard) |
| Config Apache | `C:\xampp\apache\conf\httpd.conf` (backup `httpd.conf.bak-item5-20261027-211119`) |
| Log do WAF | `logs/security.log` (negado pela web) |
| Evidência de teste | `C:\xampp\incident-2026-10-27\logs\security-waf-test.log` |
| Triagem de logs | [`EXECUCAO-A7-LOGS-INCIDENTE.md`](EXECUCAO-A7-LOGS-INCIDENTE.md) |
| Avaliação LGPD | [`EXECUCAO-A8-AVALIACAO-LGPD.md`](EXECUCAO-A8-AVALIACAO-LGPD.md) |
