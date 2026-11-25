# Execução do item 4 — Hardening do PHP (P0)

> **Data:** 2026-10-27 · **Escopo:** ambiente local XAMPP (`C:\xampp`) e aplicação em `C:\xampp\htdocs\app`
> **Status:** **executado** no `php.ini` e no `.htaccess`; **pendente apenas o restart do serviço Apache2.4**
> (exige elevação de administrador) para ativar `expose_php=Off` e `disable_functions`.
> **Itens 1–3:** verificados — PHP atual **8.4.27** (build de 26/10/2026), backup da versão anterior em
> `C:\xampp\php-backup-8.2.12-20261027-202352`.

---

## 1. Resumo

| Item | Resultado |
|---|---|
| `php.ini` global endurecido | **17 diretivas alteradas**, com backup e checksum |
| `.htaccess` da aplicação | **camada imediata** com 12 diretivas (vale por requisição, sem reiniciar) |
| Diretório de log do PHP | `C:\xampp\php\logs` criado e testado |
| Validação funcional | login **200**, API **200**, vendor OK, Composer OK, `httpd -t` **Syntax OK** |
| Restart do Apache2.4 | **bloqueado** (serviço roda como SYSTEM; shell sem elevação) |
| Itens que dependem do restart | `expose_php=Off` (header `X-Powered-By`) e `disable_functions` no Apache |

**Mitigação crítica já ativa:** `session.upload_progress.enabled=0` (vetor do CVE-2026-109619), além de
`display_errors=Off`, `post_max_size=32M`, `upload_max_filesize=30M`, sessão `HttpOnly`/`SameSite=Lax`
e `zend.exception_ignore_args=1`.

---

## 2. Estado antes

| Item | Valor |
|---|---|
| PHP CLI/Apache | 8.4.27 (corrigido para CVE-2026-109618/109619) |
| `php.ini` ativo | `C:\xampp\php\php.ini` (77.423 bytes; SHA-256 `EABE3D77…E1B42F`) |
| `display_errors` / `display_startup_errors` | `On` / `On` |
| `expose_php` | `On` |
| `post_max_size` / `upload_max_filesize` | `40M` / `40M` |
| `disable_functions` | vazio |
| `session.upload_progress.enabled` | default `On` (linha comentada) |
| `session.use_strict_mode` | `0` |
| `session.cookie_httponly` / `samesite` | vazios |
| `zend.exception_ignore_args` | `Off` |
| Apache | serviço **Apache2.4** em execução (SYSTEM), mod_php |

---

## 3. O que foi executado

### 3.1 `php.ini` (17 alterações)

Backup criado antes: `C:\xampp\php\php.ini.bak-hardening-20261027-204524`
(77.423 bytes, SHA-256 `EABE3D775EC1D65C88D6CE08A13320340552B403A0D6DD377F360644EAE1B42F`).

| Diretiva | Antes | Depois |
|---|---|---|
| `display_errors` | On | **Off** |
| `display_startup_errors` (2 ocorrências) | On | **Off** |
| `expose_php` | On | **Off** |
| `zend.exception_ignore_args` | Off | **On** |
| `post_max_size` | 40M | **32M** |
| `upload_max_filesize` | 40M | **30M** |
| `disable_functions` | vazio | **exec,shell_exec,system,passthru,popen,dl** |
| `session.upload_progress.enabled` | On (default) | **0** |
| `session.use_strict_mode` | 0 | **1** |
| `session.use_only_cookies` | default | **1** |
| `session.use_trans_sid` | default | **0** |
| `session.cookie_httponly` | vazio | **1** |
| `session.cookie_samesite` | vazio | **Lax** |
| `session.cookie_secure` | comentado | **0** (local HTTP; ver §7) |
| `cgi.fix_pathinfo` | comentado | **0** |
| `phar.readonly` | comentado | **On** |

SHA-256 depois: `B34C948C18F4932827804AE49FD87B0C04F27D0D58A9DF4E283927567B356278`
(tamanho 77.514 bytes).

### 3.2 `.htaccess` (camada imediata, linhas 67–87)

Bloco `<IfModule php_module>` com:

```
php_value display_errors Off
php_value display_startup_errors Off
php_value log_errors On
php_value zend.exception_ignore_args 1
php_value post_max_size 32M
php_value upload_max_filesize 30M
php_value session.upload_progress.enabled 0
php_value session.use_strict_mode 1
php_value session.use_only_cookies 1
php_value session.use_trans_sid 0
php_value session.cookie_httponly 1
php_value session.cookie_samesite Lax
```

