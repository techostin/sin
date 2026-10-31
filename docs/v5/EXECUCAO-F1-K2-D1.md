# Execução F1.6 + Piloto K2 + Fatia D1

> **Data:** 30/10/2026 · **Status:** F1.6 concluída na fundação; **K2 piloto entregue**; **D1 iniciada
> na camada de leitura, ainda sem rota pública**.
> **Gate:** itens 4 e 6 da Fase 0 continuam pendentes (externos/humanos).

---

## 1. Evidências

| Item | Resultado |
|---|---|
| Testes | **31 testes / 84 asserções** (PHPUnit 12.5.42) |
| PHPStan | **0 erros** (level 6) |
| Deptrac | **0 violações** |
| PHPCS | **0 erros** (PSR-12) |
| Health main | **200** |
| Login legado main | **200** |
| Staging | **200** em login, health, estados e CNPO |
| Commit do código | **`9564ca9`** |
| Tag | **`f1-k2-d1`** → `9564ca9` |
| Branch | `feat/f1-kernel` |

Verificação em um comando: [`scripts/f1-check.ps1`](../../scripts/f1-check.ps1).

---

## 2. F1.6 — Segurança no Kernel

| Componente | Arquivo | Cobertura |
|---|---|---|
| Política de autorização | `src/Kernel/Security/Policy.php` + `RolePolicy.php` | admin, gestor, técnico, diretoria, agricultor, agroindústria; nega por padrão |
| Escopo/tenant | `TenantScope.php` | certificadora e associações visíveis |
| CSRF | `CsrfToken.php` | geração e comparação `hash_equals` |
| Upload | `UploadValidator.php` | erro, tamanho, arquivo real e MIME via `finfo` |
| Auth | `Http/Middleware/AuthMiddleware.php` | 401 sem contexto; passa com contexto |
| Testes | `tests/Unit/SecurityTest.php` | 8 cenários |

> A resolução real do contexto (sessão/token) continua na K1; a F1.6 entrega o **mecanismo** e a
> matriz, sem acoplar módulos ao `$_SESSION`.

---

## 3. K2 — Piloto de Território

### Endpoints publicados

| Endpoint | Resultado real |
|---|---|
| `GET /api/v1/territorios/estados` | 28 estados |
| `GET /api/v1/territorios/cidades?uf=RS` | 497 cidades |
| `GET /api/v1/territorios/microrregioes?mesorregiao_id=1` | 7 microrregiões |
| `GET /api/v1/territorios/cnpo?termo=terra&limite=2` | 2 registros |

- Consultas com prepared statements e `LIMIT` limitado a 100.
- CNPO: termo mínimo de 2 caracteres, filtros UF/cidade/tipo; parâmetros nomeados duplicados foram
  corrigidos (`:termo_nome`/`:termo_entidade`).
- Erros de validação retornam 400 com `code=VALIDACAO`.
- Testes de controller com repositório fake: 7 cenários.

### Arquivos

```
src/Modules/Territorio/
├── TerritorioRepositoryInterface.php
├── DatabaseTerritorioRepository.php
└── TerritorioController.php
```

---

## 4. D1 — Fatia A (somente leitura, sem exposição)

| Entrega | Arquivo |
|---|---|
| Repositório paginado/filtrado | `src/Modules/Associados/DatabaseAssociadosRepository.php` |
| Contrato | `AssociadosRepositoryInterface.php` |
| Controller | `AssociadosController.php` |
| Testes | `tests/Unit/AssociadosControllerTest.php` (5 cenários) |

**Decisão de segurança:** as rotas de D1 **não foram publicadas**. CPF e e-mail são mascarados no
controller, mas o endpoint de associados contém dados pessoais e a K1 (contexto autenticado) ainda
não existe. Expor agora criaria um IDOR/vazamento. A próxima etapa é plugar `AuthMiddleware` +
`RolePolicy` e então liberar `GET /api/v1/associados`.

---

## 5. F1.8 — Staging e rollback

- Staging recriado no commit `9564ca9` em `http://localhost/app-staging`.
- `sisagro_staging` (anonimizado) e 4 feature flags inalteradas.
- `app-staging` usa vendor por junção; **nunca remover a pasta com `Remove-Item -Recurse`**.
- Runbook: [`F1-DEPLOY-ROLLBACK.md`](F1-DEPLOY-ROLLBACK.md).
- Deploy de produção ainda é operacional (git + composer install); o runbook cobre verificação e
  rollback por tag.

### Incidente e recuperação

Durante a recriação do staging, um `Remove-Item -Recurse` seguiu a junção e apagou o `vendor` do
main. Diagnóstico: permissão do diretório; correção com o utilitário de ACL + `composer install`.
Main e staging voltaram a 200. O procedimento seguro (remover a junção antes) está no runbook.

---

## 6. O que permanece aberto

| Item | Situação |
|---|---|
| D1 exposto via API | aguarda K1 (auth) |
| Deploy de produção automatizado | runbook manual; sem pipeline de deploy |
| Itens 4 e 6 da Fase 0 | Resend/logs/LGPD e fiscais — externos/humanos |
| Push ao remoto | pendente |

---

## 7. Conclusão

A F1.6 está implementada e testada; K2 tem um piloto funcional com dados reais; D1 tem a camada de
leitura pronta e mascarada, deliberadamente não exposta. O staging está sincronizado e o rollback
documentado. Nenhum módulo de escrita foi migrado.
