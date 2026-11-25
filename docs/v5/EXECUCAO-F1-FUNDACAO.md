# Execução F1 — Fundação Modular (Kernel, API v1 e Health)

> **Data:** 30/10/2026 · **Status:** **F1 iniciada e fundação funcional**.
> **Exceção de gate registrada:** a F1 foi autorizada pelo responsável mesmo com os itens 4 e 6 da
> Fase 0 pendentes. Nenhum módulo de negócio foi iniciado.
> **Nenhum segredo é exibido neste relatório.**

---

## 1. Resultado

| Item | Resultado |
|---|---|
| Commit da fundação | **`621ec90`** (39 arquivos, +3.263/−246) |
| Commit de higiene/cache | **`2979360`** |
| Tag | **`f1-fundacao`** → `2979360` |
| Branch | `feat/f1-kernel` |
| Endpoint | **`GET /app/api/v1/health` → 200** |
| Legacy | `GET /app/login.php` → **200** |
| Testes | **11 testes / 32 asserções** (PHPUnit 12.5.42) |
| PHPStan | **0 erros** (level 6, `src/`) |
| Deptrac | **0 violações** |
| PHPCS | **0 erros** (PSR-12, `src/` e `tests/`) |
| CI | `.github/workflows/ci.yml` (PHP 8.4) |

Resposta do health:

```json
{"data":{"status":"ok","php":"8.4.27","database":"ok","time":"..."},
 "meta":{"requestId":"..."}}
```

---

## 2. O que foi entregue

| F1 | Entrega | Status |
|---|---|---|
| 1.1 | Composer PSR-4 `App\` → `src/`, autoload-dev, scripts | ✅ |
| 1.2 | Bootstrap único, config existente, API sem sessão (`SINPORG_SKIP_SESSION`) | ✅ |
| 1.3 | Kernel: `ContextoUsuario`, `AssociadoRef`, `UnidadeProdutivaRef`, `AuditLogger`, `EventBus`, `FeatureFlags` | ✅ (interfaces) |
| 1.4 | HTTP: `Request`, `Response`, `Router`, `Pipeline`, middleware, front controller, `/health` | ✅ |
| 1.5 | Persistência: `Connection` (PDO), `UnitOfWork`, `Repository` base | ✅ |
| 1.6 | Segurança: WAF/request guard existentes, headers, cookie; authz/CSRF/tenant completos | ⚠️ parcial (F2) |
| 1.7 | PHPUnit, PHPStan, Deptrac, PHPCS, `composer test/analyse/deptrac/lint` | ✅ |
| 1.8 | CI, staging (item 3), feature flags; deploy 1 comando/rollback | ⚠️ parcial |
| 1.9 | Health, `X-Request-Id`, log `logs/app.log`, envelope JSON | ✅ |

### Estrutura criada

```
src/
├── Kernel/
│   ├── Bootstrap.php
│   ├── Container.php
│   ├── Contracts/           (6 interfaces)
│   ├── FeatureFlags/DatabaseFeatureFlags.php
│   ├── Http/
│   │   ├── Request.php, Response.php, Router.php, Pipeline.php
│   │   ├── MiddlewareInterface.php
│   │   └── Middleware/SecurityHeadersMiddleware.php
│   ├── Persistence/         Connection, UnitOfWork, Repository
│   └── Support/Logger.php
└── Modules/Health/HealthController.php
routes/api.php
api/v1/index.php
tests/Unit/*.php
```

Integrações:
- `composer.json` — PSR-4, autoload-dev, scripts e dev tools.
- `.htaccess` — rota `api/v1` no nível do app.
- `api/.htaccess` — fallback de reescrita para `v1/index.php` (CORS existente).
- `config/config.php` — sessão ignorada quando `SINPORG_SKIP_SESSION` está definido.
- `sql/create_feature_flags.sql` — tabela/seed (item 3).
- `.gitignore` — caches de PHPUnit/PHPStan/Deptrac e `api/uploads`.

---

## 3. Decisões de implementação

1. **API stateless:** o bootstrap da API não inicia sessão; autenticação virá em middleware (K1).
2. **Kernel não depende de módulos:** o Deptrac pegou `Bootstrap → HealthController`; a ligação do
   controller foi movida para `routes/api.php`, mantendo a regra `Modules → Kernel`.
3. **Um só front controller:** `/api/v1/*` é reescrito para `api/v1/index.php`; as rotas legadas do
   `index.php` continuam intactas.
4. **Envelope JSON** (`data`/`meta` e `error`) e `X-Request-Id` em todas as respostas.
5. **Erros não vazam** stack/SQL: o front controller responde 500 genérico e registra em `error_log`.
6. **Nenhuma migração destrutiva**; `feature_flags` é aditiva.

---

## 4. Pendências e limites

| Item | Situação | Próximo passo |
|---|---|---|
| Authz/CSRF/tenant/upload no Kernel | parcial | implementar guards/policies na F1.6 |
| Deploy 1 comando + rollback | parcial | runbook + script de staging |
| Staging | criado no item 3, commit anterior à F1 | sincronizar codebase F1 no staging |
| Itens 4 e 6 da Fase 0 | pendentes externos/humanos | Resend/logs/LGPD e nomes de fiscais |
| Pacotes dev | `qossmic/deptrac` e `phpcsstandards/php_codesniffer` emitem aviso de abandono | trocar por `deptrac/deptrac`/`squizlabs` em commit de manutenção |
| Push | não realizado | requer credenciais/decisão |

---

## 5. Próximos passos (F1/F2)

1. Implementar `AuthMiddleware`/`Policy`/`TenantScope` no Kernel e testes de IDOR/tenant.
2. Fechar deploy/rollback e sincronizar o staging com a tag `f1-fundacao`.
3. Iniciar o piloto **K2** (endpoints de território) usando o mesmo padrão.
4. Só depois iniciar **D1** (associados), conforme kickoff.

---

## 6. Conclusão

A fundação da F1 está **funcional e com quality gate verde**. A F1 não está "concluída" porque
authz/deploy completos e os itens 4/6 da Fase 0 seguem pendentes — mas a modularização **começou** e
tem base testável para K2/D1.