Motivo: o serviço Apache2.4 já estava em execução e não pôde ser reiniciado sem elevação. `.htaccess`
com `php_value` aplica as diretivas **por requisição**, sem depender de restart. Em PHP-FPM o bloco é
ignorado (o `php.ini` cobre). `expose_php` e `disable_functions` são `PHP_INI_SYSTEM` e **não** podem
ser definidas por `.htaccess` — dependem do restart.

### 3.3 Diretório de log

`C:\xampp\php\logs` criado (não existia). Teste de escrita executado com sucesso:

```
[28-Oct-2026 00:45:56 Europe/Berlin] hardening-ok 2026-10-28T00:45:56+01:00
```

### 3.4 Limpeza e preservação

- Arquivos temporários de teste removidos da raiz (`check_ini.php`, `check_vendor.php`,
  `zz_verifica_ini.php`, `php.ini.test`, `_write_test.txt`).
- Cópia do `php.ini` que havia sido criada em `docs/v5/backups/` foi **removida** (não deve ficar
  dentro do webroot).
- Script de hardening preservado em `docs/v5/scripts/hardening_php_ini.ps1`.

---

## 4. Evidências de validação

### 4.1 CLI (php.ini global)

| Diretiva | Valor efetivo |
|---|---|
| `display_errors` / `display_startup_errors` | `''` (desligados) |
| `expose_php` | `''` (desligado) |
| `post_max_size` / `upload_max_filesize` | `32M` / `30M` |
| `disable_functions` | `exec,shell_exec,system,passthru,popen,dl` |
| `session.upload_progress.enabled` | `0` |
| `session.use_strict_mode` / `use_only_cookies` | `1` / `1` |
| `session.cookie_httponly` / `samesite` | `1` / `Lax` |
| `zend.exception_ignore_args` | `1` |
| `exec`, `shell_exec`, `system`, `passthru`, `popen` | **desabilitadas** (`function_exists` = false) |
| `proc_open` | **ativa** (necessária para o Composer; não usada pela aplicação) |

### 4.2 Apache (php.ini em memória + `.htaccess`)

| Diretiva | Antes | Depois (via `.htaccess`) |
|---|---|---|
| `display_errors` | 1 | **Off** |
| `display_startup_errors` | 1 | **Off** |
| `post_max_size` | 40M | **32M** |
| `upload_max_filesize` | 40M | **30M** |
| `session.upload_progress.enabled` | 1 | **0** |
| `session.use_strict_mode` | 0 | **1** |
| `session.cookie_httponly` | vazio | **1** |
| `session.cookie_samesite` | vazio | **Lax** |
| `zend.exception_ignore_args` | vazio | **1** |
| `expose_php` | 1 | **1 (pendente de restart)** |
| `disable_functions` | vazio | **vazio (pendente de restart)** |

### 4.3 Funcional

| Teste | Resultado |
|---|---|
| `GET /app/login.php` | **200** (33.562 bytes, contém “SINPORG”) |
| `Set-Cookie` | `PHPSESSID=…; path=/; HttpOnly; SameSite=Lax` |
| `GET /app/` | **302** (redirecionamento esperado) |
| `GET /app/api/get_cidades.php` | **200** |
| `httpd -t` | **Syntax OK** |
| Vendor (mPDF, PhpSpreadsheet, TCPDF, Dompdf) | classes carregam no PHP 8.4.27 |
| Composer | `Composer version 2.8.9` funciona com o ini endurecido |
| Limite de upload da aplicação | maior arquivo existente em `uploads/` tem **3,97 MB** (limite de 30M é folgado) |

> O teste de instanciação do mPDF falhou apenas porque o diretório temporário apontado pelo sandbox
> do DSH não é gravável; não é efeito do hardening. As classes e o autoload funcionam.

---

## 5. Pendência — restart do serviço Apache2.4

O serviço roda como **SYSTEM** e o shell atual não tem elevação:

```
Restart-Service Apache2.4  →  "Não é possível abrir o serviço Apache2.4"
httpd -k restart           →  AH10014: Failed to open the 'Apache2.4' service
```

**Ação necessária (uma vez, como administrador):**

1. Abrir o **XAMPP Control Panel** como administrador → **Stop** e **Start** em Apache; **ou**
2. Em um PowerShell **como administrador**:
   ```powershell
   Restart-Service Apache2.4 -Force
   ```

**Após o restart, validar:**

