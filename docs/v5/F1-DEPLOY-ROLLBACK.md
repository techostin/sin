# F1 — Deploy, Verificação e Rollback

> **Data:** 30/10/2026 · **Aplica-se a:** F1/K2/D1 · **Nada é destrutivo no banco.**
> Staging: `C:\xampp\htdocs\app-staging` · main: `C:\xampp\htdocs\app`

---

## 1. Verificação em um comando

```powershell
.\scripts\f1-check.ps1 -BaseUrl "http://localhost/app"
```

O script roda `composer test`, `composer analyse`, `composer deptrac`, `composer lint` e valida
`/api/v1/health` + `/api/v1/territorios/estados`. Falha com código diferente de zero se algo
estiver fora do padrão.

---

## 2. Sincronizar o staging (procedimento seguro)

> **ATENÇÃO:** nunca apague a pasta `app-staging` com `Remove-Item -Recurse` enquanto `vendor` for
> uma junção: isso apaga o `vendor` do main. Remova a junção primeiro.

```powershell
cd C:\xampp\htdocs\app
git worktree remove --force C:\xampp\htdocs\app-staging
git worktree prune
git worktree add --detach C:\xampp\htdocs\app-staging <commit-ou-tag>

# 3. recriar a junção do vendor (sem copiar 176 MB)
cmd /c mklink /J C:\xampp\htdocs\app-staging\vendor C:\xampp\htdocs\app\vendor

# 4. copiar segredo local e uploads vazios
Copy-Item .\config\secrets.php .\..\app-staging\config\secrets.php -Force
New-Item -ItemType Directory -Path .\..\app-staging\uploads -Force | Out-Null
Copy-Item .\uploads\.htaccess .\..\app-staging\uploads\.htaccess -Force
```

Ajustar **somente na cópia de staging**:

| Arquivo | Ajuste |
|---|---|
| `config/config.php` | `DB_NAME=sisagro_staging`; `SITE_URL=http://localhost/app-staging`; cookie `/app-staging/` |
| `.htaccess` | `RewriteBase /app-staging/`; relatórios `^/app-staging/relatorios/` |

Validar:

```powershell
curl.exe -s -o NUL -w "%{http_code}" http://localhost/app-staging/login.php
curl.exe -s -o NUL -w "%{http_code}" http://localhost/app-staging/api/v1/health
curl.exe -s http://localhost/app-staging/api/v1/territorios/estados
```

Esperado: `200`, `200` e JSON com 28 estados.

### Remover o staging com segurança

```powershell
cmd /c rmdir C:\xampp\htdocs\app-staging\vendor   # remove só a junção
git worktree remove --force C:\xampp\htdocs\app-staging
git worktree prune
```

---

## 3. Deploy da aplicação principal

O deploy atual é **git-based** (sem migração destrutiva):

```powershell
cd C:\xampp\htdocs\app
git fetch --all --tags
git checkout feat/f1-kernel
git pull --ff-only
composer install --no-dev --no-interaction --prefer-dist
composer dump-autoload -o
.\scripts\f1-check.ps1 -BaseUrl "http://localhost/app"
```

> `--no-dev` não instala PHPUnit/PHPStan/Deptrac. Para CI/local use `composer install`.

---

## 4. Rollback

### 4.1 Código

| Alvo | Comando | Efeito |
|---|---|---|
| F1 + K2 + D1 | `git checkout f1-k2-d1` | volta ao estado `9564ca9` |
| F1 fundação | `git checkout f1-fundacao` | volta à fundação `2979360` |
| Baseline | `git checkout pre-modularizacao` | volta ao legado congelado |

Depois: `composer install --no-dev` (se o lock mudou) e `f1-check.ps1`.

### 4.2 Feature flags

As flags ficam em `feature_flags`; para desligar tudo:

```sql
UPDATE feature_flags SET ativo = 0;
```

As flags atuais (`api.v1`, `modulos.territorio.v2`, `modulos.associados.v2`,
`relatorios.v2`) já nascem desligadas; K2 não exige ativação para os endpoints de leitura.

### 4.3 Banco

Não há migração destrutiva na F1. O rollback de código não exige rollback de schema.
`feature_flags` é aditiva e pode permanecer.

---

## 5. Critérios de aceite do deploy

- [ ] `composer test` verde.
- [ ] PHPStan/Deptrac/PHPCS verdes.
- [ ] `/api/v1/health` 200 com `database=ok`.
- [ ] `/login.php` 200 (legado intacto).
- [ ] `/api/v1/territorios/estados` 200.
- [ ] staging 200 e usando `sisagro_staging`.
- [ ] tag/commit registrados no `STATUS.md`.

---

## 6. Incidente conhecido (30/10/2026)

Ao recriar o staging, um `Remove-Item -Recurse` seguiu a junção `vendor` e removeu o vendor do main.
O vendor foi reinstalado via `composer install`; main e staging voltaram a 200. **Use o procedimento
da seção 2** para evitar recorrência.