```powershell
# 1) X-Powered-By deve desaparecer
(Invoke-WebRequest http://localhost/app/login.php -UseBasicParsing).Headers['X-Powered-By']

# 2) php.ini efetivo no Apache (criar temporariamente um .php com phpinfo ou usar o script de verificação)
```

Resultado esperado: `expose_php=Off`, `disable_functions=exec,shell_exec,system,passthru,popen,dl`
ativos também no Apache. O restante já está ativo via `.htaccess`.

---

## 6. Riscos residuais e decisões conscientes

| Item | Situação | Motivo / próxima ação |
|---|---|---|
| `allow_url_fopen=On` | mantido | `classes/CarteirinhaImpressa.php:65` usa `file_get_contents` para gerar QR (`api.qrserver.com`). URL fixa; risco de SSRF baixo. Reavaliar troca por cURL com allowlist |
| `proc_open` ativo | mantido | necessário ao Composer; a aplicação não usa. Se o Composer não for usado no servidor, desabilitar |
| `open_basedir` | não definido | poderia quebrar `vendor/`, `uploads/`, `tmp/` e o Composer; exige mapeamento antes |
| `session.cookie_secure=0` | local (HTTP) | em produção/HTTPS deve ser `1`; o app já define `secure` dinamicamente via `session_set_cookie_params` |
| `X-Powered-By` | ainda presente | expõe `PHP/8.4.27` até o restart do serviço |
| `disable_functions` no Apache | pendente | ativa após restart; a aplicação não usa essas funções, então o risco imediato é baixo |
| `APP_DEBUG` | app pode religar erros | `config/error_config.php` faz `ini_set('display_errors', APP_DEBUG ? '1':'0')`; garantir `APP_DEBUG=0` em produção |
| `.htaccess` com `php_value` | funciona no XAMPP atual | exige `AllowOverride` com `FileInfo/Options`; em PHP-FPM é ignorado (php.ini cobre) |
| Backup do php.ini | fora do webroot | `C:\xampp\php\php.ini.bak-hardening-20261027-204524`; manter até validação em produção |

---

## 7. Como reverter (se necessário)

1. Restaurar o ini:
   ```powershell
   Copy-Item 'C:\xampp\php\php.ini.bak-hardening-20261027-204524' 'C:\xampp\php\php.ini' -Force
   ```
2. Remover o bloco “HARDENING PHP” do `.htaccess` (linhas 67–87) — **não** usar `git checkout`,
   pois o arquivo tem outras alterações não commitadas.
3. Reiniciar o serviço Apache2.4 (admin).
4. Nenhum dado de banco foi alterado.

---

## 8. Próximos itens do P0

| Item | Status | Observação |
|---|---|---|
| 1. Backup | ✅ (informado pelo usuário) | validar restauração, se ainda não feita |
| 2. Verificar PHP em produção | ✅ (local 8.4.27) | **confirmar no servidor de produção** |
| 3. Atualizar PHP | ✅ local 8.4.27 | produção precisa do mesmo patch (≥ 8.4.27 / 8.5.12) |
| **4. Hardening do php.ini** | ✅ **executado** (restart pendente) | este relatório |
| 5. WAF / regras virtuais | ⏳ | Cloudflare/AWS/Fastly enquanto o patch propaga |
| 6. Rotacionar segredos e sessões | ⏳ **prioridade alta** | a CVE permite leitura de memória; rotacionar DB, Resend, `ID_ENCODE_KEY`, sessões |
| 7. Preservar/revisar logs | ⏳ | procurar multipart anômalo, memory limit, 500, `PHPSESSID` malformado |
| 8. Avaliação LGPD | ⏳ | Res. CD/ANPD 15/2024: 3 dias úteis (6 para pequeno porte) |

---

## Anexo — Arquivos e rastreabilidade

| Artefato | Caminho |
|---|---|
| `php.ini` endurecido | `C:\xampp\php\php.ini` (SHA-256 `B34C948C…B356278`) |
| Backup do original | `C:\xampp\php\php.ini.bak-hardening-20261027-204524` |
| Camada imediata | `.htaccess` (bloco nas linhas 67–87) |
| Diretório de log | `C:\xampp\php\logs` |
| Script de reprodução | `docs/v5/scripts/hardening_php_ini.ps1` |
| Plano relacionado | [`PLANO-DE-MODULOS-E-EXECUCAO.md`](PLANO-DE-MODULOS-E-EXECUCAO.md) |
| Parecer de segurança | [`PARECER-LINGUAGEM-SEGURANCA-LGPD.md`](PARECER-LINGUAGEM-SEGURANCA-LGPD.md) |
